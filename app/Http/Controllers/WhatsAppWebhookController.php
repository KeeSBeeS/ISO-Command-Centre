<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Services\WhatsApp\WhatsAppWebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    /**
     * Meta subscription handshake: echo hub.challenge when the verify token matches.
     */
    public function verify(Request $request, WhatsAppSettings $settings)
    {
        $expected = (string) $settings->get('whatsapp_webhook_verify_token', '');
        // PHP converts dots in query keys to underscores (hub.mode => hub_mode).
        $mode = (string) $request->query('hub_mode', '');
        $token = (string) $request->query('hub_verify_token', '');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return response((string) $request->query('hub_challenge', ''), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request, WhatsAppSettings $settings, WhatsAppWebhookProcessor $processor)
    {
        if (!$settings->installed()) {
            return response('Not installed', 503);
        }

        $raw = $request->getContent();
        $valid = $processor->signatureIsValid($raw, $request->header('X-Hub-Signature-256'));

        $event = WhatsAppWebhookEvent::create([
            'signature_valid' => $valid,
            'payload' => $valid ? $raw : mb_substr($raw, 0, 2000),
        ]);

        if (!$valid) {
            $event->update(['error' => 'Invalid or missing X-Hub-Signature-256. Check the App Secret in WhatsApp settings.']);

            return response('Invalid signature', 403);
        }

        $settings->set('whatsapp_last_webhook_at', now()->toDateTimeString());

        try {
            $summary = $processor->process((array) json_decode($raw, true));
            $event->update(['processed_at' => now(), 'error' => null]);
        } catch (Throwable $exception) {
            Log::error('WhatsApp webhook processing failed', ['event' => $event->id, 'error' => $exception->getMessage()]);
            $event->update(['error' => mb_substr($exception->getMessage(), 0, 2000)]);

            // A non-200 makes Meta retry; inbound messages are de-duplicated by wamid.
            return response('Processing failed', 500);
        }

        return response()->json(['ok' => true] + $summary);
    }
}
