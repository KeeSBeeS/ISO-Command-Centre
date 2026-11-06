<?php

namespace Tests\Feature\Updates;

use App\Models\User;
use App\Support\EnvFile;
use App\Updates\DatabaseBackup;
use App\Updates\HealthCheck;
use App\Updates\History;
use App\Updates\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\Support\FakeServer;
use Tests\Support\InstallsIsoSchema;
use Tests\TestCase;

class UpdatesTest extends TestCase
{
    use InstallsIsoSchema;
    use RefreshDatabase;

    private FakeServer $server;

    private User $admin;

    /** @var list<string> */
    private array $restored = [];

    /** @var list<string> */
    private array $healthProblems = [];

    protected function setUp(): void
    {
        parent::setUp();
        Server::loadTools();

        $this->server = new FakeServer;
        $this->server->install('v0.2.0');
        $this->app->useStoragePath($this->server->home.'/shared/storage');
        config([
            'iso.home' => $this->server->home,
            'iso.web' => $this->server->web,
            'iso.version' => 'v0.2.0',
            'iso.updates.api_url' => 'https://api.test',
            'iso.updates.token' => 'token',
        ]);
        $this->app->instance(EnvFile::class, new EnvFile($this->server->home.'/shared/.env'));

        $this->mock(DatabaseBackup::class, function (MockInterface $mock) {
            $mock->shouldReceive('create')->andReturnUsing(function (string $label) {
                $path = $this->server->home."/backups/$label.sql.gz";
                touch($path);

                return $path;
            });
            $mock->shouldReceive('restore')->andReturnUsing(function (string $path) {
                $this->restored[] = $path;
            });
            $mock->shouldReceive('prune');
        });
        $this->mock(HealthCheck::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('problems')->andReturnUsing(fn () => $this->healthProblems));

        $this->installIsoSchema();
        $this->admin = $this->userWithRole('system-administrator');
    }

