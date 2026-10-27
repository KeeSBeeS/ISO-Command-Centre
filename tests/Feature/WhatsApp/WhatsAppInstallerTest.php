<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Services\WhatsApp\WhatsAppActivation;
use App\Services\WhatsApp\WhatsAppInstaller;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsAppInstallerTest extends TestCase
{
    use RefreshDatabase, InteractsWithWhatsApp;

    private const MIGRATION = 'package-releases/2.9.1/migrations/2026_10_27_000001_install_whatsapp_business.php';

    private ?string $sandbox = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatform();
    }

    protected function tearDown(): void
    {
        if ($this->sandbox) {
            exec('rm -rf ' . escapeshellarg($this->sandbox));
        }
        parent::tearDown();
    }

    public function test_install_creates_tables_settings_and_permissions(): void
    {
        $this->app->make(WhatsAppInstaller::class)->install();

        foreach (WhatsAppInstaller::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }

        $this->assertSame(count(WhatsAppInstaller::PERMISSIONS), Permission::where('module', 'WhatsApp')->count());
        $this->assertSame(count(WhatsAppInstaller::PERMISSIONS), Role::where('slug', 'system-administrator')->first()->permissions()->count());
        $this->assertTrue(Role::where('slug', 'director')->first()->permissions()->where('slug', 'whatsapp.broadcast')->exists());
        $this->assertFalse(Role::where('slug', 'director')->first()->permissions()->where('slug', 'whatsapp.settings.manage')->exists());
        $this->assertFalse(Role::where('slug', 'employee')->first()->permissions()->exists());
        $this->assertSame(40, strlen((string) SystemSetting::where('key', 'whatsapp_cron_key')->value('value')));
    }

    public function test_rerun_keeps_values_and_role_changes(): void
    {
        $installer = $this->app->make(WhatsAppInstaller::class);
        $installer->install();

        $settings = $this->app->make(WhatsAppSettings::class);
        $settings->set('whatsapp_phone_number_id', '555000111');
        $cronKey = $settings->get('whatsapp_cron_key');

        $director = Role::where('slug', 'director')->first();
        $director->permissions()->detach(Permission::where('slug', 'whatsapp.broadcast')->value('id'));

        $installer->install();
        $settings->flush();

        $this->assertSame('555000111', $settings->get('whatsapp_phone_number_id'));
        $this->assertSame($cronKey, $settings->get('whatsapp_cron_key'));
        $this->assertFalse($director->permissions()->where('slug', 'whatsapp.broadcast')->exists());
    }

    public function test_secrets_are_encrypted_at_rest(): void
    {
        $settings = $this->installWhatsApp();

        $stored = SystemSetting::where('key', 'whatsapp_access_token')->value('value');
        $this->assertNotSame('test-token', $stored);
        $this->assertSame('test-token', $settings->get('whatsapp_access_token'));
    }

    public function test_package_migration_installs_and_rolls_back_cleanly(): void
    {
        SystemSetting::create(['key' => 'core_unrelated', 'group' => 'Core', 'label' => 'Unrelated', 'value' => 'keep']);
        $live = $this->makeLiveSandbox();
        $originalProviders = file_get_contents($live . '/bootstrap/providers.php');
        $originalLayout = file_get_contents($live . '/resources/views/layouts/app.blade.php');

        $migration = require base_path(self::MIGRATION);
        $this->app->setBasePath($live);

        $migration->up();
        $migration->up(); // idempotent

        $providers = file_get_contents($live . '/bootstrap/providers.php');
        $layout = file_get_contents($live . '/resources/views/layouts/app.blade.php');
        $this->assertSame(1, substr_count($providers, 'WhatsAppServiceProvider::class) ?'));
        $this->assertContains(\App\Providers\WhatsAppServiceProvider::class, require $live . '/bootstrap/providers.php');
        $this->assertSame(1, substr_count($layout, "route('whatsapp.inbox')"));
        $this->assertStringContainsString('LIVE-ONLY Compliance', $layout);
        $this->assertLessThan(strpos($layout, '<div class="nav-section-label">Admin</div>'), strpos($layout, WhatsAppActivation::MENU_START));
        $this->assertStringContainsString("str_starts_with(\$routeName, 'whatsapp')", $layout);
        $this->assertNotEmpty(glob($live . '/bootstrap/providers.php.bak-*'));
        $this->assertTrue(Schema::hasTable('whatsapp_messages'));

        $migration->down();

        $this->assertSame($originalProviders, file_get_contents($live . '/bootstrap/providers.php'));
        $this->assertSame($originalLayout, file_get_contents($live . '/resources/views/layouts/app.blade.php'));
        foreach (WhatsAppInstaller::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
        $this->assertSame(0, Permission::where('slug', 'like', 'whatsapp.%')->count());
        $this->assertSame(0, SystemSetting::where('key', 'like', 'whatsapp_%')->count());
        $this->assertSame('keep', SystemSetting::where('key', 'core_unrelated')->value('value'));
    }

    public function test_guarded_provider_entry_survives_missing_module_files(): void
    {
        $live = $this->makeLiveSandbox();
        (new WhatsAppActivation($live))->add();

        $code = str_replace('App\\Providers\\WhatsAppServiceProvider', 'App\\Providers\\RemovedByRescueProvider', file_get_contents($live . '/bootstrap/providers.php'));
        file_put_contents($live . '/bootstrap/providers.php', $code);

        $this->assertSame([\App\Providers\AppServiceProvider::class], require $live . '/bootstrap/providers.php');
    }

    public function test_legacy_activation_script_line_is_replaced_by_guarded_block(): void
    {
        $live = $this->makeLiveSandbox("<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n    App\\Providers\\WhatsAppServiceProvider::class,\n];\n");

        (new WhatsAppActivation($live))->add();

        $providers = file_get_contents($live . '/bootstrap/providers.php');
        $this->assertSame(1, substr_count($providers, 'WhatsAppServiceProvider::class) ?'));
        $this->assertSame(2, substr_count($providers, 'WhatsAppServiceProvider::class'));
        $this->assertSame([\App\Providers\AppServiceProvider::class, \App\Providers\WhatsAppServiceProvider::class], require $live . '/bootstrap/providers.php');
    }

    /**
     * A copy of the live files the package patches: the repo layout plus a menu item that only exists live.
     */
    private function makeLiveSandbox(?string $providers = null): string
    {
        $this->sandbox = sys_get_temp_dir() . '/wa-live-' . bin2hex(random_bytes(4));
        mkdir($this->sandbox . '/bootstrap', 0755, true);
        mkdir($this->sandbox . '/resources/views/layouts', 0755, true);

        file_put_contents($this->sandbox . '/bootstrap/providers.php', $providers ?? file_get_contents(base_path('bootstrap/providers.php')));
        $layout = str_replace(
            '<div class="nav-section-label">Admin</div>',
            "<a href=\"/compliance\">LIVE-ONLY Compliance</a>\n                <div class=\"nav-section-label\">Admin</div>",
            file_get_contents(base_path('resources/views/layouts/app.blade.php'))
        );
        file_put_contents($this->sandbox . '/resources/views/layouts/app.blade.php', $layout);

        return $this->sandbox;
    }
}
