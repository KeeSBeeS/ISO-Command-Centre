<?php

namespace App\Services;

use App\Models\PackageInstall;
use App\Models\PackageUpload;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Installs .isopkg update packages with a full on-disk journal so every install
 * can be rolled back: replaced files are backed up, created files are removed,
 * deleted files are restored and package migrations are reversed with down().
 *
 * Data lives in storage/app/package-manager:
 *   incoming/            uploaded packages (also an FTP / File Manager drop folder)
 *   installs/<key>/      journal.json, backup/ (original files), migrations/
 */
class PackageManager
{
    public const STATUS_INSTALLING = 'installing';
    public const STATUS_INSTALLED = 'installed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ROLLED_BACK = 'rolled_back';
    public const STATUS_RESCUED = 'rescued';
    public const ROLLBACK_STATUSES = [self::STATUS_INSTALLED, self::STATUS_INSTALLING, self::STATUS_RESCUED];

    private string $root;
    private string $dataDirectory;
    private array $log = [];

    public function __construct(?string $root = null, ?string $dataDirectory = null)
    {
        $this->root = rtrim($root ?? base_path(), '/');
        $this->dataDirectory = rtrim($dataDirectory ?? storage_path('app/package-manager'), '/');
    }

    public function ensureSchema(): void
    {
        if (!Schema::hasTable('package_uploads')) {
            Schema::create('package_uploads', function (Blueprint $table) {
                $table->id();
                $table->string('original_name');
                $table->string('stored_name')->unique();
                $table->string('sha256', 64)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->string('version', 50)->nullable();
                $table->string('title')->nullable();
                $table->longText('manifest')->nullable();
                $table->string('status', 30)->default('uploaded');
                $table->text('validation_error')->nullable();
                $table->string('source', 30)->default('upload');
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('package_installs')) {
            Schema::create('package_installs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('package_upload_id')->nullable();
                $table->string('install_key', 100)->unique();
                $table->string('package', 100);
                $table->string('version', 50);
                $table->string('previous_version', 50)->nullable();
                $table->string('title')->nullable();
                $table->string('status', 30)->default(self::STATUS_INSTALLING);
                $table->longText('manifest')->nullable();
                $table->string('package_sha256', 64)->nullable();
                $table->unsignedInteger('files_added')->default(0);
                $table->unsignedInteger('files_replaced')->default(0);
                $table->unsignedInteger('files_deleted')->default(0);
                $table->unsignedInteger('migrations_run')->default(0);
                $table->longText('log')->nullable();
                $table->text('error')->nullable();
                $table->unsignedBigInteger('installed_by')->nullable();
                $table->timestamp('installed_at')->nullable();
                $table->unsignedBigInteger('rolled_back_by')->nullable();
                $table->timestamp('rolled_back_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'id']);
            });

            // First run: the files on disk (VERSION) are the source of truth for the starting version.
            $fileVersion = is_file($this->root . '/VERSION') ? trim((string) file_get_contents($this->root . '/VERSION')) : '';
            if (PackageArchive::isValidVersion($fileVersion) && version_compare($fileVersion, $this->currentVersion(), '>')) {
                $this->setPlatformVersion($fileVersion);
            }
        }

        if (Schema::hasTable('permissions') && !Permission::where('slug', 'packages.manage')->exists()) {
            $permission = Permission::create([
                'name' => 'Manage Update Packages',
                'slug' => 'packages.manage',
                'module' => 'System',
                'description' => 'Upload, install and roll back platform update packages.',
            ]);

            Role::where('slug', 'system-administrator')->get()->each(function (Role $role) use ($permission) {
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            });
        }

        $this->syncRescuedInstalls();
    }

    public function currentVersion(): string
    {
        if (Schema::hasTable('system_settings')) {
            $version = DB::table('system_settings')->where('key', 'platform_version')->value('value');
            if ($version && PackageArchive::isValidVersion((string) $version)) {
                return (string) $version;
            }
        }

        $file = $this->root . '/VERSION';
        if (is_file($file) && PackageArchive::isValidVersion(trim((string) file_get_contents($file)))) {
            return trim((string) file_get_contents($file));
        }

        return '0.0.0';
    }

    public function incomingDirectory(): string
    {
        return $this->directory('incoming');
    }

    public function signingKey(): string
    {
        return (string) config('packages.signing_key', '');
    }

    public function rescueEnabled(): bool
    {
        return (string) config('packages.rescue_key', '') !== '';
    }

    public function uploadLimitBytes(): int
    {
        return min($this->iniBytes('upload_max_filesize'), $this->iniBytes('post_max_size')) ?: 0;
    }

    public function storeUpload(UploadedFile $file, ?User $user): PackageUpload
    {
        $this->ensureSchema();

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, [PackageArchive::EXTENSION, 'zip'], true)) {
            throw new PackageManagerException('Upload a .isopkg update package (or a .zip built by the package builder).');
        }

        $storedName = date('Ymd-His') . '-' . Str::lower(Str::random(8)) . '.' . PackageArchive::EXTENSION;
        $file->move($this->incomingDirectory(), $storedName);

        return $this->register($storedName, $file->getClientOriginalName(), 'upload', $user);
    }

