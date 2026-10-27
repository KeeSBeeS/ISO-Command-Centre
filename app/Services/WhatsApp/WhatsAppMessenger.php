<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WhatsAppMessenger
{
    public const MAX_ATTEMPTS = 3;

    public function __construct(private WhatsAppCloudApi $api, private WhatsAppSettings $settings)
    {
    }

    public function contactForNumber(string $number, ?string $name = null): WhatsAppContact
    {
        $waId = PhoneNumber::normalize($number, (string) $this->settings->get('whatsapp_default_country_code', '27'));
        if ($waId === null) {
            throw new RuntimeException('"' . $number . '" is not a valid phone number.');
        }

        $contact = WhatsAppContact::firstOrCreate(['wa_id' => $waId]);
        if ($name && !$contact->display_name && !$contact->user_id) {
            $contact->update(['display_name' => $name]);
        }

        return $contact;
    }

    /**
     * Find or create the WhatsApp contact for an employee using their mobile, then phone numbers.
     */
    public function contactForUser(User $user): ?WhatsAppContact
    {
        if ($contact = WhatsAppContact::where('user_id', $user->id)->first()) {
            return $contact;
        }

        $countryCode = (string) $this->settings->get('whatsapp_default_country_code', '27');
        $profile = Schema::hasTable('employee_profiles') ? $user->profile : null;

        foreach ([$profile?->mobile, $user->phone, $profile?->phone] as $number) {
            $waId = PhoneNumber::normalize($number, $countryCode);
            if ($waId === null) {
                continue;
            }

            $contact = WhatsAppContact::firstOrCreate(['wa_id' => $waId]);
            if (!$contact->user_id) {
                $contact->update(['user_id' => $user->id]);
            }

            return $contact;
        }

        return null;
    }

    /**
     * Free-form text. Only allowed inside the 24-hour customer service window.
     */
    public function sendText(WhatsAppContact $contact, string $body, ?User $sender = null, ?string $replyToWamid = null, string $source = 'manual'): WhatsAppMessage
    {
        $this->assertReady();

        $body = trim($body);
        if ($body === '') {
            throw new RuntimeException('Message text is required.');
        }

        if (!$contact->isServiceWindowOpen()) {
            throw new RuntimeException('The 24-hour WhatsApp service window is closed for this contact. Send an approved template instead.');
        }

        $message = $this->record($contact, [
            'type' => 'text',
            'body' => mb_substr($body, 0, 4096),
            'context_wamid' => $replyToWamid,
            'sent_by' => $sender?->id,
            'source' => $source,
            'status' => 'sending',
        ]);

        return $this->deliver($message);
    }

    /**
     * Send an approved template. Returns null when skipped (opted out, missing opt-in or duplicate notification).
     */
    public function sendTemplate(
        WhatsAppContact $contact,
        string $templateName,
        array $bodyParams = [],
        ?string $language = null,
        ?User $sender = null,
        string $source = 'manual',
        ?string $notificationKey = null,
        bool $queue = false,
        ?int $broadcastId = null,
        array $headerParams = [],
    ): ?WhatsAppMessage {
        $this->assertReady();

        if ($contact->isOptedOut()) {
            if ($source === 'manual') {
                throw new RuntimeException('This contact has opted out of WhatsApp messages.');
            }

            return null;
        }

        if ($source !== 'manual' && $this->settings->bool('whatsapp_require_opt_in') && !$contact->isOptedIn()) {
            return null;
        }

        if ($notificationKey && WhatsAppMessage::where('notification_key', $notificationKey)->where('status', '!=', 'failed')->exists()) {
            return null;
        }

        $language = $language ?: (string) $this->settings->get('whatsapp_template_language', 'en');
        $template = WhatsAppTemplate::where('name', $templateName)->where('language', $language)->first();

        $bodyParams = array_values(array_map(fn ($value) => WhatsAppCloudApi::cleanParameter($value), $bodyParams));
        $headerParams = array_values(array_map(fn ($value) => WhatsAppCloudApi::cleanParameter($value), $headerParams));

        if ($template) {
            $bodyParams = array_slice($bodyParams, 0, count($template->bodyPlaceholders()));
            $headerParams = array_slice($headerParams, 0, count($template->headerPlaceholders()));
        }

        $message = $this->record($contact, [
            'type' => 'template',
            'body' => $template ? $template->render($bodyParams) : implode(' | ', $bodyParams),
            'template_name' => $templateName,
            'template_language' => $language,
            'template_params' => ['body' => $bodyParams, 'header' => $headerParams],
            'sent_by' => $sender?->id,
            'source' => $source,
            'notification_key' => $notificationKey,
            'broadcast_id' => $broadcastId,
            'status' => $queue ? 'queued' : 'sending',
        ]);

        return $queue ? $message : $this->deliver($message);
    }

    public function sendMedia(WhatsAppContact $contact, UploadedFile $file, ?string $caption = null, ?User $sender = null): WhatsAppMessage
    {
        $this->assertReady();

        if (!$contact->isServiceWindowOpen()) {
            throw new RuntimeException('The 24-hour WhatsApp service window is closed for this contact. Media can only be sent after the contact messages you.');
        }

        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        $type = match (true) {
            in_array($mime, ['image/jpeg', 'image/png'], true) => 'image',
            in_array($mime, ['video/mp4', 'video/3gpp'], true) => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'document',
        };

        $filename = Str::limit($file->getClientOriginalName() ?: ('file.' . $file->extension()), 200, '');
        $path = $file->storeAs('whatsapp/outbound/' . now()->format('Y/m'), Str::uuid() . '.' . ($file->extension() ?: 'bin'), 'local');

        $message = $this->record($contact, [
            'type' => $type,
            'body' => $caption ? mb_substr($caption, 0, 1024) : null,
            'media_mime' => $mime,
            'media_filename' => $filename,
            'media_path' => $path,
            'sent_by' => $sender?->id,
            'source' => 'manual',
            'status' => 'sending',
        ]);

        return $this->deliver($message);
    }

    /**
     * Attempt delivery of a queued, sending or failed message.
     */
    public function deliver(WhatsAppMessage $message): WhatsAppMessage
    {
        $contact = $message->contact;
        $message->forceFill(['status' => 'sending', 'attempts' => $message->attempts + 1])->save();

        try {
            $result = match ($message->type) {
                'text' => $this->api->sendText($contact->wa_id, (string) $message->body, $message->context_wamid),
                'template' => $this->api->sendTemplate(
                    $contact->wa_id,
                    (string) $message->template_name,
                    (string) $message->template_language,
                    $this->templateComponents($message),
                ),
                default => $this->deliverMedia($message),
            };

            $message->forceFill([
                'wamid' => $result['wamid'],
                'status' => 'sent',
                'sent_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $permanent = $exception instanceof WhatsAppApiException ? $exception->isPermanent() : false;
            $message->forceFill([
                'status' => ($permanent || $message->attempts >= self::MAX_ATTEMPTS) ? 'failed' : 'queued',
                'error_code' => $exception instanceof WhatsAppApiException ? $exception->errorCode : null,
                'error_message' => WhatsAppCloudApi::safeMessage($exception),
                'failed_at' => now(),
            ])->save();
        }

        return $message->refresh();
    }

    /**
     * Process queued messages (broadcasts and retries). Called from the cron URL.
     */
    public function dispatchQueued(int $limit = 50): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'requeued' => 0];

        if (!$this->settings->ready()) {
            return $result;
        }

        WhatsAppMessage::with('contact')
            ->where('direction', 'outbound')
            ->where('status', 'queued')
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (WhatsAppMessage $message) use (&$result) {
                $status = $this->deliver($message)->status;
                $key = match ($status) {
                    'sent' => 'sent',
                    'failed' => 'failed',
                    default => 'requeued',
                };
                $result[$key]++;
            });

        return $result;
    }

    public function retry(WhatsAppMessage $message): WhatsAppMessage
    {
        $this->assertReady();

        if ($message->direction !== 'outbound' || !in_array($message->status, ['failed', 'queued'], true)) {
            throw new RuntimeException('Only failed or queued outbound messages can be retried.');
        }

        if ($message->type !== 'template' && !$message->contact->isServiceWindowOpen()) {
            throw new RuntimeException('The 24-hour service window has closed. Send a template instead of retrying this message.');
        }

        $message->forceFill(['attempts' => 0])->save();

        return $this->deliver($message);
    }

    public function markConversationRead(WhatsAppContact $contact): void
    {
        $latestInbound = $contact->messages()
            ->where('direction', 'inbound')
            ->where('status', 'received')
            ->latest('id')
            ->first();

        $contact->messages()->where('direction', 'inbound')->where('status', 'received')->update(['status' => 'read', 'read_at' => now()]);
        $contact->forceFill(['unread_count' => 0])->save();

        if ($latestInbound?->wamid && $this->settings->ready()) {
            try {
                $this->api->markAsRead($latestInbound->wamid);
            } catch (Throwable) {
                // Read receipts are best effort.
            }
        }
    }

    private function deliverMedia(WhatsAppMessage $message): array
    {
        if (!$message->media_id) {
            $absolute = Storage::disk('local')->path((string) $message->media_path);
            if (!$message->media_path || !is_file($absolute)) {
                throw new RuntimeException('The media file for this message is missing.');
            }

            $message->forceFill(['media_id' => $this->api->uploadMedia($absolute, (string) $message->media_mime, (string) $message->media_filename)])->save();
        }

        return $this->api->sendMedia($message->contact->wa_id, $message->type, $message->media_id, $message->body, $message->media_filename);
    }

    private function templateComponents(WhatsAppMessage $message): array
    {
        $params = (array) $message->template_params;
        $bodyValues = array_values((array) ($params['body'] ?? []));
        $headerValues = array_values((array) ($params['header'] ?? []));

        $template = WhatsAppTemplate::where('name', $message->template_name)->where('language', $message->template_language)->first();

        $bodyPlaceholders = $template ? $template->bodyPlaceholders() : array_map('strval', range(1, max(count($bodyValues), 1)));
        $headerPlaceholders = $template ? $template->headerPlaceholders() : [];

        if (!$template && $bodyValues === []) {
            $bodyPlaceholders = [];
        }

        return WhatsAppCloudApi::buildTemplateComponents($bodyPlaceholders, $bodyValues, $headerPlaceholders, $headerValues);
    }

    private function record(WhatsAppContact $contact, array $attributes): WhatsAppMessage
    {
        $message = $contact->messages()->create(array_merge(['direction' => 'outbound', 'attempts' => 0], $attributes));

        $contact->forceFill([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($message->preview, 120),
        ])->save();

        return $message;
    }

    private function assertReady(): void
    {
        if (!$this->settings->installed()) {
            throw new RuntimeException('WhatsApp is not installed yet. Install the v2.9.1 WhatsApp package first.');
        }

        if (!$this->settings->ready()) {
            throw new RuntimeException('WhatsApp Business is disabled or not configured. Check WhatsApp settings.');
        }
    }
}
