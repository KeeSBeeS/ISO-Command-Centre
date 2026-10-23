<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase, InteractsWithWhatsApp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatform();
        $this->installWhatsApp();
        $this->fakeGraphSuccess();
    }

    public function test_verification_handshake(): void
    {
        $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=verify-token-123&hub.challenge=987654')
            ->assertOk()
            ->assertSeeText('987654');

        $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=987654')->assertForbidden();
    }

    public function test_rejects_invalid_signature(): void
    {
        $raw = json_encode($this->inboundPayload('27821234567', 'wamid.IN1', ['type' => 'text', 'text' => ['body' => 'Hi']]));

        $this->call('POST', '/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad'], $raw)
            ->assertForbidden();

        $this->assertSame(0, WhatsAppMessage::count());
        $this->assertFalse(WhatsAppWebhookEvent::first()->signature_valid);
    }

    public function test_inbound_text_creates_contact_and_message_once(): void
    {
        $payload = $this->inboundPayload('27821234567', 'wamid.IN1', ['type' => 'text', 'text' => ['body' => 'Hello ISO']]);

        $this->signedWebhook($payload)->assertOk()->assertJson(['ok' => true, 'messages' => 1]);
        $this->signedWebhook($payload)->assertOk()->assertJson(['messages' => 0]);

        $contact = WhatsAppContact::where('wa_id', '27821234567')->firstOrFail();
        $this->assertSame('Sipho', $contact->profile_name);
        $this->assertSame(1, $contact->unread_count);
        $this->assertTrue($contact->isServiceWindowOpen());
        $this->assertSame('Hello ISO', $contact->messages()->first()->body);
        $this->assertSame(1, WhatsAppMessage::count());
    }

    public function test_ignores_other_phone_number_ids(): void
    {
        $payload = $this->inboundPayload('27821234567', 'wamid.IN1', ['type' => 'text', 'text' => ['body' => 'Hi']]);
        $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = '999';

        $this->signedWebhook($payload)->assertOk()->assertJson(['messages' => 0, 'ignored' => 1]);
    }

    public function test_inbound_media_is_stored_with_media_id(): void
    {
        $this->signedWebhook($this->inboundPayload('27821234567', 'wamid.IMG', [
            'type' => 'image',
            'image' => ['id' => 'media-1', 'mime_type' => 'image/jpeg', 'caption' => 'Site photo'],
        ]))->assertOk();

        $message = WhatsAppMessage::firstOrFail();
        $this->assertSame('image', $message->type);
        $this->assertSame('media-1', $message->media_id);
        $this->assertSame('Site photo', $message->body);
        $this->assertTrue($message->hasMedia());
    }

    public function test_status_updates_never_move_backwards(): void
    {
        $contact = WhatsAppContact::create(['wa_id' => '27821234567']);
        $message = $contact->messages()->create(['direction' => 'outbound', 'type' => 'text', 'body' => 'Hi', 'status' => 'sent', 'wamid' => 'wamid.OUT1']);

        $this->signedWebhook($this->statusPayload('wamid.OUT1', 'read'))->assertOk();
        $this->signedWebhook($this->statusPayload('wamid.OUT1', 'delivered'))->assertOk();

        $message->refresh();
        $this->assertSame('read', $message->status);
        $this->assertNotNull($message->read_at);
        $this->assertNotNull($message->delivered_at);
    }

    public function test_failed_status_records_error(): void
    {
        $contact = WhatsAppContact::create(['wa_id' => '27821234567']);
        $message = $contact->messages()->create(['direction' => 'outbound', 'type' => 'template', 'status' => 'sent', 'wamid' => 'wamid.OUT2']);

        $this->signedWebhook($this->statusPayload('wamid.OUT2', 'failed', [
            'errors' => [['code' => 131026, 'title' => 'Message undeliverable', 'error_data' => ['details' => 'Receiver is not on WhatsApp']]],
        ]))->assertOk();

        $message->refresh();
        $this->assertSame('failed', $message->status);
        $this->assertSame('131026', $message->error_code);
        $this->assertSame('Receiver is not on WhatsApp', $message->error_message);
    }

    public function test_stop_and_start_keywords_manage_consent(): void
    {
        $this->signedWebhook($this->inboundPayload('27821234567', 'wamid.S1', ['type' => 'text', 'text' => ['body' => 'stop']]))->assertOk();
        $contact = WhatsAppContact::where('wa_id', '27821234567')->first();
        $this->assertTrue($contact->isOptedOut());

        $this->signedWebhook($this->inboundPayload('27821234567', 'wamid.S2', ['type' => 'text', 'text' => ['body' => 'START']]))->assertOk();
        $contact->refresh();
        $this->assertTrue($contact->isOptedIn());
        $this->assertSame('WhatsApp keyword: START', $contact->opt_in_source);

        // Each keyword sends exactly one confirmation reply.
        $this->assertSame(2, WhatsAppMessage::where('direction', 'outbound')->count());
    }

    public function test_auto_reply_only_for_new_conversations(): void
    {
        $settings = $this->app->make(\App\Services\WhatsApp\WhatsAppSettings::class);
        $settings->set('whatsapp_auto_reply_enabled', '1');

        $this->signedWebhook($this->inboundPayload('27821234567', 'wamid.A1', ['type' => 'text', 'text' => ['body' => 'Hi']]))->assertOk();
        $this->signedWebhook($this->inboundPayload('27821234567', 'wamid.A2', ['type' => 'text', 'text' => ['body' => 'Are you there?']]))->assertOk();

        $replies = WhatsAppMessage::where('source', 'auto_reply')->get();
        $this->assertCount(1, $replies);
        $this->assertSame('sent', $replies->first()->status);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v23.0/1234567890/messages')
            && $request['type'] === 'text'
            && $request['to'] === '27821234567'
            && $request->hasHeader('Authorization', 'Bearer test-token'));
    }
}