    /** Registers packages copied into the incoming folder by FTP or File Manager. */
    public function scanIncoming(?User $user): int
    {
        $this->ensureSchema();

        $known = PackageUpload::pluck('stored_name')->all();
        $count = 0;

        foreach (glob($this->incomingDirectory() . '/*.{' . PackageArchive::EXTENSION . ',zip}', GLOB_BRACE) ?: [] as $path) {
            $name = basename($path);
            if (in_array($name, $known, true)) {
                continue;
            }

            $this->register($name, $name, 'drop_folder', $user);
            $count++;
        }

        return $count;
    }

    public function deleteUpload(PackageUpload $upload): void
    {
        @unlink($this->incomingDirectory() . '/' . $upload->stored_name);
        $upload->delete();
    }

    /**
     * Pre-install inspection: compatibility checks and the file-by-file plan.
     *
     * @return array{manifest: array, checks: array, plan: array, can_install: bool, current_version: string}
     */
    public function inspect(PackageUpload $upload): array
    {
        $currentVersion = $this->currentVersion();
        $checks = [];
        $plan = [];

        try {
            $archive = PackageArchive::open($this->uploadPath($upload));
        } catch (PackageManagerException $e) {
            return [
                'manifest' => $upload->manifest ?? [],
                'checks' => [['label' => 'Package readable', 'ok' => false, 'message' => $e->getMessage()]],
                'plan' => [],
                'can_install' => false,
                'current_version' => $currentVersion,
            ];
        }

        $manifest = $archive->manifest();
        $requires = $manifest['requires'];

        $checks[] = [
            'label' => 'Newer version',
            'ok' => version_compare($manifest['version'], $currentVersion, '>'),
            'message' => "Package v{$manifest['version']} · installed v{$currentVersion}",
        ];

        if (!empty($requires['min_version'])) {
            $checks[] = [
                'label' => 'Minimum installed version',
                'ok' => version_compare($currentVersion, $requires['min_version'], '>='),
                'message' => "Requires v{$requires['min_version']} or newer to be installed first.",
            ];
        }

        if (!empty($requires['max_version'])) {
            $checks[] = [
                'label' => 'Maximum installed version',
                'ok' => version_compare($currentVersion, $requires['max_version'], '<='),
                'message' => "Only applies to v{$requires['max_version']} or older.",
            ];
        }

        if (!empty($requires['php'])) {
            $checks[] = [
                'label' => 'PHP version',
                'ok' => version_compare(PHP_VERSION, (string) $requires['php'], '>='),
                'message' => 'Requires PHP ' . $requires['php'] . ' · server runs ' . PHP_VERSION,
            ];
        }

        foreach ((array) ($requires['extensions'] ?? []) as $extension) {
            $checks[] = [
                'label' => 'PHP extension ' . $extension,
                'ok' => extension_loaded((string) $extension),
                'message' => extension_loaded((string) $extension) ? 'Loaded' : 'Missing on this server',
            ];
        }

        $key = $this->signingKey();
        if ($key !== '') {
            $checks[] = [
                'label' => 'Signature',
                'ok' => $archive->signatureValid($key),
                'message' => $archive->isSigned() ? ($archive->signatureValid($key) ? 'Signed with the site signing key.' : 'Signature does not match PACKAGE_SIGNING_KEY.') : 'Unsigned package. This site requires signed packages.',
            ];
        }

        $problems = $archive->verifyContents();
        $checks[] = [
            'label' => 'Checksums',
            'ok' => $problems === [],
            'message' => $problems === [] ? 'All ' . (count($archive->files()) + count($archive->migrations())) . ' file(s) match their SHA-256 hashes.' : implode(' ', array_slice($problems, 0, 5)),
        ];

        $unwritable = [];
        foreach ($archive->files() as $file) {
            $target = $this->root . '/' . $file['path'];
            $exists = is_file($target);
            $sameContent = $exists && hash_equals($file['sha256'], (string) hash_file('sha256', $target));

            $plan[] = ['action' => $exists ? ($sameContent ? 'unchanged' : 'replace') : 'add', 'path' => $file['path'], 'size' => $file['size']];

            if (!$this->isWritableTarget($target)) {
                $unwritable[] = $file['path'];
            }
        }

        foreach ($archive->deletes() as $path) {
            $target = $this->root . '/' . $path;
            $plan[] = ['action' => is_file($target) ? 'delete' : 'already_absent', 'path' => $path, 'size' => is_file($target) ? filesize($target) : 0];

            if (is_file($target) && !is_writable(dirname($target))) {
                $unwritable[] = $path;
            }
        }

        foreach ($archive->migrations() as $migration) {
            $plan[] = ['action' => 'migration', 'path' => $migration['name'], 'size' => 0];
        }

        $checks[] = [
            'label' => 'File permissions',
            'ok' => $unwritable === [],
            'message' => $unwritable === [] ? 'All target paths are writable.' : 'Not writable: ' . implode(', ', array_slice($unwritable, 0, 8)) . (count($unwritable) > 8 ? ' …' : ''),
        ];

        $pending = PackageInstall::whereIn('status', [self::STATUS_INSTALLING, self::STATUS_RESCUED])->exists();
        $checks[] = [
            'label' => 'No unfinished install',
            'ok' => !$pending,
            'message' => $pending ? 'An interrupted or rescued install must be rolled back first (see Install History).' : 'No interrupted installs.',
        ];

        return [
            'manifest' => $manifest,
            'checks' => $checks,
            'plan' => $plan,
            'can_install' => collect($checks)->every(fn ($check) => $check['ok']),
            'current_version' => $currentVersion,
        ];
    }

