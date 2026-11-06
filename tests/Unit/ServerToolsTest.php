<?php

namespace Tests\Unit;

use App\Support\EnvFile;
use App\Updates\Server;
use Tests\Support\FakeServer;
use Tests\TestCase;

/**
 * installer/stubs/tools.php.stub, and the front door, cron.php and
 * recovery.php that rely on it when ISO Command Centre itself can't run.
 */
class ServerToolsTest extends TestCase
{
    private FakeServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        Server::loadTools();
        $this->server = new FakeServer;
    }

    protected function tearDown(): void
    {
        $this->server->destroy();
        parent::tearDown();
    }

    private function startUpdate(string $from, string $to, array $extra = []): void
    {
        iso_write_php($this->server->home.'/update-pending.php', $extra + [
            'from' => $from, 'to' => $to, 'token' => 'secret', 'backup' => null, 'migrated' => false, 'until' => time() + 900,
        ]);
        iso_switch($this->server->home, $to);
        file_put_contents($this->server->home.'/shared/storage/framework/down', '{}');
    }

    public function test_env_values_written_by_the_installer_read_back_exactly(): void
    {
        $values = ['DB_PASSWORD' => 'p@ss"w$rd\'#x\\', 'DB_HOST' => 'localhost', 'MAIL_PASSWORD' => 'with ${HOME}'];
        (new EnvFile($this->server->home.'/shared/.env'))->set($values);

        $this->assertEquals($values + ['APP_KEY' => 'test'], iso_env($this->server->home));
    }

    public function test_releases_are_listed_newest_first_and_only_real_ones_can_be_switched_to(): void
    {
        foreach (['v0.2.0', 'v0.10.0', 'v0.9.1'] as $version) {
            $this->server->addRelease($version);
        }
        mkdir($this->server->home.'/releases/v9.9.9'); // half unpacked: no bootstrap/app.php

        $this->assertSame(['v0.10.0', 'v0.9.1', 'v0.2.0'], iso_installed_releases($this->server->home));

        iso_switch($this->server->home, 'v0.9.1');
        $this->assertSame('v0.9.1', $this->server->current());

        $this->expectExceptionMessage('not installed');
        iso_switch($this->server->home, 'v9.9.9');
    }

    public function test_reverting_an_update_switches_back_and_records_why(): void
    {
        $this->server->install('v0.2.0');
        $this->server->addRelease('v0.3.0');
        $this->startUpdate('v0.2.0', 'v0.3.0');

        iso_revert_pending($this->server->home, 'It broke.', false);

        $this->assertSame('v0.2.0', $this->server->current());
        $this->assertNull($this->server->pending());
        $this->assertFileDoesNotExist($this->server->home.'/shared/storage/framework/down');
        $failed = iso_read_php($this->server->home.'/update-failed.php');
        $this->assertSame(['v0.3.0', 'It broke.', false], [$failed['version'], $failed['reason'], $failed['database_restored']]);
    }

    public function test_the_front_door_switches_back_when_the_new_version_cannot_start(): void
    {
        $this->server->install('v0.2.0');
        $this->server->addRelease('v0.3.0', overrides: ['bootstrap/app.php' => '<?php throw new RuntimeException("boom");']);
        $this->startUpdate('v0.2.0', 'v0.3.0');

        $this->server->run($this->server->web.'/index.php');

        $this->assertSame('v0.2.0', $this->server->current());
        $this->assertStringContainsString('boom', iso_read_php($this->server->home.'/update-failed.php')['reason']);
    }

    public function test_the_front_door_switches_back_when_the_new_version_has_a_syntax_error(): void
    {
        $this->server->install('v0.2.0');
        $this->server->addRelease('v0.3.0', overrides: ['bootstrap/app.php' => '<?php this is not php']);
        $this->startUpdate('v0.2.0', 'v0.3.0');

        $this->server->run($this->server->web.'/index.php');

        $this->assertSame('v0.2.0', $this->server->current());
    }

    public function test_the_front_door_switches_back_when_the_new_version_crashes_fatally(): void
    {
        $this->server->install('v0.2.0');
        // Redeclaring a function is a fatal error that try/catch can't catch.
        $this->server->addRelease('v0.3.0', overrides: ['bootstrap/app.php' => '<?php function iso_switch() {}']);
        $this->startUpdate('v0.2.0', 'v0.3.0');

        $this->server->run($this->server->web.'/index.php');

        $this->assertSame('v0.2.0', $this->server->current());
        $this->assertStringContainsString('crashed', iso_read_php($this->server->home.'/update-failed.php')['reason']);
    }

    public function test_the_front_door_leaves_a_running_version_alone_when_no_update_is_pending(): void
    {
        $this->server->install('v0.2.0');
        $this->server->addRelease('v0.3.0', overrides: ['bootstrap/app.php' => '<?php throw new RuntimeException("boom");']);
        iso_switch($this->server->home, 'v0.3.0');

        $this->server->run($this->server->web.'/index.php');

        $this->assertSame('v0.3.0', $this->server->current());
    }

    public function test_cron_undoes_an_update_that_was_never_finished(): void
    {
        $bootstrap = '<?php return new class { public function handleCommand($input) { return 0; } };';
        $autoload = '<?php require '.var_export(base_path('vendor/autoload.php'), true).';';
        $this->server->install('v0.2.0');
        foreach (['bootstrap/app.php' => $bootstrap, 'vendor/autoload.php' => $autoload] as $path => $contents) {
            file_put_contents($this->server->home."/releases/v0.2.0/$path", $contents);
        }
        $this->server->addRelease('v0.3.0', overrides: ['bootstrap/app.php' => $bootstrap, 'vendor/autoload.php' => $autoload]);

        $this->startUpdate('v0.2.0', 'v0.3.0', ['until' => time() + 900]);
        $this->assertSame(0, $this->server->run($this->server->home.'/cron.php')['status']);
        $this->assertSame('v0.3.0', $this->server->current(), 'Still inside the update window.');

        iso_write_php($this->server->home.'/update-pending.php', ['until' => time() - 1] + $this->server->pending());
        $result = $this->server->run($this->server->home.'/cron.php');

        $this->assertSame(0, $result['status'], $result['output']);
        $this->assertSame('v0.2.0', $this->server->current());
        $this->assertNull($this->server->pending());
    }

    public function test_recovery_page_switches_versions_with_the_right_key_only(): void
    {
        $this->server->install('v0.2.0');
        $this->server->addRelease('v0.3.0');
        $this->startUpdate('v0.2.0', 'v0.3.0');
        $page = $this->server->web.'/recovery.php';

        $this->assertStringContainsString("isn't set up yet", $this->server->run($page)['output']);

        iso_write_php($this->server->home.'/recovery-key.php', ['hash' => password_hash('ABCDE-FGHIJ', PASSWORD_DEFAULT)]);
        $this->assertStringContainsString('not correct', $this->server->run($page, ['key' => 'WRONG', 'version' => 'v0.2.0'])['output']);
        $this->assertSame('v0.3.0', $this->server->current());

        $output = $this->server->run($page, ['key' => ' abcde-fghij ', 'version' => 'v0.2.0'])['output'];

        $this->assertStringContainsString('ISO Command Centre now runs v0.2.0', $output);
        $this->assertSame('v0.2.0', $this->server->current());
        $this->assertNull($this->server->pending());
        $this->assertFileDoesNotExist($this->server->home.'/shared/storage/framework/down');
    }

    public function test_recovery_page_locks_after_five_wrong_keys(): void
    {
        $this->server->install('v0.2.0');
        $this->server->addRelease('v0.3.0');
        iso_write_php($this->server->home.'/recovery-key.php', ['hash' => password_hash('RIGHT', PASSWORD_DEFAULT)]);
        $page = $this->server->web.'/recovery.php';

        for ($i = 0; $i < 5; $i++) {
            $this->server->run($page, ['key' => 'WRONG', 'version' => 'v0.3.0']);
        }
        $output = $this->server->run($page, ['key' => 'RIGHT', 'version' => 'v0.3.0'])['output'];

        $this->assertStringContainsString('Too many wrong keys', $output);
        $this->assertSame('v0.2.0', $this->server->current());
    }

    public function test_state_files_are_read_fresh_every_time(): void
    {
        $file = $this->server->home.'/current.php';
        iso_write_php($file, 'v0.2.0');
        $this->assertSame('v0.2.0', iso_read_php($file));

        // Replaced behind PHP's back, the way a cached copy would go stale.
        file_put_contents($file, "<?php return 'v0.3.0';\n");
        $this->assertSame('v0.3.0', iso_read_php($file));
        $this->assertSame(['a' => [1, true]], (function () use ($file) {
            iso_write_php($file, ['a' => [1, true]]);

            return iso_read_php($file);
        })());
    }
}
