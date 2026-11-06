<?php

namespace Tests\Feature\Updates;

use App\Updates\DatabaseBackup;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Backup and restore need a real MySQL database. CI provides one; locally,
 * set ISO_TEST_MYSQL_DATABASE, _USERNAME, _PASSWORD (and _HOST) to run these.
 */
class DatabaseBackupTest extends TestCase
{
    private Connection $db;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        if (! getenv('ISO_TEST_MYSQL_DATABASE')) {
            $this->markTestSkipped('Set ISO_TEST_MYSQL_DATABASE to run the MySQL backup tests.');
        }

        config(['database.connections.backup_test' => [
            'driver' => 'mysql',
            'host' => getenv('ISO_TEST_MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('ISO_TEST_MYSQL_PORT') ?: 3306,
            'database' => getenv('ISO_TEST_MYSQL_DATABASE'),
            'username' => getenv('ISO_TEST_MYSQL_USERNAME'),
            'password' => getenv('ISO_TEST_MYSQL_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]]);
        $this->db = DB::connection('backup_test');
        $this->dropAllTables();

        $this->directory = sys_get_temp_dir().'/iso-backups-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->dropAllTables();
            exec('rm -rf '.escapeshellarg($this->directory));
        }
        parent::tearDown();
    }

    private function dropAllTables(): void
    {
        $this->db->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->db->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) {
            $this->db->statement('DROP TABLE `'.array_values((array) $row)[0].'`');
        }
        $this->db->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshot(): array
    {
        $tables = [];
        foreach ($this->db->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) {
            $table = array_values((array) $row)[0];
            $tables[$table] = array_map(fn ($r) => (array) $r, $this->db->select("SELECT * FROM `$table` ORDER BY id"));
        }
        ksort($tables);

        return $tables;
    }

    public function test_a_restore_brings_back_exactly_what_was_backed_up(): void
    {
        $this->db->statement('CREATE TABLE users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(100), note TEXT NULL, score DECIMAL(8,2), created_at TIMESTAMP NULL)');
        $this->db->statement('CREATE TABLE posts (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT, body TEXT, FOREIGN KEY (user_id) REFERENCES users(id))');
        $this->db->table('users')->insert([
            ['name' => "O'Brien \"quoted\" \\ back", 'note' => "line one\nline two\r\n\ttab -- iso:end", 'score' => 12.5, 'created_at' => '2026-10-30 08:00:00'],
            ['name' => 'Zoë 🚀 ünïcødé', 'note' => null, 'score' => -1, 'created_at' => null],
        ]);
        for ($i = 0; $i < 450; $i++) { // more than one INSERT batch
            $this->db->table('posts')->insert(['user_id' => 1 + $i % 2, 'body' => "post $i; DROP TABLE users; --"]);
        }
        $before = $this->snapshot();

        $backups = new DatabaseBackup($this->db, $this->directory);
        $file = $backups->create('v0.1.0-before-v0.2.0');
        $this->assertFileExists($file);
        $this->assertStringEndsWith('-v0.1.0-before-v0.2.0.sql.gz', $file);

        // A newer version changes data and adds a table...
        $this->db->table('users')->where('id', 1)->update(['name' => 'changed']);
        $this->db->table('posts')->delete();
        $this->db->statement('CREATE TABLE added_later (id INT PRIMARY KEY)');

        $backups->restore($file);

        // ...and restoring puts everything back, without the new table.
        $this->assertSame($before, $this->snapshot());
    }

    public function test_old_backups_are_pruned_but_protected_ones_are_kept(): void
    {
        mkdir($this->directory);
        foreach (['20260101-000000-a', '20260102-000000-b', '20260103-000000-c', '20260104-000000-d'] as $name) {
            touch("{$this->directory}/$name.sql.gz");
        }

        (new DatabaseBackup($this->db, $this->directory))->prune(2, ["{$this->directory}/20260101-000000-a.sql.gz"]);

        $left = array_map('basename', glob($this->directory.'/*.sql.gz'));
        $this->assertSame(['20260101-000000-a.sql.gz', '20260103-000000-c.sql.gz', '20260104-000000-d.sql.gz'], $left);
    }
}