    public function install(PackageUpload $upload, ?User $user): PackageInstall
    {
        $this->ensureSchema();

        return $this->withLock(function () use ($upload, $user) {
            $report = $this->inspect($upload);
            if (!$report['can_install']) {
                $failed = collect($report['checks'])->reject(fn ($check) => $check['ok'])->map(fn ($check) => $check['label'] . ': ' . $check['message']);
                throw new PackageManagerException('Pre-install checks failed. ' . $failed->implode(' | '));
            }

            $archive = PackageArchive::open($this->uploadPath($upload));
            $manifest = $archive->manifest();
            $previousVersion = $report['current_version'];
            $installKey = date('Ymd-His') . '-v' . $manifest['version'] . '-' . Str::lower(Str::random(6));
            $installDirectory = $this->directory('installs/' . $installKey);
            $stagingDirectory = $installDirectory . '/staging';

            $this->log = [];
            $this->note("Installing {$archive->title()} (v{$manifest['version']}) over v{$previousVersion}.");

            $install = PackageInstall::create([
                'package_upload_id' => $upload->id,
                'install_key' => $installKey,
                'package' => $manifest['package'],
                'version' => $manifest['version'],
                'previous_version' => $previousVersion,
                'title' => $archive->title(),
                'status' => self::STATUS_INSTALLING,
                'manifest' => $manifest,
                'package_sha256' => $upload->sha256,
                'installed_by' => $user?->id,
            ]);

            $journal = [
                'install_id' => $install->id,
                'install_key' => $installKey,
                'package' => $manifest['package'],
                'version' => $manifest['version'],
                'previous_version' => $previousVersion,
                'status' => self::STATUS_INSTALLING,
                'started_at' => now()->toIso8601String(),
                'operations' => [],
                'created_directories' => [],
                'migrations' => [],
            ];
            $journalPath = $installDirectory . '/journal.json';
            $this->saveJournal($journalPath, $journal);

            try {
                $archive->extractTo($stagingDirectory);
                $this->note('Package extracted and checksums re-verified.');

                foreach ($archive->files() as $file) {
                    $this->applyFile($journal, $journalPath, $installDirectory, $file['path'], $stagingDirectory . '/' . PackageArchive::FILES_PREFIX . $file['path']);
                }

                foreach ($archive->deletes() as $path) {
                    $this->applyDelete($journal, $journalPath, $installDirectory, $path);
                }

                $this->writeVersionFile($journal, $journalPath, $installDirectory, $manifest['version']);

                foreach ($archive->migrations() as $migration) {
                    $this->applyMigration($journal, $journalPath, $installDirectory, $stagingDirectory, $migration);
                }

                $this->setPlatformVersion($manifest['version']);

                $journal['status'] = self::STATUS_INSTALLED;
                $journal['completed_at'] = now()->toIso8601String();
                $this->saveJournal($journalPath, $journal);

                $counts = $this->countOperations($journal);
                $this->note("Installed: {$counts['create']} added, {$counts['replace']} replaced, {$counts['delete']} deleted, " . count($journal['migrations']) . ' migration(s).');

                $install->update([
                    'status' => self::STATUS_INSTALLED,
                    'files_added' => $counts['create'],
                    'files_replaced' => $counts['replace'],
                    'files_deleted' => $counts['delete'],
                    'migrations_run' => count($journal['migrations']),
                    'installed_at' => now(),
                    'log' => implode("\n", $this->log),
                ]);
                $upload->update(['status' => 'installed']);
            } catch (Throwable $e) {
                $this->note('ERROR: ' . $e->getMessage());
                $this->note('Reverting the partial install…');

                $warnings = $this->revertJournal($journal, $journalPath);
                foreach ($warnings as $warning) {
                    $this->note('WARNING: ' . $warning);
                }
                $this->setPlatformVersion($previousVersion);

                $journal['status'] = self::STATUS_FAILED;
                $this->saveJournal($journalPath, $journal);

                $install->update([
                    'status' => self::STATUS_FAILED,
                    'error' => $e->getMessage(),
                    'log' => implode("\n", $this->log),
                ]);

                throw new PackageManagerException('Install failed and all changes were reverted: ' . $e->getMessage(), 0, $e);
            } finally {
                $this->deleteDirectory($stagingDirectory);
                $this->clearCaches();
            }

            return $install->fresh();
        });
    }