    protected function tearDown(): void
    {
        $this->server->destroy();
        parent::tearDown();
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin);
    }

    private function fakeGitHub(string $version = 'v0.3.0', ?string $checksum = null, array $overrides = []): void
    {
        $zip = $this->server->zip($version, $overrides);
        Http::fake([
            'api.test/repos/KeeSBeeS/ISO-Command-Centre/releases*' => Http::response([[
                'tag_name' => $version, 'draft' => false, 'prerelease' => false, 'body' => 'New: better things', 'published_at' => '2026-10-30T10:00:00Z',
                'assets' => [
                    ['name' => "iso-command-centre-$version.zip", 'url' => "https://api.test/assets/$version-zip"],
                    ['name' => "iso-command-centre-$version.zip.sha256", 'url' => "https://api.test/assets/$version-sha"],
                ],
            ]]),
            "api.test/assets/$version-zip" => self::asset($zip),
            "api.test/assets/$version-sha" => self::asset(($checksum ?? hash('sha256', $zip))."  iso-command-centre-$version.zip\n"),
            'api.test/repos/KeeSBeeS/ISO-Command-Centre' => Http::response(['full_name' => 'KeeSBeeS/ISO-Command-Centre']),
        ]);
    }

    /**
     * Like the real GitHub API: a release asset's bytes only when they are the
     * one thing asked for, otherwise the asset's description as JSON.
     */
    private static function asset(string $bytes): \Closure
    {
        return fn (Request $request) => $request->header('Accept') === ['application/octet-stream']
            ? Http::response($bytes)
            : Http::response(['url' => $request->url(), 'name' => 'asset', 'size' => strlen($bytes)]);
    }

    /**
     * Press Install, then follow the redirect into the new version.
     */
    private function installUpdate(string $version = 'v0.3.0'): TestResponse
    {
        $response = $this->asAdmin()->post('/settings/updates/install', ['version' => $version]);
        $response->assertRedirectContains('/settings/updates/finish/');
        config(['iso.version' => $this->server->current()]);

        return $this->get($response->headers->get('Location'));
    }

    public function test_only_system_administrators_and_the_update_permission_can_open_updates(): void
    {
        $this->get('/settings/updates')->assertRedirect('/login');
        $this->actingAs($this->userWithRole('employee'))->get('/settings/updates')->assertForbidden();
        $this->actingAs($this->userWithRole('manager'))->get('/settings/updates')->assertForbidden();
        $this->asAdmin()->get('/settings/updates')->assertOk()->assertSee('ISO Command Centre is running');

        // Directors have every permission seeded at install, including this one.
        $this->actingAs($this->userWithRole('director'))->get('/settings/updates')->assertOk();
    }

    public function test_the_update_permission_is_added_by_a_migration_on_existing_sites(): void
    {
        DB::table('permissions')->where('slug', 'platform_updates.manage')->delete();
        DB::table('migrations')->where('migration', '2026_11_06_000000_add_platform_updates_permission')->delete();

        $this->artisan('migrate', ['--force' => true]);

        $permission = DB::table('permissions')->where('slug', 'platform_updates.manage')->value('id');
        $this->assertNotNull($permission);
        $this->assertTrue(DB::table('permission_role')->where('permission_id', $permission)
            ->where('role_id', DB::table('roles')->where('slug', 'system-administrator')->value('id'))->exists());
    }

    public function test_a_development_checkout_says_updates_only_work_on_the_server(): void
    {
        config(['iso.home' => null, 'iso.web' => null]);

        $this->asAdmin()->get('/settings/updates')->assertOk()->assertSee('Updates only work on the server');
    }

    public function test_the_recovery_key_is_created_on_the_first_visit_and_shown_only_once(): void
    {
        $page = $this->asAdmin()->get('/settings/updates')->assertSee('save it now');
        preg_match('/[A-Z0-9]{5}(-[A-Z0-9]{5}){4}/', $page->getContent(), $match);

        $this->assertTrue(password_verify($match[0], iso_read_php($this->server->home.'/recovery-key.php')['hash']));
        $this->asAdmin()->get('/settings/updates')->assertDontSee('save it now')->assertDontSee($match[0]);
    }

    public function test_a_github_token_is_saved_only_once_github_accepts_it(): void
    {
        Http::fake(['api.test/repos/KeeSBeeS/ISO-Command-Centre' => Http::sequence()->push([], 401)->push(['full_name' => 'KeeSBeeS/ISO-Command-Centre'])]);
        $this->asAdmin()->from('/settings/updates')->post('/settings/updates/token', ['token' => 'wrong'])->assertSessionHasErrors('token');
        $this->assertNull((new EnvFile($this->server->home.'/shared/.env'))->get('ISO_GITHUB_TOKEN'));

        $this->asAdmin()->from('/settings/updates')->post('/settings/updates/token', ['token' => 'github_pat_good'])->assertSessionHasNoErrors();
        $this->assertSame('github_pat_good', (new EnvFile($this->server->home.'/shared/.env'))->get('ISO_GITHUB_TOKEN'));
    }

    public function test_checking_shows_a_newer_version_on_the_updates_page_and_every_page(): void
    {
        $this->fakeGitHub();

        $this->asAdmin()->from('/settings/updates')->post('/settings/updates/check')->assertRedirect('/settings/updates');

        $this->asAdmin()->get('/settings/updates')->assertSee('v0.3.0 is available')->assertSee('New: better things');
        $this->asAdmin()->get('/profile')->assertSee('ISO Command Centre v0.3.0 is available');
    }

    public function test_installing_an_update_switches_to_it_and_finishes_in_the_new_version(): void
    {
        $this->fakeGitHub();

        $response = $this->asAdmin()->post('/settings/updates/install', ['version' => 'v0.3.0']);

        $pending = $this->server->pending();
        $response->assertRedirect(route('platform_updates.finish', $pending['token']));
        $this->assertSame(['v0.2.0', 'v0.3.0', false], [$pending['from'], $pending['to'], $pending['migrated']]);
        $this->assertFileExists($pending['backup']);
        $this->assertSame('v0.3.0', $this->server->current());
        $this->assertFileExists($this->server->web.'/build/assets/app-v0.3.0.css');
        $this->assertTrue($this->app->isDownForMaintenance());
        $this->get('/profile')->assertServiceUnavailable();

        config(['iso.version' => 'v0.3.0']);
        $this->get(route('platform_updates.finish', $pending['token']))
            ->assertRedirect('/settings/updates')->assertSessionHas('status', 'ISO Command Centre is now running v0.3.0.');

        $this->assertNull($this->server->pending());
        $this->assertFalse($this->app->isDownForMaintenance());
        $this->assertSame([], $this->restored);
        $this->assertSame(['update', 'v0.2.0', 'v0.3.0'], array_values(array_intersect_key(
            app(History::class)->all()[0], array_flip(['type', 'from', 'to']))));
    }

    public function test_a_failed_health_check_switches_back_and_restores_the_database(): void
    {
        $this->fakeGitHub();
        $this->healthProblems = ['No System Administrator account was found: No active user has the System Administrator role.'];

        $this->installUpdate()->assertRedirect('/settings/updates')->assertSessionHas('error');

        $this->assertSame('v0.2.0', $this->server->current());
        $this->assertNull($this->server->pending());
        $this->assertCount(1, $this->restored);
        $this->assertFalse($this->app->isDownForMaintenance());
        $failed = iso_read_php($this->server->home.'/update-failed.php');
        $this->assertSame(['v0.3.0', true], [$failed['version'], $failed['database_restored']]);
        $this->assertStringContainsString('No System Administrator account was found', $failed['reason']);

        config(['iso.version' => 'v0.2.0']);
        $this->asAdmin()->get('/settings/updates')->assertSee('The update to v0.3.0 was undone');

        // The failed version is removed.
        $this->assertDirectoryDoesNotExist($this->server->home.'/releases/v0.3.0');
    }

    public function test_a_failure_the_front_door_undid_is_logged_and_cleaned_up_when_the_updates_page_shows_it(): void
    {
        // As the front door leaves it after a release that couldn't start.
        $this->server->addRelease('v0.3.0');
        iso_write_php($this->server->home.'/update-failed.php', ['version' => 'v0.3.0', 'reason' => 'The new version could not start: boom', 'database_restored' => false, 'at' => time()]);

        $this->asAdmin()->get('/settings/updates')->assertSee('The update to v0.3.0 was undone')->assertSee('boom');
        $this->asAdmin()->get('/settings/updates');

        $this->assertDirectoryDoesNotExist($this->server->home.'/releases/v0.3.0');
    }

    public function test_a_damaged_download_is_rejected_before_anything_changes(): void
    {
        $this->fakeGitHub(checksum: str_repeat('0', 64));

        $this->asAdmin()->from('/settings/updates')->post('/settings/updates/install', ['version' => 'v0.3.0'])
            ->assertRedirect('/settings/updates')->assertSessionHas('error', fn ($error) => str_contains($error, 'damaged'));

        $this->assertSame('v0.2.0', $this->server->current());
        $this->assertNull($this->server->pending());
        $this->assertDirectoryDoesNotExist($this->server->home.'/releases/v0.3.0');
    }

    public function test_only_one_update_at_a_time_and_only_with_the_right_token(): void
    {
        $this->fakeGitHub();
        $this->asAdmin()->post('/settings/updates/install', ['version' => 'v0.3.0']);
        $this->app->maintenanceMode()->deactivate();

        $this->asAdmin()->from('/settings/updates')->post('/settings/updates/install', ['version' => 'v0.3.0'])
            ->assertSessionHas('error', 'Another update is still being finished.');

        config(['iso.version' => 'v0.3.0']);
        $this->asAdmin()->get(route('platform_updates.finish', 'wrong-token'))->assertSessionHas('error');
        $this->assertNotNull($this->server->pending());
    }

    public function test_rolling_back_with_the_database_restores_the_backup_from_when_that_version_was_left(): void
    {
        $this->fakeGitHub();
        $this->installUpdate();
        $backupLeavingOld = app(History::class)->all()[0]['backup'];

        $this->asAdmin()->post('/settings/updates/rollback', ['version' => 'v0.2.0', 'mode' => 'database'])
            ->assertRedirect('/settings/updates')->assertSessionHas('status');

        $this->assertSame('v0.2.0', $this->server->current());
        $this->assertSame([$backupLeavingOld], $this->restored);
        $this->assertSame('rollback', app(History::class)->all()[0]['type']);
        $this->assertFalse($this->app->isDownForMaintenance());
    }

    public function test_rolling_back_the_code_only_keeps_the_database(): void
    {
        $this->fakeGitHub();
        $this->installUpdate();

        $this->asAdmin()->post('/settings/updates/rollback', ['version' => 'v0.2.0', 'mode' => 'code'])->assertSessionHas('status');

        $this->assertSame('v0.2.0', $this->server->current());
        $this->assertSame([], $this->restored);
    }

    public function test_versions_without_the_update_manager_cannot_be_switched_to(): void
    {
        $this->server->addRelease('v0.1.0', withUpdater: false);

        $this->asAdmin()->get('/settings/updates')->assertSee('has no Update Manager');
        $this->asAdmin()->from('/settings/updates')->post('/settings/updates/rollback', ['version' => 'v0.1.0', 'mode' => 'code'])
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'no Update Manager'));
        $this->assertSame('v0.2.0', $this->server->current());
    }

    public function test_an_upgrade_from_the_setup_file_is_finished_after_logging_in(): void
    {
        // As iso_setup_upgrade() leaves it: switched, no backup yet.
        $this->server->addRelease('v0.3.0');
        iso_write_php($this->server->home.'/update-pending.php', [
            'from' => 'v0.2.0', 'to' => 'v0.3.0', 'token' => 'from-setup', 'backup' => null, 'migrated' => false, 'until' => time() + 86400,
        ]);
        iso_switch($this->server->home, 'v0.3.0');
        config(['iso.version' => 'v0.3.0']);

        $this->get('/settings/updates/finish/from-setup')->assertRedirect('/login');
        $this->actingAs($this->admin)->get('/settings/updates/finish/from-setup')->assertSessionHas('status', 'ISO Command Centre is now running v0.3.0.');

        $this->assertNull($this->server->pending());
        $this->assertStringContainsString('v0.2.0-before-v0.3.0', app(History::class)->all()[0]['backup']);
    }

    public function test_the_finish_step_waits_while_the_server_still_runs_the_old_version(): void
    {
        $this->fakeGitHub();
        $response = $this->asAdmin()->post('/settings/updates/install', ['version' => 'v0.3.0']);
        $finish = $response->headers->get('Location');

        // The server's PHP cache still serves v0.2.0 for a moment.
        $this->get($finish)
            ->assertOk()->assertSee('Switching to v0.3.0')
            ->assertHeader('Refresh', '3;url='.route('platform_updates.finish', ['token' => $this->server->pending()['token'], 'attempt' => 1]));
        $this->assertNotNull($this->server->pending());

        // Once the new version runs, the same address finishes the update.
        config(['iso.version' => 'v0.3.0']);
        $this->get($finish.'?attempt=4')->assertRedirect('/settings/updates')->assertSessionHas('status', 'ISO Command Centre is now running v0.3.0.');
        $this->assertNull($this->server->pending());
    }

    public function test_the_finish_step_gives_up_waiting_after_about_a_minute_and_a_half(): void
    {
        $this->fakeGitHub();
        $finish = $this->asAdmin()->post('/settings/updates/install', ['version' => 'v0.3.0'])->headers->get('Location');

        $this->get($finish.'?attempt=30')->assertRedirect('/settings/updates')
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'still running v0.2.0'));
        $this->assertNotNull($this->server->pending(), 'Left for cron.php to undo, or to finish later.');
    }

    public function test_during_maintenance_an_administrator_can_still_reach_the_updates_page_which_checks_again(): void
    {
        $this->fakeGitHub();
        $this->asAdmin()->post('/settings/updates/install', ['version' => 'v0.3.0']);
        $this->assertTrue($this->app->isDownForMaintenance());

        // Still on the old version: the page shows the unfinished update and reloads itself.
        $this->asAdmin()->get('/settings/updates')->assertOk()->assertSee("hasn't finished yet", false)->assertHeader('Refresh', '5');
        $this->asAdmin()->get('/profile')->assertServiceUnavailable();

        // Once the new version runs, opening the page finishes the update.
        config(['iso.version' => 'v0.3.0']);
        $finish = $this->asAdmin()->get('/settings/updates')->assertRedirect(route('platform_updates.finish', $this->server->pending()['token']));
        $this->get($finish->headers->get('Location'))->assertSessionHas('status', 'ISO Command Centre is now running v0.3.0.');
        $this->assertFalse($this->app->isDownForMaintenance());
        $this->asAdmin()->get('/profile')->assertOk();
    }

    public function test_an_upgrade_the_browser_never_finished_is_finished_by_opening_the_updates_page(): void
    {
        // As iso_setup_upgrade() leaves it when the finish link hit the old version.
        $this->server->addRelease('v0.3.0');
        iso_write_php($this->server->home.'/update-pending.php', [
            'from' => 'v0.2.0', 'to' => 'v0.3.0', 'token' => 'from-setup', 'backup' => null, 'migrated' => false, 'until' => time() + 86400,
        ]);
        iso_switch($this->server->home, 'v0.3.0');
        config(['iso.version' => 'v0.3.0']);

        $this->asAdmin()->get('/settings/updates')->assertRedirect('/settings/updates/finish/from-setup');
        $this->asAdmin()->get('/settings/updates/finish/from-setup')->assertSessionHas('status', 'ISO Command Centre is now running v0.3.0.');
        $this->assertNull($this->server->pending());
    }

    public function test_release_files_are_requested_as_files_and_never_as_json(): void
    {
        $this->fakeGitHub();

        $this->installUpdate()->assertSessionHas('status', 'ISO Command Centre is now running v0.3.0.');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/assets/')
            && $request->header('Accept') === ['application/octet-stream']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/assets/')
            && in_array('application/json', $request->header('Accept'), true));
    }

    public function test_a_description_instead_of_the_file_is_reported_clearly(): void
    {
        Http::fake([
            'api.test/repos/KeeSBeeS/ISO-Command-Centre/releases*' => Http::response([[
                'tag_name' => 'v0.3.0', 'draft' => false, 'prerelease' => false, 'body' => '', 'published_at' => null,
                'assets' => [
                    ['name' => 'iso-command-centre-v0.3.0.zip', 'url' => 'https://api.test/assets/zip'],
                    ['name' => 'iso-command-centre-v0.3.0.zip.sha256', 'url' => 'https://api.test/assets/sha'],
                ],
            ]]),
            'api.test/assets/*' => Http::response(['url' => 'https://api.test/assets/zip', 'name' => 'iso-command-centre-v0.3.0.zip']),
        ]);

        $this->asAdmin()->from('/settings/updates')->post('/settings/updates/install', ['version' => 'v0.3.0'])
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'something other than the release files'));
        $this->assertSame('v0.2.0', $this->server->current());
        $this->assertNull($this->server->pending());
    }
}
