<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class WhatsAppWebhookProcessor
{
    public const OPT_OUT_KEYWORDS = ['STOP', 'UNSUBSCRIBE', 'STOP ALL', 'OPT OUT', 'OPTOUT'];

    public const OPT_IN_KEYWORDS = ['START', 'SUBSCRIBE', 'OPT IN', 'OPTIN'];

    public function __construct(private WhatsAppSettings $settings, private WhatsAppMessenger $messenger)
    {
    }

    /**
     * Meta signs each webhook with HMAC-SHA256 of the raw body using the App Secret.
     */
    public function signatureIsValid(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = (string) $this->settings->get('whatsapp_app_secret', '');
        if ($secret === '' || !$signatureHeader || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256=' . hash_hmac('sha256', $rawBody, $secret), $signatureHeader);
    }

    public function process(array $payload): array
    {
        $summary = ['messages' => 0, 'statuses' => 0, 'ignored' => 0];

        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            $summary['ignored']++;

            return $summary;
        }

        $phoneNumberId = (string) $this->settings->get('whatsapp_phone_number_id', '');

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? null) !== 'messages') {
                    $summary['ignored']++;
                    continue;
                }

                $value = (array) ($change['value'] ?? []);
                $metadataPhoneId = (string) ($value['metadata']['phone_number_id'] ?? '');
                if ($phoneNumberId !== '' && $metadataPhoneId !== '' && $metadataPhoneId !== $phoneNumberId) {
                    $summary['ignored']++;
                    continue;
                }

                $profiles = [];
                foreach ((array) ($value['contacts'] ?? []) as $profile) {
                    if (!empty($profile['wa_id'])) {
                        $profiles[(string) $profile['wa_id']] = $profile['profile']['name'] ?? null;
                    }
                }

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    if ($this->storeInbound($message, $profiles)) {
                        $summary['messages']++;
                    }
                }

                foreach ((array) ($value['statuses'] ?? []) as $status) {
                    if ($this->applyStatus($status)) {
                        $summary['statuses']++;
                    }
                }
            }
        }

        return $summary;
    }

    private function storeInbound(array $data, array $profiles): bool
    {
        $wamid = (string) ($data['id'] ?? '');
        $from = (string) ($data['from'] ?? '');
        if ($wamid === '' || $from === '' || WhatsAppMessage::where('wamid', $wamid)->exists()) {
            return false;
        }

        $type = (string) ($data['type'] ?? 'unsupported');
        [$body, $media] = $this->extractContent($type, $data);
        $timestamp = isset($data['timestamp']) ? Carbon::createFromTimestamp((int) $data['timestamp']) : now();

        [$contact, $windowWasOpen] = DB::transaction(function () use ($from, $profiles, $wamid, $type, $body, $media, $data, $timestamp) {
            $contact = WhatsAppContact::lockForUpdate()->firstOrCreate(['wa_id' => $from]);
            $windowWasOpen = $contact->isServiceWindowOpen();

            $message = $contact->messages()->create(array_merge([
                'direction' => 'inbound',
                'wamid' => $wamid,
                'type' => $type,
                'body' => $body,
                'context_wamid' => $data['context']['id'] ?? null,
                'status' => 'received',
                'source' => 'customer',
                'payload' => $data,
                'created_at' => $timestamp,
            ], $media));

            $contact->forceFill([
                'profile_name' => $profiles[$from] ?? $contact->profile_name,
                'last_inbound_at' => $timestamp,
                'last_message_at' => $timestamp,
                'last_message_preview' => Str::limit($message->preview, 120),
                'unread_count' => $contact->unread_count + 1,
            ])->save();

            return [$contact, $windowWasOpen];
        });

        $handledKeyword = $this->handleKeywords($contact, $type, $body);
        if (!$windowWasOpen && !$handledKeyword) {
            $this->autoReply($contact);
        }

        return true;
    }

    private function extractContent(string $type, array $data): array
    {
        $media = [];
        $body = null;

        switch ($type) {
            case 'text':
                $body = $data['text']['body'] ?? null;
                break;
            case 'image':
            case 'video':
            case 'audio':
            case 'document':
            case 'sticker':
                $item = (array) ($data[$type] ?? []);
                $body = $item['caption'] ?? null;
                $media = [
                    'media_id' => $item['id'] ?? null,
                    'media_mime' => $item['mime_type'] ?? null,
                    'media_filename' => $item['filename'] ?? null,
                ];
                break;
            case 'location':
                $location = (array) ($data['location'] ?? []);
                $body = trim(implode(' · ', array_filter([
                    $location['name'] ?? null,
                    $location['address'] ?? null,
                    isset($location['latitude'], $location['longitude']) ? $location['latitude'] . ',' . $location['longitude'] : null,
                ])));
                break;
            case 'button':
                $body = $data['button']['text'] ?? $data['button']['payload'] ?? null;
                break;
            case 'interactive':
                $interactive = (array) ($data['interactive'] ?? []);
                $body = $interactive['button_reply']['title'] ?? $interactive['list_reply']['title'] ?? null;
                break;
            case 'reaction':
                $body = 'Reacted ' . ($data['reaction']['emoji'] ?? '');
                break;
            case 'contacts':
                $names = array_map(fn ($contact) => $contact['name']['formatted_name'] ?? 'Contact', (array) ($data['contacts'] ?? []));
                $body = 'Shared contact: ' . implode(', ', $names);
                break;
            default:
                $body = $data['errors'][0]['title'] ?? 'Unsupported message type.';
        }

        return [$body !== null ? mb_substr((string) $body, 0, 4096) : null, $media];
    }

    /**
     * Statuses can arrive out of order, so a status never moves a message backwards (read stays read).
     */
    private function applyStatus(array $status): bool
    {
        $message = WhatsAppMessage::where('wamid', (string) ($status['id'] ?? ''))->where('direction', 'outbound')->first();
        if (!$message) {
            return false;
        }

        $new = (string) ($status['status'] ?? '');
        $at = isset($status['timestamp']) ? Carbon::createFromTimestamp((int) $status['timestamp']) : now();

        if ($new === 'failed') {
            $error = (array) ($status['errors'][0] ?? []);
            $message->forceFill([
                'status' => 'failed',
                'failed_at' => $at,
                'error_code' => isset($error['code']) ? (string) $error['code'] : null,
                'error_message' => mb_substr((string) ($error['error_data']['details'] ?? $error['message'] ?? $error['title'] ?? 'Delivery failed.'), 0, 1000),
            ])->save();

            return true;
        }

        if (!isset(WhatsAppMessage::STATUS_RANK[$new])) {
            return false;
        }

        $timestampColumn = match ($new) {
            'sent' => 'sent_at',
            'delivered' => 'delivered_at',
            'read' => 'read_at',
            default => null,
        };

        $updates = [];
        if ($timestampColumn && !$message->{$timestampColumn}) {
            $updates[$timestampColumn] = $at;
        }
        if ($message->status !== 'failed' && WhatsAppMessage::STATUS_RANK[$new] > (WhatsAppMessage::STATUS_RANK[$message->status] ?? -1)) {
            $updates['status'] = $new;
        }

        if ($updates !== []) {
            $message->forceFill($updates)->save();
        }

        return true;
    }

    private function handleKeywords(WhatsAppContact $contact, string $type, ?string $body): bool
    {
        if (!in_array($type, ['text', 'button', 'interactive'], true) || $body === null) {
            return false;
        }

        $keyword = strtoupper(trim(preg_replace('/\s+/', ' ', $body), " \t\n\r.!"));

        if (in_array($keyword, self::OPT_OUT_KEYWORDS, true)) {
            $contact->forceFill(['opted_out_at' => now()])->save();
            $this->safeText($contact, 'You have been unsubscribed from ISO WhatsApp notifications. Reply START to subscribe again.');
        } elseif (in_array($keyword, self::OPT_IN_KEYWORDS, true) && ($contact->isOptedOut() || !$contact->isOptedIn())) {
            $contact->forceFill(['opted_in_at' => now(), 'opted_out_at' => null, 'opt_in_source' => 'WhatsApp keyword: ' . $keyword])->save();
            $this->safeText($contact, 'You are subscribed to ISO WhatsApp notifications. Reply STOP at any time to unsubscribe.');
        } else {
            return false;
        }

        return true;
    }

    private function autoReply(WhatsAppContact $contact): void
    {
        if (!$this->settings->bool('whatsapp_auto_reply_enabled')) {
            return;
        }

        $text = trim((string) $this->settings->get('whatsapp_auto_reply_message', ''));
        if ($text !== '') {
            $this->safeText($contact, $text, 'auto_reply');
        }
    }

    private function safeText(WhatsAppContact $contact, string $text, string $source = 'auto_reply'): void
    {
        if (!$this->settings->ready()) {
            return;
        }

        try {
            $this->messenger->sendText($contact->refresh(), $text, null, null, $source);
        } catch (Throwable) {
            // Automatic replies must never break webhook processing.
        }
    }
}
