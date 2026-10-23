<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Department;
use App\Models\SystemSetting;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppInboxTest extends TestCase
{
    use RefreshDatabase, InteractsWithWhatsApp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatform();
        $this->installWhatsApp();
    }

    public function test_pages_render_for_system_administrator(): void
    {
        $admin = $this->makeUser('system-administrator');
        $contact = WhatsAppContact::create(['wa_id' => '27821234567', 'profile_name' => 'Sipho', 'last_inbound_at' => now(), 'last_message_at' => now(), 'unread_count' => 1]);
        $contact->messages()->create(['direction' => 'inbound', 'type' => 'text', 'body' => '<b>Hello</b>', 'status' => 'received', 'wamid' => 'wamid.X']);
        WhatsAppTemplate::create(['name' => 'hello_world', 'language' => 'en_US', 'status' => 'APPROVED', 'category' => 'UTILITY', 'components' => [['type' => 'BODY', 'text' => 'Hello World']]]);

        $this->actingAs($admin)->get('/whatsapp')->assertOk()->assertSee('Sipho');
        $this->actingAs($admin)->get('/whatsapp/conversations/' . $contact->id)
            ->assertOk()
            ->assertSee('&lt;b&gt;Hello&lt;/b&gt;', false)
            ->assertSee('Window open until');
        $this->assertSame(0, $contact->refresh()->unread_count);

        foreach (['/whatsapp/contacts', '/whatsapp/templates', '/whatsapp/broadcasts', '/whatsapp/broadcasts/create', '/settings/whatsapp'] as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
    }

    public function test_employee_without_permission_is_denied(): void
    {
        $employee = $this->makeUser('employee');

        $this->actingAs($employee)->get('/whatsapp')->assertForbidden();
        $this->actingAs($employee)->get('/settings/whatsapp')->assertForbidden();
    }

    public function test_manager_can_reply_but_not_manage_settings(): void
    {
        $this->fakeGraphSuccess();
        $manager = $this->makeUser('manager');
        $contact = WhatsAppContact::create(['wa_id' => '27821234567', 'last_inbound_at' => now(), 'last_message_at' => now()]);

        $this->actingAs($manager)->post('/whatsapp/conversations/' . $contact->id . '/text', ['body' => 'We are on our way'])
            ->assertRedirect('/whatsapp/conversations/' . $contact->id)
            ->assertSessionHas('success');

        $message = WhatsAppMessage::sole();
        $this->assertSame('sent', $message->status);
        $this->assertSame($manager->id, $message->sent_by);

        $this->actingAs($manager)->put('/settings/whatsapp', [])->assertForbidden();
        $this->actingAs($manager)->get('/whatsapp/broadcasts')->assertForbidden();
    }

    public function test_poll_returns_new_messages_and_statuses(): void
    {
        $admin = $this->makeUser('system-administrator');
        $contact = WhatsAppContact::create(['wa_id' => '27821234567', 'last_inbound_at' => now(), 'last_message_at' => now()]);
        $out = $contact->messages()->create(['direction' => 'outbound', 'type' => 'text', 'body' => 'Hi', 'status' => 'delivered']);
        $in = $contact->messages()->create(['direction' => 'inbound', 'type' => 'text', 'body' => 'Thanks!', 'status' => 'received']);

        $this->actingAs($admin)->getJson('/whatsapp/conversations/' . $contact->id . '/poll?after=' . $out->id)
            ->assertOk()
            ->assertJsonPath('last_id', $in->id)
            ->assertJsonPath('statuses.' . $out->id . '.status', 'delivered')
            ->assertJsonPath('window_open', true)
            ->assertSee('Thanks!');
    }

    public function test_start_conversation_with_template_by_phone(): void
    {
        $this->fakeGraphSuccess();
        $admin = $this->makeUser('system-administrator');
        $template = WhatsAppTemplate::create(['name' => 'site_visit', 'language' => 'en', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, we visit on {{2}}.']]]);

        $response = $this->actingAs($admin)->post('/whatsapp/start', [
            'phone' => '072 000 1111', 'name' => 'Client Foreman', 'template_id' => $template->id, 'params' => ['Pieter', 'Monday'],
        ]);

        $contact = WhatsAppContact::where('wa_id', '27720001111')->firstOrFail();
        $response->assertRedirect('/whatsapp/conversations/' . $contact->id);
        $this->assertSame('Client Foreman', $contact->display_name);
        $this->assertSame('Hi Pieter, we visit on Monday.', $contact->messages()->sole()->body);
    }

    public function test_settings_update_keeps_secret_when_left_blank(): void
    {
        $admin = $this->makeUser('system-administrator');
        $settings = $this->app->make(WhatsAppSettings::class);

        $this->actingAs($admin)->put('/settings/whatsapp', [
            'whatsapp_enabled' => '1',
            'whatsapp_api_version' => 'v24.0',
            'whatsapp_phone_number_id' => '1234567890',
            'whatsapp_business_account_id' => '9876543210',
            'whatsapp_access_token' => '',
            'whatsapp_app_secret' => '',
            'whatsapp_webhook_verify_token' => 'verify-token-123',
            'whatsapp_default_country_code' => '27',
            'whatsapp_cron_key' => 'cron-key-1234567890',
            'whatsapp_template_language' => 'en',
        ])->assertRedirect('/settings/whatsapp')->assertSessionHasNoErrors();

        $settings->flush();
        $this->assertSame('test-token', $settings->get('whatsapp_access_token'));
        $this->assertSame('v24.0', $settings->get('whatsapp_api_version'));
        $this->assertFalse($settings->bool('whatsapp_require_opt_in'));

        $this->actingAs($admin)->get('/settings/whatsapp')->assertOk()->assertDontSee('test-token');
        $this->assertStringNotContainsString('test-token', (string) SystemSetting::where('key', 'whatsapp_access_token')->value('value'));
    }

    public function test_broadcast_queues_and_sends_personalised_messages_to_opted_in_department(): void
    {
        $this->fakeGraphSuccess();
        $admin = $this->makeUser('system-administrator');
        $department = Department::create(['name' => 'Workshop']);
        $optedIn = $this->makeUser('employee', ['name' => 'Lerato Mokoena'], '0821111111');
        $notOptedIn = $this->makeUser('employee', ['name' => 'Johan Smit'], '0822222222');
        $otherDept = $this->makeUser('employee', ['name' => 'Other Person'], '0823333333');
        $optedIn->departments()->attach($department->id);
        $notOptedIn->departments()->attach($department->id);
        WhatsAppContact::create(['wa_id' => '27821111111', 'user_id' => $optedIn->id, 'opted_in_at' => now()]);
        WhatsAppContact::create(['wa_id' => '27823333333', 'user_id' => $otherDept->id, 'opted_in_at' => now()]);
        $template = WhatsAppTemplate::create(['name' => 'notice', 'language' => 'en', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}: {{2}}']]]);

        $this->actingAs($admin)->postJson('/whatsapp/broadcasts/preview', ['audience_type' => 'department', 'department_ids' => [$department->id]])
            ->assertOk()->assertJson(['count' => 1]);

        $this->actingAs($admin)->post('/whatsapp/broadcasts', [
            'name' => 'Workshop closure', 'template_id' => $template->id, 'audience_type' => 'department',
            'department_ids' => [$department->id], 'params' => ['{first_name}', 'Workshop closed Friday'],
        ])->assertRedirect();

        $broadcast = WhatsAppBroadcast::sole();
        $this->assertSame(1, $broadcast->total_count);
        $this->assertSame('completed', $broadcast->status);
        $message = $broadcast->messages()->sole();
        $this->assertSame('sent', $message->status);
        $this->assertSame('Hi Lerato: Workshop closed Friday', $message->body);
    }

    public function test_media_download_is_cached_and_served_safely(): void
    {
        $admin = $this->makeUser('system-administrator');
        $contact = WhatsAppContact::create(['wa_id' => '27821234567']);
        $message = $contact->messages()->create(['direction' => 'inbound', 'type' => 'document', 'status' => 'received', 'media_id' => 'media-9', 'media_mime' => 'text/html', 'media_filename' => 'evil.html']);

        Http::fake([
            'graph.facebook.com/*/media-9' => Http::response(['url' => 'https://lookaside.fbsbx.com/whatsapp/media-9', 'mime_type' => 'text/html']),
            'lookaside.fbsbx.com/*' => Http::response('<script>alert(1)</script>', 200, ['Content-Type' => 'text/html']),
        ]);

        $response = $this->actingAs($admin)->get('/whatsapp/messages/' . $message->id . '/media');
        $response->assertOk();
        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertNotNull($message->refresh()->media_path);

        \Illuminate\Support\Facades\Storage::disk('local')->delete($message->media_path);
    }
}
