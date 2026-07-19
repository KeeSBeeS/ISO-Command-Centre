<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class PlatformUpdateService
{
    /**
     * Top-level paths that a web-applied update package may never overwrite.
     * Protects live credentials, uploaded documents and the repository itself.
     */
    private const PROTECTED_PATHS = ['.git', 'storage', 'node_modules'];

    /**
     * Top-level paths excluded from pre-update code backups.
     * vendor is excluded to keep backup ZIPs small enough for shared hosting;
     * it is restored from the previous deployment package if ever needed.
     */
    private const BACKUP_EXCLUDED_PATHS = ['.git', 'storage', 'node_modules', 'vendor'];

    private const PACKAGE_MARKER_DIRS = ['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'vendor'];
    private const PACKAGE_MARKER_FILES = ['artisan', 'composer.json', 'index.php', 'VERSION'];

    public function packagesPath(): string
    {
        return $this->ensureDirectory(storage_path('app/updates/packages'));
    }

    public function backupsPath(): string
    {
        return $this->ensureDirectory(storage_path('app/updates/backups'));
    }

    public function listPackages(): array
    {
        return $this->listZipFiles($this->packagesPath());
    }

    public function listBackups(): array
    {
        return $this->listZipFiles($this->backupsPath());
    }

    public function storeUploadedPackage(UploadedFile $file): string
    {
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $filename = 'upload-' . now()->format('Ymd-His') . '-' . (Str::slug($original) ?: 'package') . '.zip';

        $file->move($this->packagesPath(), $filename);

        $this->assertValidZip($this->packagesPath() . DIRECTORY_SEPARATOR . $filename);

        return $filename;
    }

    public function downloadFromGithub(string $repository, string $branch, ?string $token = null): string
    {
        if (!preg_match('#^[A-Za-z0-9_.\-]+/[A-Za-z0-9_.\-]+$#', $repository)) {
            throw new RuntimeException('GitHub repository must be in owner/repository format.');
        }

        $branch = trim($branch) !== '' ? trim($branch) : 'main';
        $encodedBranch = str_replace('%2F', '/', rawurlencode($branch));
        $url = 'https://api.github.com/repos/' . $repository . '/zipball/' . $encodedBranch;

        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'ISO-Admin-Update-Manager',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $filename = 'github-' . Str::slug(str_replace('/', '-', $repository)) . '-' . Str::slug($branch) . '-' . now()->format('Ymd-His') . '.zip';
        $target = $this->packagesPath() . DIRECTORY_SEPARATOR . $filename;

        try {
            $response = Http::withHeaders($headers)
                ->connectTimeout(20)
                ->timeout(300)
                ->withOptions(['sink' => $target])
                ->get($url);
        } catch (\Throwable $exception) {
            @unlink($target);
            throw new RuntimeException('GitHub download failed: ' . $exception->getMessage());
        }

        if (!$response->successful()) {
            @unlink($target);
            $hint = $response->status() === 404
                ? ' Check the repository name and branch, and supply a token for private repositories.'
                : ($response->status() === 401 ? ' The GitHub token was rejected.' : '');
            throw new RuntimeException('GitHub responded with HTTP ' . $response->status() . '.' . $hint);
        }

        try {
            $this->assertValidZip($target);
        } catch (RuntimeException $exception) {
            @unlink($target);
            throw $exception;
        }

        return $filename;
    }

    public function createBackup(): string
    {
        $version = $this->currentFileVersion() ?? 'unknown';
        $filename = 'backup-v' . Str::slug($version) . '-' . now()->format('Ymd-His') . '.zip';
        $target = $this->backupsPath() . DIRECTORY_SEPARATOR . $filename;

        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup ZIP file.');
        }

        $base = base_path();
        $excluded = self::BACKUP_EXCLUDED_PATHS;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS),
                function ($current) use ($base, $excluded) {
                    $relative = ltrim(str_replace('\\', '/', substr($current->getPathname(), strlen($base))), '/');
                    $firstSegment = explode('/', $relative)[0];

                    return !in_array($firstSegment, $excluded, true);
                }
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($base))), '/');
            $zip->addFile($file->getPathname(), $relative);
        }

        $zip->close();

        return $filename;
    }

    /**
     * Extracts a stored package and copies its files over the installation.
     * Returns a summary: files copied, source root used, backup file (if any)
     * and the package VERSION (if the package ships one).
     */
    public function applyPackage(string $filename, bool $backupFirst = true): array
    {
        $zipPath = $this->packagesPath() . DIRECTORY_SEPARATOR . $this->safeZipName($filename);

        if (!is_file($zipPath)) {
            throw new RuntimeException('Update package not found: ' . $filename);
        }

        @set_time_limit(0);
        @ignore_user_abort(true);

        $backupFile = null;
        if ($backupFirst) {
            $backupFile = $this->createBackup();
        }

        $tempPath = $this->extractToTemp($zipPath);

        try {
            $sourceRoot = $this->detectSourceRoot($tempPath);
            $copied = $this->copyTree($sourceRoot, base_path());
            $packageVersion = is_file($sourceRoot . DIRECTORY_SEPARATOR . 'VERSION')
                ? trim((string) file_get_contents($sourceRoot . DIRECTORY_SEPARATOR . 'VERSION'))
                : null;
        } finally {
            $this->deleteDirectory($tempPath);
        }

        $this->clearCompiledCaches();

        return [
            'files_copied' => $copied,
            'backup_file' => $backupFile,
            'package_version' => $packageVersion,
        ];
    }

    public function deletePackage(string $filename): void
    {
        $path = $this->packagesPath() . DIRECTORY_SEPARATOR . $this->safeZipName($filename);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function deleteBackup(string $filename): void
    {
        $path = $this->backupsPath() . DIRECTORY_SEPARATOR . $this->safeZipName($filename);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function backupFilePath(string $filename): string
    {
        $path = $this->backupsPath() . DIRECTORY_SEPARATOR . $this->safeZipName($filename);

        if (!is_file($path)) {
            throw new RuntimeException('Backup file not found: ' . $filename);
        }

        return $path;
    }

    public function currentFileVersion(): ?string
    {
        $versionFile = base_path('VERSION');

        return is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : null;
    }

    public function zipExtensionAvailable(): bool
    {
        return class_exists(ZipArchive::class);
    }

    private function safeZipName(string $filename): string
    {
        $filename = basename($filename);

        if (!preg_match('/^[A-Za-z0-9._\-]+\.zip$/', $filename)) {
            throw new RuntimeException('Invalid update file name.');
        }

        return $filename;
    }

    private function assertValidZip(string $path): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The file is not a valid ZIP archive.');
        }
        $zip->close();
    }

    private function extractToTemp(string $zipPath): string
    {
        $tempPath = $this->ensureDirectory(storage_path('app/updates/tmp')) . DIRECTORY_SEPARATOR . uniqid('extract-', true);
        $this->ensureDirectory($tempPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open the update ZIP archive.');
        }

        // Guard against zip-slip: reject entries that escape the extraction root.
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = str_replace('\\', '/', (string) $zip->getNameIndex($index));
            if ($entry === '' || str_starts_with($entry, '/') || str_contains($entry, "\0")) {
                $zip->close();
                throw new RuntimeException('The ZIP archive contains an unsafe path and was rejected.');
            }
            foreach (explode('/', $entry) as $segment) {
                if ($segment === '..') {
                    $zip->close();
                    throw new RuntimeException('The ZIP archive contains an unsafe path and was rejected.');
                }
            }
        }

        if (!$zip->extractTo($tempPath)) {
            $zip->close();
            $this->deleteDirectory($tempPath);
            throw new RuntimeException('Could not extract the update ZIP archive.');
        }

        $zip->close();

        return $tempPath;
    }

    private function detectSourceRoot(string $tempPath): string
    {
        if ($this->looksLikePlatformPackage($tempPath)) {
            return $tempPath;
        }

        // GitHub archives wrap everything in a single owner-repo-hash folder.
        $entries = array_values(array_diff(scandir($tempPath) ?: [], ['.', '..']));
        if (count($entries) === 1 && is_dir($tempPath . DIRECTORY_SEPARATOR . $entries[0])) {
            $inner = $tempPath . DIRECTORY_SEPARATOR . $entries[0];
            if ($this->looksLikePlatformPackage($inner)) {
                return $inner;
            }
        }

        throw new RuntimeException('The ZIP does not look like an ISO Admin update package. Expected folders such as app/, resources/ or routes/ at the package root.');
    }

    private function looksLikePlatformPackage(string $path): bool
    {
        foreach (self::PACKAGE_MARKER_DIRS as $dir) {
            if (is_dir($path . DIRECTORY_SEPARATOR . $dir)) {
                return true;
            }
        }

        foreach (self::PACKAGE_MARKER_FILES as $file) {
            if (is_file($path . DIRECTORY_SEPARATOR . $file)) {
                return true;
            }
        }

        return false;
    }

    private function copyTree(string $sourceRoot, string $targetRoot): int
    {
        $copied = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($sourceRoot))), '/');
            $firstSegment = explode('/', $relative)[0];

            if (in_array($firstSegment, self::PROTECTED_PATHS, true)) {
                continue;
            }
            if (str_starts_with($relative, '.env')) {
                continue;
            }
            if ($relative === 'database/database.sqlite') {
                continue;
            }

            $target = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $targetDir = dirname($target);

            if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new RuntimeException('Could not create directory: ' . $targetDir);
            }

            if (!@copy($file->getPathname(), $target)) {
                throw new RuntimeException('Could not overwrite file: ' . $relative . '. Check file permissions on the server.');
            }

            $copied++;
        }

        return $copied;
    }

    private function clearCompiledCaches(): void
    {
        foreach (glob(storage_path('framework/views/*.php')) ?: [] as $file) {
            @unlink($file);
        }

        foreach (glob(base_path('bootstrap/cache/routes*.php')) ?: [] as $file) {
            @unlink($file);
        }

        foreach (['config.php', 'events.php', 'packages.php', 'services.php'] as $file) {
            @unlink(base_path('bootstrap/cache/' . $file));
        }
    }

    private function listZipFiles(string $directory): array
    {
        $files = [];

        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.zip') ?: [] as $path) {
            $files[] = [
                'name' => basename($path),
                'size_bytes' => filesize($path) ?: 0,
                'modified_at' => date('Y-m-d H:i', filemtime($path) ?: time()),
            ];
        }

        usort($files, fn (array $a, array $b) => strcmp($b['name'], $a['name']));

        return $files;
    }

    private function ensureDirectory(string $path): string
    {
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        return $path;
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
