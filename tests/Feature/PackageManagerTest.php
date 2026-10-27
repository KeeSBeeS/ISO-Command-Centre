<?php

namespace Tests\Feature;

use App\Models\PackageInstall;
use App\Models\PackageUpload;
use App\Services\PackageArchive;
use App\Services\PackageManager;
use App\Services\PackageManagerException;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

class PackageManagerTest extends TestCase
{
    private string $sandbox;
    private PackageManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);

        $this->sandbox = sys_get_temp_dir() . '/isopkg-test-' . bin2hex(random_bytes(4));
        mkdir($this->sandbox . '/app', 0755, true);
        file_put_contents($this->sandbox . '/VERSION', "2.8.5\n");
        file_put_contents($this->sandbox . '/app/Existing.php', 'original');
        file_put_contents($this->sandbox . '/app/Obsolete.php', 'obsolete');

        $this->manager = new PackageManager($this->sandbox, $this->sandbox . '/storage/app/package-manager');
        $this->manager->ensureSchema();
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->sandbox));
        parent::tearDown();
    }

    public function test_install_and_rollback_restore_files_database_and_version(): void
    {
        $upload = $this->upload('2.9.0', [
            'app/Existing.php' => 'updated',
            'app/New/Added.php' => 'added',
        ], ['app/Obsolete.php'], [
            '2026_10_27_000001_create_widgets' => $this->createTableMigration('pkg_widgets'),
        ]);

        $install = $this->manager->install($upload, null);

        $this->assertSame(PackageManager::STATUS_INSTALLED, $install->status);
        $this->assertSame('updated', file_get_contents($this->sandbox . '/app/Existing.php'));
        $this->assertSame('added', file_get_contents($this->sandbox . '/app/New/Added.php'));
        $this->assertFileDoesNotExist($this->sandbox . '/app/Obsolete.php');
        $this->assertSame('2.9.0', $this->manager->currentVersion());
        $this->assertTrue(Schema::hasTable('pkg_widgets'));
        $this->assertSame([1, 1, 1, 1], [$install->files_added, $install->files_replaced, $install->files_deleted, $install->migrations_run]);

        $result = $this->manager->rollback($install, null);

        $this->assertSame(['2.9.0'], $result['rolled_back']);
        $this->assertSame('original', file_get_contents($this->sandbox . '/app/Existing.php'));
        $this->assertSame('obsolete', file_get_contents($this->sandbox . '/app/Obsolete.php'));
        $this->assertFileDoesNotExist($this->sandbox . '/app/New/Added.php');
        $this->assertDirectoryDoesNotExist($this->sandbox . '/app/New');
        $this->assertSame('2.8.5', $this->manager->currentVersion());
        $this->assertFalse(Schema::hasTable('pkg_widgets'));
        $this->assertSame(PackageManager::STATUS_ROLLED_BACK, $install->fresh()->status);
    }

    public function test_failed_migration_reverts_the_whole_install(): void
    {
        $upload = $this->upload('2.9.0', ['app/Existing.php' => 'updated'], [], [
            '2026_10_27_000001_create_widgets' => $this->createTableMigration('pkg_widgets'),
            '2026_10_27_000002_broken' => "<?php return new class { public function up(): void { throw new RuntimeException('boom'); } };",
        ]);

        try {
            $this->manager->install($upload, null);
            $this->fail('Install should have failed.');
        } catch (PackageManagerException $e) {
            $this->assertStringContainsString('boom', $e->getMessage());
        }

        $this->assertSame('original', file_get_contents($this->sandbox . '/app/Existing.php'));
        $this->assertSame('2.8.5', $this->manager->currentVersion());
        $this->assertFalse(Schema::hasTable('pkg_widgets'));
        $this->assertSame(PackageManager::STATUS_FAILED, PackageInstall::first()->status);
    }

    public function test_rolling_back_an_older_install_rolls_back_newer_ones_first(): void
    {
        $first = $this->manager->install($this->upload('2.9.0', ['app/Existing.php' => 'v290']), null);
        $this->manager->install($this->upload('2.9.1', ['app/Existing.php' => 'v291']), null);

        $result = $this->manager->rollback($first, null);

        $this->assertSame(['2.9.1', '2.9.0'], $result['rolled_back']);
        $this->assertSame('original', file_get_contents($this->sandbox . '/app/Existing.php'));
        $this->assertSame('2.8.5', $this->manager->currentVersion());
    }

    public function test_tampered_and_incompatible_packages_cannot_be_installed(): void
    {
        $tampered = $this->upload('2.9.0', ['app/Existing.php' => 'updated'], [], [], function (ZipArchive $zip) {
            $zip->addFromString('files/app/Existing.php', 'malicious');
        });
        $this->assertFalse($this->manager->inspect($tampered)['can_install']);

        $older = $this->upload('2.8.0', ['app/Existing.php' => 'older']);
        $this->assertFalse($this->manager->inspect($older)['can_install']);

        $this->expectException(PackageManagerException::class);
        $this->manager->install($tampered, null);
    }

    public function test_protected_and_traversal_paths_are_rejected(): void
    {
        foreach (['.env', '../outside.php', 'storage/app/x.php', '/etc/passwd'] as $path) {
            try {
                PackageArchive::normalisePath($path);
                $this->fail("{$path} should be rejected.");
            } catch (PackageManagerException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function upload(string $version, array $files, array $deletes = [], array $migrations = [], ?callable $tamper = null): PackageUpload
    {
        $manifest = [
            'format' => 1,
            'package' => 'iso-command-centre',
            'version' => $version,
            'title' => "Test {$version}",
            'requires' => ['min_version' => '2.8.0'],
            'files' => [],
            'delete' => $deletes,
            'migrations' => [],
        ];

        $path = $this->manager->incomingDirectory() . "/test-{$version}-" . bin2hex(random_bytes(3)) . '.isopkg';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);

        foreach ($files as $file => $contents) {
            $zip->addFromString('files/' . $file, $contents);
            $manifest['files'][] = ['path' => $file, 'sha256' => hash('sha256', $contents), 'size' => strlen($contents)];
        }
        foreach ($migrations as $name => $contents) {
            $zip->addFromString("migrations/{$name}.php", $contents);
            $manifest['migrations'][] = ['name' => $name, 'path' => "migrations/{$name}.php", 'sha256' => hash('sha256', $contents)];
        }

        $zip->addFromString('manifest.json', json_encode($manifest));
        if ($tamper) {
            $tamper($zip);
        }
        $zip->close();

        $this->manager->scanIncoming(null);

        return PackageUpload::where('stored_name', basename($path))->firstOrFail();
    }

    private function createTableMigration(string $table): string
    {
        return <<<PHP
        <?php
        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration {
            public function up(): void { Schema::create('{$table}', function (Blueprint \$table) { \$table->id(); }); }
            public function down(): void { Schema::dropIfExists('{$table}'); }
        };
        PHP;
    }
}
