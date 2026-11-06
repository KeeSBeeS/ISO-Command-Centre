<?php

namespace Tests\Unit;

use App\Updates\Server;
use Tests\Support\FakeServer;
use Tests\TestCase;

/**
 * installer/iso-setup.php, run the way the person installing runs it: copied
 * into the web folder next to a release zip and opened in the browser.
 */
class SetupTest extends TestCase
{
    private FakeServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        Server::loadTools();
        $this->server = new FakeServer;
        // The private folder doesn't exist yet on a site being moved.
        exec('rm -rf '.escapeshellarg($this->server->home));
    }

    protected function tearDown(): void
    {
        $this->server->destroy();
        parent::tearDown();
    }

    /**
     * A v2.8.x site: Laravel directly in the web folder, served through /public.
     */
    private function legacySite(string $version = '2.8.5'): void
    {
        $files = [
            'artisan' => '<?php',
            'VERSION' => $version."\n",
            '.env' => "APP_KEY=base64:legacy\nAPP_URL=https://isoadmin.co.za/public\nDB_DATABASE=iso\n",
            '.htaccess' => 'RedirectMatch 301 ^/$ https://isoadmin.co.za/public',
            'app/Models/User.php' => '<?php',
            'bootstrap/app.php' => '<?php return null;',
            'bootstrap/cache/config.php' => "<?php return ['base' => '/old/path'];",
            'bootstrap/cache/packages.php' => '<?php return [];',
            'vendor/autoload.php' => '<?php',
            'public/index.php' => '<?php // old',
            'storage/app/isoadmin_installed.lock' => '2025-01-01',
            'storage/app/private/employee-documents/medical.enc' => 'encrypted',
            'storage/logs/laravel.log' => 'old log',
            'README_V2_8_5_EMPLOYEE_ATTENDANCE_HISTORY.md' => '# notes',
            'cgi-bin/.htaccess' => 'Deny from all',
        ];
        foreach ($files as $path => $contents) {
            $target = $this->server->web.'/'.$path;
            is_dir(dirname($target)) || mkdir(dirname($target), 0755, true);
            file_put_contents($target, $contents);
        }
    }

    private function uploadRelease(string $version = 'v3.0.0'): void
    {
        file_put_contents($this->server->web."/iso-command-centre-$version.zip", $this->server->zip($version));
        copy(base_path('installer/iso-setup.php'), $this->server->web.'/iso-setup.php');
    }

    private function openSetup(array $post = []): string
    {
        return $this->server->run($this->server->web.'/iso-setup.php', $post)['output'];
    }

    public function test_an_old_site_is_detected_and_offered_the_move(): void
    {
        $this->legacySite();
        $this->uploadRelease();

        $page = $this->openSetup();

        $this->assertStringContainsString('v2.8.5 is installed directly in this web folder', $page);
        $this->assertStringContainsString('Move and upgrade', $page);
    }

    public function test_moving_an_old_site_keeps_its_settings_and_files_and_leaves_an_update_to_finish(): void
    {
        $this->legacySite();
        $this->uploadRelease();
        $home = $this->server->home;
        $web = $this->server->web;

        $page = $this->openSetup(['home' => $home]);

        $this->assertStringContainsString('Log in and finish the update', $page, $page);

        // The old version is now an installed release the front door can switch back to.
        $this->assertSame(['v3.0.0', 'v2.8.5'], iso_installed_releases($home));
        $this->assertFileExists("$home/releases/v2.8.5/artisan");
        $this->assertFileExists("$home/releases/v2.8.5/app/Models/User.php");
        $this->assertFileDoesNotExist("$home/releases/v2.8.5/bootstrap/cache/config.php", 'Cached paths point at the old folder.');
        $this->assertFileExists("$home/releases/v2.8.5/bootstrap/cache/packages.php");
        $this->assertStringContainsString("APP_URL=https://isoadmin.co.za\n", file_get_contents("$home/releases/v2.8.5/.env"));

        // Settings and uploaded documents are shared; the old version still sees them.
        $this->assertStringContainsString("APP_KEY=base64:legacy\nAPP_URL=https://isoadmin.co.za\n", file_get_contents("$home/shared/.env"));
        $this->assertSame('encrypted', file_get_contents("$home/shared/storage/app/private/employee-documents/medical.enc"));
        $this->assertFileExists("$home/shared/storage/app/isoadmin_installed.lock");
        $this->assertSame('encrypted', file_get_contents("$home/releases/v2.8.5/storage/app/private/employee-documents/medical.enc"));

        // Nothing of the old site is left in the web folder, except what isn't ours.
        foreach (['artisan', '.env', 'app', 'vendor', 'storage', 'bootstrap', 'VERSION', 'README_V2_8_5_EMPLOYEE_ATTENDANCE_HISTORY.md', 'iso-setup.php', 'iso-command-centre-v3.0.0.zip'] as $gone) {
            $this->assertFileDoesNotExist("$web/$gone");
        }
        $this->assertFileExists("$web/cgi-bin/.htaccess");
        $this->assertFileExists("$home/releases/v2.8.5/README_V2_8_5_EMPLOYEE_ATTENDANCE_HISTORY.md");
        $this->assertStringContainsString('left where they are:</small></p><ul><li><code>cgi-bin</code></li></ul>', $page, 'Only what was never ours is listed.');
        $this->assertStringContainsString("define('ISO_HOME', ".var_export($home, true).')', file_get_contents("$web/index.php"));
        $this->assertStringContainsString('RewriteRule ^public/(.+)$ /$1', file_get_contents("$web/.htaccess"));
        $this->assertFileExists("$web/recovery.php");
        $this->assertFileExists("$home/cron.php");

        // v3.0.0 runs, and finishes the update once a System Administrator logs in.
        $this->assertSame('v3.0.0', $this->server->current());
        $pending = $this->server->pending();
        $this->assertSame(['v2.8.5', 'v3.0.0', false, null], [$pending['from'], $pending['to'], $pending['migrated'], $pending['backup']]);
        $this->assertGreaterThan(time() + 80000, $pending['until']);
        $this->assertStringContainsString('/settings/updates/finish/'.$pending['token'], $page);
    }

    public function test_after_the_move_the_front_door_switches_back_to_the_old_site_if_the_new_version_cannot_start(): void
    {
        $this->legacySite();
        $this->uploadRelease();
        $this->openSetup(['home' => $this->server->home]);
        file_put_contents($this->server->home.'/releases/v3.0.0/bootstrap/app.php', '<?php throw new RuntimeException("boom");');

        $this->server->run($this->server->web.'/index.php');

        $this->assertSame('v2.8.5', $this->server->current());
        $this->assertNull($this->server->pending());
        $this->assertStringContainsString('boom', iso_read_php($this->server->home.'/update-failed.php')['reason']);
    }

    public function test_an_older_release_is_refused_and_the_site_is_left_untouched(): void
    {
        $this->legacySite('2.9.0');
        $this->uploadRelease('v2.8.6');

        $page = $this->openSetup(['home' => $this->server->home]);

        $this->assertStringContainsString('v2.9.0 is already installed', $page);
        $this->assertFileExists($this->server->web.'/artisan');
        $this->assertFileExists($this->server->web.'/.env');
        $this->assertFileExists($this->server->web.'/storage/app/private/employee-documents/medical.enc');
        $this->assertSame([], iso_installed_releases($this->server->home));
        $this->assertFileExists($this->server->web.'/iso-command-centre-v2.8.6.zip', 'Kept, so the person can see what went wrong.');
    }

    public function test_a_private_folder_inside_the_web_folder_is_refused(): void
    {
        $this->legacySite();
        $this->uploadRelease();

        $page = $this->openSetup(['home' => $this->server->web.'/private']);

        $this->assertStringContainsString('can be reached from the internet', $page);
        $this->assertFileExists($this->server->web.'/artisan');
    }

    public function test_a_site_already_on_the_release_layout_is_upgraded_instead(): void
    {
        mkdir($this->server->home.'/releases', 0755, true);
        mkdir($this->server->home.'/shared/storage/framework', 0755, true);
        $this->server->install('v3.0.0');
        $this->uploadRelease('v3.1.0');

        $this->assertStringContainsString('v3.0.0 is installed in', $this->openSetup());
        $page = $this->openSetup(['upgrade' => '1']);

        $this->assertStringContainsString('Log in and finish the update', $page);
        $this->assertSame('v3.1.0', $this->server->current());
        $this->assertSame('v3.0.0', $this->server->pending()['from']);
    }
}