    /**
     * Rolls back an install. Any newer installs are rolled back first (newest first)
     * so files and the database always return to a consistent earlier state.
     *
     * @return array{rolled_back: array<int, string>, warnings: array<int, string>}
     */
    public function rollback(PackageInstall $target, ?User $user): array
    {
        $this->ensureSchema();

        if (!in_array($target->status, self::ROLLBACK_STATUSES, true)) {
            throw new PackageManagerException('Only installed, interrupted or rescued packages can be rolled back.');
        }

        return $this->withLock(function () use ($target, $user) {
            $installs = PackageInstall::whereIn('status', self::ROLLBACK_STATUSES)
                ->where('id', '>=', $target->id)
                ->orderByDesc('id')
                ->get();

            $rolledBack = [];
            $allWarnings = [];

            try {
                foreach ($installs as $install) {
                    $this->log = [];
                    $this->note("Rolling back v{$install->version} to v{$install->previous_version}.");

                    $journalPath = $this->journalPath($install);
                    $journal = $this->loadJournal($journalPath);
                    $warnings = $this->revertJournal($journal, $journalPath);

                    foreach ($warnings as $warning) {
                        $this->note('WARNING: ' . $warning);
                    }

                    $this->setPlatformVersion((string) $install->previous_version);

                    $journal['status'] = self::STATUS_ROLLED_BACK;
                    $journal['rolled_back_at'] = now()->toIso8601String();
                    $this->saveJournal($journalPath, $journal);

                    $this->note('Rollback complete.');
                    $install->update([
                        'status' => self::STATUS_ROLLED_BACK,
                        'rolled_back_by' => $user?->id,
                        'rolled_back_at' => now(),
                        'log' => trim($install->log . "\n\n" . implode("\n", $this->log)),
                    ]);
                    $install->upload?->update(['status' => 'uploaded']);

                    $rolledBack[] = $install->version;
                    $allWarnings = array_merge($allWarnings, $warnings);
                }
            } finally {
                $this->clearCaches();
            }

            return ['rolled_back' => $rolledBack, 'warnings' => $allWarnings];
        });
    }

