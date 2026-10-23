<?php

namespace Tests\Feature\WhatsApp;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppMessenger;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WhatsAppMessagingTest extends TestCase
{
    use RefreshDatabase, InteractsWithWhatsApp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatform();
        $this->installWhatsApp();
    }

    public function test_free_form_text_requires_open_service_window(): void
    {
        $this->fakeGraphSuccess();
        $contact = WhatsAppContact::create(['wa_id' => '27821234567', 'last_inbound_at' => now()->subHours(25)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('24-hour');
        $this->app->make(WhatsAppMessenger::class)->sendText($contact, 'Hello');
    }

    public function test_template_payload_uses_synced_placeholders(): void
    {
        $this->fakeGraphSuccess();
        WhatsAppTemplate::create([
            'name' => 'named_tpl', 'language' => 'en', 'status' => 'APPROVED', 'parameter_format' => 'NAMED',
            'components' => [['type' => 'BODY', 'text' => 'Hi {{first_name}}, order {{order_no}} is ready.']],
        ]);
        $contact = WhatsAppContact::create(['wa_id' => '27821234567']);

        $message = $this->app->make(WhatsAppMessenger::class)->sendTemplate($contact, 'named_tpl', ["Thabo\nM", '', 'extra'], 'en');

        $this->assertSame('sent', $message->status);
        $this->assertSame('Hi Thabo M, order - is ready.', $message->body);
        Http::assertSent(function ($request) {
            $parameters = $request['template']['components'][0]['parameters'] ?? [];

            return $request['type'] === 'template'
                && $request['template']['name'] === 'named_tpl'
                && $request['template']['language']['code'] === 'en'
                && count($parameters) === 2
                && $parameters[0] === ['type' => 'text', 'text' => 'Thabo M', 'parameter_name' => 'first_name'];
        });
    }

    public function test_permanent_errors_fail_and_transient_errors_queue_for_retry(): void
    {
        $contact = WhatsAppContact::create(['wa_id' => '27821234567']);
        $messenger = $this->app->make(WhatsAppMessenger::class);

        Http::fakeSequence()
            ->push(['error' => ['code' => 132001, 'message' => 'Template name does not exist in the translation']], 404)
            ->push(['error' => ['code' => 2, 'message' => 'Service temporarily unavailable']], 503)
            ->push(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.RETRY']]]);

        $failed = $messenger->sendTemplate($contact, 'missing_tpl', [], 'en');
        $this->assertSame('failed', $failed->status);
        $this->assertSame('132001', $failed->error_code);

        $queued = $messenger->sendTemplate($contact, 'hello_world', [], 'en_US');
        $this->assertSame('queued', $queued->status);

        $result = $messenger->dispatchQueued();
        $this->assertSame(1, $result['sent']);
        $this->assertSame('sent', $queued->refresh()->status);
    }

    public function test_notifications_respect_opt_in_and_opt_out(): void
    {
        $this->fakeGraphSuccess();
        $contact = WhatsAppContact::create(['wa_id' => '27821234567']);
        $messenger = $this->app->make(WhatsAppMessenger::class);

        $this->assertNull($messenger->sendTemplate($contact, 'hello_world', [], 'en_US', null, 'notification'));

        $contact->update(['opted_in_at' => now()]);
        $this->assertNotNull($messenger->sendTemplate($contact, 'hello_world', [], 'en_US', null, 'notification', 'key-1'));
        $this->assertNull($messenger->sendTemplate($contact, 'hello_world', [], 'en_US', null, 'notification', 'key-1'), 'duplicate key is skipped');

        $contact->update(['opted_out_at' => now()]);
        $this->assertNull($messenger->sendTemplate($contact, 'hello_world', [], 'en_US', null, 'notification', 'key-2'));
    }

    public function test_contact_for_user_uses_profile_mobile(): void
    {
        $user = $this->makeUser('employee', ['phone' => '011 555 0000'], '083 222 3333');

        $contact = $this->app->make(WhatsAppMessenger::class)->contactForUser($user);

        $this->assertSame('27832223333', $contact->wa_id);
        $this->assertSame($user->id, $contact->user_id);
    }

    public function test_leave_decision_sends_notification_to_opted_in_employee(): void
    {
        $this->fakeGraphSuccess();
        $this->app->make(WhatsAppSettings::class)->set('whatsapp_notify_leave_enabled', '1');

        $employee = $this->makeUser('employee', [], '0821234567');
        WhatsAppContact::create(['wa_id' => '27821234567', 'user_id' => $employee->id, 'opted_in_at' => now()]);
        $type = LeaveType::create(['name' => 'Annual Leave', 'code' => 'annual']);
        $leave = LeaveRequest::create(['user_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => '2026-12-01', 'end_date' => '2026-12-05', 'status' => 'pending']);

        $leave->update(['status' => 'approved', 'reviewed_at' => now()]);
        $leave->update(['manager_notes' => 'Enjoy']);

        $message = WhatsAppMessage::where('source', 'notification')->sole();
        $this->assertSame('leave_request_update', $message->template_name);
        $this->assertSame([$employee->name, 'Annual Leave', '2026-12-01 to 2026-12-05', 'Approved'], $message->template_params['body']);
    }

    public function test_leave_approval_never_breaks_when_whatsapp_fails(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        $this->app->make(WhatsAppSettings::class)->set('whatsapp_notify_leave_enabled', '1');
        $employee = $this->makeUser('employee', [], '0821234567');
        WhatsAppContact::create(['wa_id' => '27821234567', 'user_id' => $employee->id, 'opted_in_at' => now()]);
        $leave = LeaveRequest::create(['user_id' => $employee->id, 'start_date' => '2026-12-01', 'end_date' => '2026-12-01', 'status' => 'pending']);

        $leave->update(['status' => 'declined', 'reviewed_at' => now()]);

        $this->assertSame('declined', $leave->refresh()->status);
        $this->assertSame('queued', WhatsAppMessage::sole()->status);
    }

    public function test_cron_requires_key_and_sends_document_reminders_once(): void
    {
        $this->fakeGraphSuccess();
        $this->app->make(WhatsAppSettings::class)->set('whatsapp_notify_documents_enabled', '1');
        $employee = $this->makeUser('employee', [], '0821234567');
        WhatsAppContact::create(['wa_id' => '27821234567', 'user_id' => $employee->id, 'opted_in_at' => now()]);
        DB::table('employee_documents')->insert([
            'user_id' => $employee->id, 'document_type' => 'medical', 'title' => 'Annual Medical', 'has_expiry' => true,
            'expires_at' => now()->addDays(10)->toDateString(), 'reminder_date' => now()->subDay()->toDateString(), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get('/whatsapp/cron/wrong-key-123456')->assertForbidden();
        $this->get('/whatsapp/cron/cron-key-1234567890')->assertOk()->assertJson(['ok' => true, 'employee_document_reminders' => 1]);
        $this->get('/whatsapp/cron/cron-key-1234567890')->assertOk()->assertJson(['employee_document_reminders' => 0]);

        $this->assertSame('document_expiry_reminder', WhatsAppMessage::sole()->template_name);
    }
}
