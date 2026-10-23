<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Services\WhatsApp\WhatsAppInstaller;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsAppInstallerTest extends TestCase
{
    use RefreshDatabase, InteractsWithWhatsApp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatform();
    }

    public function test_install_creates_tables_settings_and_permissions(): void
    {
        SystemSetting::create(['key' => 'platform_version', 'group' => 'Identity', 'label' => 'Platform Version', 'value' => '2.8.5']);

        $this->app->make(WhatsAppInstaller::class)->install();

        foreach (['whatsapp_contacts', 'whatsapp_messages', 'whatsapp_templates', 'whatsapp_broadcasts', 'whatsapp_webhook_events'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }

        $this->assertSame(count(WhatsAppInstaller::PERMISSIONS), Permission::where('module', 'WhatsApp')->count());
        $this->assertSame(count(WhatsAppInstaller::PERMISSIONS), Role::where('slug', 'system-administrator')->first()->permissions()->count());
        $this->assertTrue(Role::where('slug', 'director')->first()->permissions()->where('slug', 'whatsapp.broadcast')->exists());
        $this->assertFalse(Role::where('slug', 'director')->first()->permissions()->where('slug', 'whatsapp.settings.manage')->exists());
        $this->assertFalse(Role::where('slug', 'employee')->first()->permissions()->exists());

        $this->assertSame('2.9.0', SystemSetting::where('key', 'platform_version')->value('value'));
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

    public function test_install_does_not_downgrade_newer_platform_version(): void
    {
        SystemSetting::create(['key' => 'platform_version', 'group' => 'Identity', 'label' => 'Platform Version', 'value' => '3.0.0']);

        $this->app->make(WhatsAppInstaller::class)->install();

        $this->assertSame('3.0.0', SystemSetting::where('key', 'platform_version')->value('value'));
    }

    public function test_secrets_are_encrypted_at_rest(): void
    {
        $settings = $this->installWhatsApp();

        $stored = SystemSetting::where('key', 'whatsapp_access_token')->value('value');
        $this->assertNotSame('test-token', $stored);
        $this->assertSame('test-token', $settings->get('whatsapp_access_token'));
    }

    public function test_setup_page_runs_for_system_administrator(): void
    {
        $admin = $this->makeUser('system-administrator');

        $this->actingAs($admin)->get('/updates/v2-9-0')->assertOk()->assertSee('Apply v2.9.0 Update');
        $this->actingAs($admin)->post('/updates/v2-9-0')->assertRedirect('/updates/v2-9-0');

        $this->assertTrue(Schema::hasTable('whatsapp_messages'));
    }
}