    public function journalFor(PackageInstall $install): array
    {
        $path = $this->journalPath($install);

        return is_file($path) ? $this->loadJournal($path) : [];
    }

    /** Writes the backup files of an install into a ZIP for download. */
    public function backupArchive(PackageInstall $install): string
    {
        $directory = $this->dataDirectory . '/installs/' . $install->install_key;
        if (!is_dir($directory)) {
            throw new PackageManagerException('The backup for this install is no longer on disk.');
        }

        $zipPath = $this->directory('exports') . '/backup-' . $install->install_key . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new PackageManagerException('Could not create the backup ZIP.');
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($directory) + 1));
            }
        }
        $zip->close();

        return $zipPath;
    }

    private function register(string $storedName, string $originalName, string $source, ?User $user): PackageUpload
    {
        $path = $this->incomingDirectory() . '/' . $storedName;
        $attributes = [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'sha256' => hash_file('sha256', $path),
            'size' => filesize($path),
            'source' => $source,
            'uploaded_by' => $user?->id,
        ];

        try {
            $archive = PackageArchive::open($path);
            $attributes += [
                'version' => $archive->version(),
                'title' => $archive->title(),
                'manifest' => $archive->manifest(),
                'status' => 'uploaded',
            ];
        } catch (PackageManagerException $e) {
            $attributes += ['status' => 'invalid', 'validation_error' => $e->getMessage()];
        }

        return PackageUpload::create($attributes);
    }

    private function applyFile(array &$journal, string $journalPath, string $installDirectory, string $path, string $source): void
    {
        $target = $this->root . '/' . $path;
        $sha256After = hash_file('sha256', $source);

        if (is_file($target)) {
            $sha256Before = hash_file('sha256', $target);
            if (hash_equals($sha256Before, $sha256After)) {
                return;
            }

            $backup = 'backup/' . $path;
            $this->copyFile($target, $installDirectory . '/' . $backup);
            $journal['operations'][] = ['type' => 'replace', 'path' => $path, 'backup' => $backup, 'sha256_before' => $sha256Before, 'sha256_after' => $sha256After];
        } else {
            if (file_exists($target)) {
                throw new PackageManagerException("{$path} exists but is not a regular file.");
            }

            $this->trackDirectories($journal, dirname($target));
            $journal['operations'][] = ['type' => 'create', 'path' => $path, 'sha256_after' => $sha256After];
        }

        $this->saveJournal($journalPath, $journal);
        $this->writeFileAtomically($source, $target);
    }

    private function applyDelete(array &$journal, string $journalPath, string $installDirectory, string $path): void
    {
        $target = $this->root . '/' . $path;
        if (!is_file($target)) {
            return;
        }

        $backup = 'backup/' . $path;
        $this->copyFile($target, $installDirectory . '/' . $backup);
        $journal['operations'][] = ['type' => 'delete', 'path' => $path, 'backup' => $backup, 'sha256_before' => hash_file('sha256', $target)];
        $this->saveJournal($journalPath, $journal);

        if (!@unlink($target)) {
            throw new PackageManagerException("Could not delete {$path}.");
        }
        $this->invalidateOpcache($target);
    }

    private function writeVersionFile(array &$journal, string $journalPath, string $installDirectory, string $version): void
    {
        foreach ($journal['operations'] as $operation) {
            if ($operation['path'] === 'VERSION') {
                return;
            }
        }

        $source = $this->directory('installs/' . basename($installDirectory) . '/staging') . '/VERSION';
        file_put_contents($source, $version . "\n");
        $this->applyFile($journal, $journalPath, $installDirectory, 'VERSION', $source);
    }

    private function applyMigration(array &$journal, string $journalPath, string $installDirectory, string $stagingDirectory, array $migration): void
    {
        $kept = 'migrations/' . basename($migration['path']);
        $this->copyFile($stagingDirectory . '/' . $migration['path'], $installDirectory . '/' . $kept);

        $journal['migrations'][] = ['name' => $migration['name'], 'file' => $kept, 'status' => 'running'];
        $this->saveJournal($journalPath, $journal);

        $this->runMigration($installDirectory . '/' . $kept, 'up');

        $journal['migrations'][array_key_last($journal['migrations'])]['status'] = 'ran';
        $this->saveJournal($journalPath, $journal);
        $this->note("Migration {$migration['name']} applied.");
    }

    /** @return array<int, string> warnings */
    private function revertJournal(array &$journal, string $journalPath): array
    {
        $warnings = [];
        $installDirectory = dirname($journalPath);

        foreach (array_reverse(array_keys($journal['migrations'])) as $index) {
            $migration = $journal['migrations'][$index];
            if (!in_array($migration['status'], ['ran', 'running'], true)) {
                continue;
            }

            try {
                $this->runMigration($installDirectory . '/' . $migration['file'], 'down');
                $journal['migrations'][$index]['status'] = 'reverted';
                $this->note("Migration {$migration['name']} reverted.");
            } catch (Throwable $e) {
                $journal['migrations'][$index]['status'] = 'revert_failed';
                $warnings[] = "Migration {$migration['name']} down() failed: " . $e->getMessage();
            }
            $this->saveJournal($journalPath, $journal);
        }

        foreach (array_reverse(array_keys($journal['operations'])) as $index) {
            $operation = $journal['operations'][$index];
            if (!empty($operation['reverted'])) {
                continue;
            }

            $target = $this->root . '/' . $operation['path'];

            try {
                if (in_array($operation['type'], ['replace', 'create'], true) && is_file($target) && isset($operation['sha256_after'])
                    && !hash_equals($operation['sha256_after'], (string) hash_file('sha256', $target))) {
                    $warnings[] = "{$operation['path']} was changed after the install; it was restored anyway.";
                }

                if ($operation['type'] === 'create') {
                    if (is_file($target) && !@unlink($target)) {
                        throw new PackageManagerException('could not remove the added file');
                    }
                    $this->invalidateOpcache($target);
                } else {
                    $this->writeFileAtomically($installDirectory . '/' . $operation['backup'], $target);
                }

                $journal['operations'][$index]['reverted'] = true;
            } catch (Throwable $e) {
                $warnings[] = "Could not restore {$operation['path']}: " . $e->getMessage();
            }
        }

        foreach (array_reverse($journal['created_directories']) as $directory) {
            $path = $this->root . '/' . $directory;
            if (is_dir($path) && count(scandir($path) ?: []) === 2) {
                @rmdir($path);
            }
        }

        $this->saveJournal($journalPath, $journal);

        return $warnings;
    }

    private function runMigration(string $file, string $direction): void
    {
        $migration = (static fn (string $__file) => require $__file)($file);

        if (!is_object($migration)) {
            throw new PackageManagerException(basename($file) . ' must return a migration object (return new class extends Migration { … }).');
        }

        if (!method_exists($migration, $direction)) {
            if ($direction === 'down') {
                return;
            }
            throw new PackageManagerException(basename($file) . " has no {$direction}() method.");
        }

        $migration->{$direction}();
    }

    private function setPlatformVersion(string $version): void
    {
        if (!Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'platform_version'],
            [
                'group' => 'Identity',
                'label' => 'Platform Version',
                'value' => $version,
                'type' => 'text',
                'description' => 'Current ISO Admin Command Framework package version.',
                'sort_order' => 5,
                'is_core' => true,
                'updated_at' => now(),
            ]
        );
    }

    /**
     * After the rescue script restores files outside Laravel, mark those installs
     * so an admin can finish the rollback (database migrations) from the UI.
     */
    private function syncRescuedInstalls(): void
    {
        PackageInstall::whereIn('status', [self::STATUS_INSTALLED, self::STATUS_INSTALLING])->get()->each(function (PackageInstall $install) {
            $path = $this->journalPath($install);
            if (!is_file($path)) {
                return;
            }

            $journal = $this->loadJournal($path);
            if (($journal['status'] ?? null) === self::STATUS_RESCUED) {
                $install->update([
                    'status' => self::STATUS_RESCUED,
                    'log' => trim($install->log . "\n\nFiles restored by the rescue script at " . ($journal['rescued_at'] ?? 'unknown time') . '. Finish the rollback to revert database migrations.'),
                ]);
            }
        });
    }

    private function trackDirectories(array &$journal, string $directory): void
    {
        $missing = [];
        while (!is_dir($directory) && $directory !== $this->root && strlen($directory) > strlen($this->root)) {
            $missing[] = $directory;
            $directory = dirname($directory);
        }

        foreach (array_reverse($missing) as $path) {
            $journal['created_directories'][] = substr($path, strlen($this->root) + 1);
        }
    }

    private function writeFileAtomically(string $source, string $target): void
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new PackageManagerException("Could not create directory {$directory}.");
        }

        $temporary = $directory . '/.pkg-tmp-' . bin2hex(random_bytes(6));
        if (!@copy($source, $temporary)) {
            throw new PackageManagerException('Could not write ' . $this->relative($target) . '.');
        }

        @chmod($temporary, is_file($target) ? (fileperms($target) & 0777) : 0644);

        if (!@rename($temporary, $target)) {
            @unlink($temporary);
            throw new PackageManagerException('Could not replace ' . $this->relative($target) . '.');
        }

        $this->invalidateOpcache($target);
    }

    private function copyFile(string $source, string $target): void
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new PackageManagerException("Could not create backup directory {$directory}.");
        }

        if (!@copy($source, $target)) {
            throw new PackageManagerException('Could not back up ' . $this->relative($source) . '.');
        }
    }

    private function isWritableTarget(string $target): bool
    {
        if (file_exists($target)) {
            return is_writable($target) && is_writable(dirname($target));
        }

        $directory = dirname($target);
        while (!is_dir($directory) && strlen($directory) > strlen($this->root)) {
            $directory = dirname($directory);
        }

        return is_writable($directory);
    }

    private function countOperations(array $journal): array
    {
        $counts = ['create' => 0, 'replace' => 0, 'delete' => 0];
        foreach ($journal['operations'] as $operation) {
            if ($operation['path'] !== 'VERSION' || $operation['type'] === 'delete') {
                $counts[$operation['type']]++;
            }
        }

        return $counts;
    }

    private function clearCaches(): void
    {
        foreach (['view:clear', 'route:clear', 'config:clear', 'event:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (Throwable) {
                // A cache that cannot be cleared must not fail an install or rollback.
            }
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function invalidateOpcache(string $path): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    private function withLock(callable $callback): mixed
    {
        @set_time_limit(600);
        ignore_user_abort(true);

        $handle = fopen($this->directory() . '/.lock', 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            throw new PackageManagerException('Another package install or rollback is already running. Try again in a minute.');
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function journalPath(PackageInstall $install): string
    {
        return $this->dataDirectory . '/installs/' . $install->install_key . '/journal.json';
    }

    private function loadJournal(string $path): array
    {
        $journal = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!is_array($journal)) {
            throw new PackageManagerException('The install journal is missing or unreadable, so this install cannot be rolled back automatically.');
        }

        return $journal + ['operations' => [], 'created_directories' => [], 'migrations' => []];
    }

    private function saveJournal(string $path, array $journal): void
    {
        $temporary = $path . '.tmp';
        if (file_put_contents($temporary, json_encode($journal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false || !rename($temporary, $path)) {
            throw new PackageManagerException('Could not write the install journal.');
        }
    }

    private function uploadPath(PackageUpload $upload): string
    {
        return $this->incomingDirectory() . '/' . $upload->stored_name;
    }

    private function directory(string $sub = ''): string
    {
        $path = $this->dataDirectory . ($sub !== '' ? '/' . $sub : '');
        if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
            throw new PackageManagerException("Could not create {$path}. Check that storage/ is writable.");
        }

        // The app root is inside public_html on this host, so block direct web access to backups.
        if (!is_file($this->dataDirectory . '/.htaccess')) {
            @file_put_contents($this->dataDirectory . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n");
            @file_put_contents($this->dataDirectory . '/index.html', '');
        }

        return $path;
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }

    private function relative(string $path): string
    {
        return str_starts_with($path, $this->root . '/') ? substr($path, strlen($this->root) + 1) : $path;
    }

    private function note(string $message): void
    {
        $this->log[] = '[' . now()->format('Y-m-d H:i:s') . '] ' . $message;
    }

    private function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));
        if ($value === '') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
