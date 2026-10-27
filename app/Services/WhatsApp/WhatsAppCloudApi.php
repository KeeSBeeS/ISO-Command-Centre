<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin client for the Meta WhatsApp Cloud API (Graph API).
 */
class WhatsAppCloudApi
{
    public const BASE_URL = 'https://graph.facebook.com';

    public function __construct(private WhatsAppSettings $settings)
    {
    }

    public function sendText(string $to, string $body, ?string $replyToWamid = null, bool $previewUrl = true): array
    {
        $payload = [
            'type' => 'text',
            'text' => ['body' => $body, 'preview_url' => $previewUrl],
        ];

        return $this->sendMessage($to, $payload, $replyToWamid);
    }

    /**
     * @param array $components Graph API template components, see buildTemplateComponents().
     */
    public function sendTemplate(string $to, string $name, string $language, array $components = []): array
    {
        $template = ['name' => $name, 'language' => ['code' => $language]];
        if ($components !== []) {
            $template['components'] = $components;
        }

        return $this->sendMessage($to, ['type' => 'template', 'template' => $template]);
    }

    public function sendMedia(string $to, string $type, string $mediaId, ?string $caption = null, ?string $filename = null): array
    {
        $media = ['id' => $mediaId];
        if ($caption !== null && $caption !== '' && in_array($type, ['image', 'video', 'document'], true)) {
            $media['caption'] = $caption;
        }
        if ($filename !== null && $type === 'document') {
            $media['filename'] = $filename;
        }

        return $this->sendMessage($to, ['type' => $type, $type => $media]);
    }

    public function markAsRead(string $wamid): void
    {
        $this->post($this->phonePath('messages'), [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $wamid,
        ]);
    }

    public function uploadMedia(string $path, string $mimeType, string $filename): string
    {
        $response = $this->client()
            ->attach('file', fopen($path, 'r'), $filename, ['Content-Type' => $mimeType])
            ->post($this->url($this->phonePath('media')), [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);

        $data = $this->decode($response);
        if (empty($data['id'])) {
            throw new WhatsAppApiException('Media upload did not return a media ID.');
        }

        return (string) $data['id'];
    }

    /**
     * Download inbound media. Media URLs are short lived, so the URL is resolved and fetched in one go.
     *
     * @return array{contents: string, mime_type: ?string}
     */
    public function downloadMedia(string $mediaId): array
    {
        $meta = $this->decode($this->client()->get($this->url($mediaId)));
        if (empty($meta['url'])) {
            throw new WhatsAppApiException('Media URL was not returned by WhatsApp.');
        }

        $response = $this->client()->get($meta['url']);
        if (!$response->successful()) {
            throw new WhatsAppApiException('Media download failed with HTTP ' . $response->status() . '.');
        }

        return ['contents' => $response->body(), 'mime_type' => $meta['mime_type'] ?? $response->header('Content-Type')];
    }

    public function fetchTemplates(): array
    {
        $wabaId = (string) $this->settings->get('whatsapp_business_account_id', '');
        if ($wabaId === '') {
            throw new WhatsAppApiException('Set the WhatsApp Business Account ID before syncing templates.');
        }

        $templates = [];
        $url = $this->url($wabaId . '/message_templates');
        $query = ['limit' => 100, 'fields' => 'id,name,language,status,category,components,parameter_format'];

        for ($page = 0; $page < 50 && $url; $page++) {
            $data = $this->decode($this->client()->get($url, $query));
            $templates = array_merge($templates, $data['data'] ?? []);
            $url = $data['paging']['next'] ?? null;
            $query = [];
        }

        return $templates;
    }

    public function phoneNumberInfo(): array
    {
        return $this->decode($this->client()->get($this->url($this->phonePath()), [
            'fields' => 'display_phone_number,verified_name,quality_rating,code_verification_status,platform_type,throughput',
        ]));
    }

    /**
     * Build template components for body (and optional text header) parameters.
     * Placeholder names are numeric for positional templates or names for NAMED templates.
     */
    public static function buildTemplateComponents(array $bodyPlaceholders, array $bodyValues, array $headerPlaceholders = [], array $headerValues = []): array
    {
        $components = [];

        $build = function (array $placeholders, array $values): array {
            $parameters = [];
            foreach ($placeholders as $index => $name) {
                $parameter = ['type' => 'text', 'text' => self::cleanParameter($values[$index] ?? '')];
                if (!ctype_digit((string) $name)) {
                    $parameter['parameter_name'] = $name;
                }
                $parameters[] = $parameter;
            }

            return $parameters;
        };

        if ($headerPlaceholders !== []) {
            $components[] = ['type' => 'header', 'parameters' => $build($headerPlaceholders, $headerValues)];
        }

        if ($bodyPlaceholders !== []) {
            $components[] = ['type' => 'body', 'parameters' => $build($bodyPlaceholders, $bodyValues)];
        }

        return $components;
    }

    /**
     * Template parameters may not be empty, contain new lines/tabs or more than four consecutive spaces.
     */
    public static function cleanParameter(mixed $value): string
    {
        $value = preg_replace('/[\r\n\t]+/', ' ', (string) $value);
        $value = trim(preg_replace('/ {4,}/', '   ', $value));

        return $value === '' ? '-' : mb_substr($value, 0, 1024);
    }

    private function sendMessage(string $to, array $payload, ?string $replyToWamid = null): array
    {
        $body = array_merge([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
        ], $payload);

        if ($replyToWamid) {
            $body['context'] = ['message_id' => $replyToWamid];
        }

        $data = $this->post($this->phonePath('messages'), $body);
        $wamid = $data['messages'][0]['id'] ?? null;
        if (!$wamid) {
            throw new WhatsAppApiException('WhatsApp accepted the request but did not return a message ID.');
        }

        return ['wamid' => $wamid, 'wa_id' => $data['contacts'][0]['wa_id'] ?? null, 'raw' => $data];
    }

    private function post(string $path, array $body): array
    {
        return $this->decode($this->client()->asJson()->post($this->url($path), $body));
    }

    private function client(): PendingRequest
    {
        $token = (string) $this->settings->get('whatsapp_access_token', '');
        if ($token === '') {
            throw new WhatsAppApiException('WhatsApp access token is not configured.');
        }

        return Http::withToken($token)->acceptJson()->timeout(30)->connectTimeout(10);
    }

    private function decode(Response $response): array
    {
        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if ($response->successful() && !isset($data['error'])) {
            return $data;
        }

        $error = $data['error'] ?? [];
        $message = (string) ($error['error_data']['details'] ?? $error['message'] ?? ('HTTP ' . $response->status()));
        $code = isset($error['code']) ? (string) $error['code'] : (string) $response->status();

        throw new WhatsAppApiException('WhatsApp API error ' . $code . ': ' . $message, $code, $error);
    }

    private function phonePath(string $suffix = ''): string
    {
        $phoneId = (string) $this->settings->get('whatsapp_phone_number_id', '');
        if ($phoneId === '') {
            throw new WhatsAppApiException('WhatsApp phone number ID is not configured.');
        }

        return trim($phoneId . '/' . $suffix, '/');
    }

    private function url(string $path): string
    {
        if (str_starts_with($path, 'https://')) {
            return $path;
        }

        $version = trim((string) $this->settings->get('whatsapp_api_version', 'v23.0'), '/') ?: 'v23.0';

        return self::BASE_URL . '/' . $version . '/' . ltrim($path, '/');
    }

    public static function safeMessage(Throwable $exception): string
    {
        return mb_substr($exception->getMessage(), 0, 1000);
    }
}
