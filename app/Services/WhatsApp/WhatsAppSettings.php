<?php

namespace App\Services\WhatsApp;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WhatsAppSettings
{
    public const GROUP = 'WhatsApp';

    /**
     * Setting definitions seeded by the v2.9.1 package migration. Secrets are stored encrypted with the APP_KEY.
     */
    public const DEFINITIONS = [
        'whatsapp_enabled' => ['label' => 'Enable WhatsApp Business', 'type' => 'boolean', 'value' => '0', 'description' => 'Master switch for sending and receiving WhatsApp messages.'],
        'whatsapp_api_version' => ['label' => 'Graph API Version', 'type' => 'text', 'value' => 'v23.0', 'description' => 'Meta Graph API version used for WhatsApp Cloud API calls.'],
        'whatsapp_phone_number_id' => ['label' => 'Phone Number ID', 'type' => 'text', 'value' => null, 'description' => 'WhatsApp Business phone number ID from Meta App Dashboard > WhatsApp > API Setup.'],
        'whatsapp_business_account_id' => ['label' => 'WhatsApp Business Account ID', 'type' => 'text', 'value' => null, 'description' => 'WABA ID, used to sync message templates.'],
        'whatsapp_access_token' => ['label' => 'Access Token', 'type' => 'secret', 'value' => null, 'description' => 'Permanent System User access token with whatsapp_business_messaging and whatsapp_business_management.'],
        'whatsapp_app_secret' => ['label' => 'App Secret', 'type' => 'secret', 'value' => null, 'description' => 'Meta App Secret used to verify webhook signatures.'],
        'whatsapp_webhook_verify_token' => ['label' => 'Webhook Verify Token', 'type' => 'text', 'value' => null, 'description' => 'Token entered in Meta when subscribing the webhook.'],
        'whatsapp_default_country_code' => ['label' => 'Default Country Code', 'type' => 'text', 'value' => '27', 'description' => 'Used to convert local numbers such as 082 123 4567 into international format.'],
        'whatsapp_cron_key' => ['label' => 'WhatsApp Cron Key', 'type' => 'text', 'value' => null, 'description' => 'Protects the public shared-hosting cron URL.'],
        'whatsapp_require_opt_in' => ['label' => 'Require Opt-in for Notifications', 'type' => 'boolean', 'value' => '1', 'description' => 'Only send business-initiated template messages to contacts with a recorded opt-in.'],
        'whatsapp_auto_reply_enabled' => ['label' => 'Auto-reply to New Conversations', 'type' => 'boolean', 'value' => '0', 'description' => 'Send an acknowledgement when a contact messages after 24 hours of silence.'],
        'whatsapp_auto_reply_message' => ['label' => 'Auto-reply Message', 'type' => 'textarea', 'value' => 'Thank you for contacting ISO. Your message has been received and a team member will respond shortly.', 'description' => 'Free-form text sent as the auto-reply.'],
        'whatsapp_template_language' => ['label' => 'Notification Template Language', 'type' => 'text', 'value' => 'en', 'description' => 'Language code of the notification templates, for example en or en_US.'],
        'whatsapp_notify_leave_enabled' => ['label' => 'Leave Decision Notifications', 'type' => 'boolean', 'value' => '0', 'description' => 'Notify employees when leave is approved or declined.'],
        'whatsapp_leave_template' => ['label' => 'Leave Decision Template', 'type' => 'text', 'value' => 'leave_request_update', 'description' => 'Body parameters: {{1}} employee name, {{2}} leave type, {{3}} dates, {{4}} decision.'],
        'whatsapp_notify_documents_enabled' => ['label' => 'Employee Document Expiry Notifications', 'type' => 'boolean', 'value' => '0', 'description' => 'Notify employees when their documents reach the reminder date.'],
        'whatsapp_document_template' => ['label' => 'Employee Document Template', 'type' => 'text', 'value' => 'document_expiry_reminder', 'description' => 'Body parameters: {{1}} employee name, {{2}} document, {{3}} expiry date.'],
        'whatsapp_notify_vehicle_documents_enabled' => ['label' => 'Vehicle Document Expiry Notifications', 'type' => 'boolean', 'value' => '0', 'description' => 'Notify the assigned driver when a vehicle document reaches the reminder date.'],
        'whatsapp_vehicle_document_template' => ['label' => 'Vehicle Document Template', 'type' => 'text', 'value' => 'vehicle_document_reminder', 'description' => 'Body parameters: {{1}} driver name, {{2}} vehicle, {{3}} document, {{4}} expiry date.'],
        'whatsapp_last_webhook_at' => ['label' => 'Last Webhook Received', 'type' => 'text', 'value' => null, 'description' => 'Diagnostic: last time Meta delivered a webhook.'],
        'whatsapp_last_cron_at' => ['label' => 'Last Cron Run', 'type' => 'text', 'value' => null, 'description' => 'Diagnostic: last time the WhatsApp cron URL ran.'],
        'whatsapp_last_cron_message' => ['label' => 'Last Cron Result', 'type' => 'text', 'value' => null, 'description' => 'Diagnostic: summary of the last cron run.'],
    ];

    private ?array $cache = null;

    public function tableReady(): bool
    {
        try {
            return Schema::hasTable('system_settings');
        } catch (Throwable) {
            return false;
        }
    }

    public function installed(): bool
    {
        try {
            return $this->tableReady()
                && Schema::hasTable('whatsapp_contacts')
                && Schema::hasTable('whatsapp_messages');
        } catch (Throwable) {
            return false;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();
        $value = $values[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        if ((self::DEFINITIONS[$key]['type'] ?? null) === 'secret') {
            try {
                return Crypt::decryptString($value);
            } catch (Throwable) {
                return $default;
            }
        }

        return $value;
    }

    public function bool(string $key): bool
    {
        return filter_var($this->get($key, '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public function hasValue(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function set(string $key, mixed $value): void
    {
        if (!$this->tableReady()) {
            return;
        }

        $definition = self::DEFINITIONS[$key] ?? ['label' => $key, 'type' => 'text', 'description' => null];
        $stored = $value === null || $value === '' ? null : (string) $value;

        if ($stored !== null && $definition['type'] === 'secret') {
            $stored = Crypt::encryptString($stored);
        }

        $setting = SystemSetting::firstOrNew(['key' => $key]);
        $setting->fill([
            'group' => self::GROUP,
            'label' => $definition['label'],
            'type' => $definition['type'] === 'secret' ? 'password' : $definition['type'],
            'description' => $definition['description'],
            'sort_order' => $setting->sort_order ?: (array_search($key, array_keys(self::DEFINITIONS), true) + 1) * 10,
            'is_core' => true,
        ]);
        $setting->value = $stored;
        $setting->save();

        $this->cache = null;
    }

    public function enabled(): bool
    {
        return $this->installed() && $this->bool('whatsapp_enabled');
    }

    public function configured(): bool
    {
        return $this->hasValue('whatsapp_phone_number_id') && $this->hasValue('whatsapp_access_token');
    }

    public function ready(): bool
    {
        return $this->enabled() && $this->configured();
    }

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (!$this->tableReady()) {
            return [];
        }

        return $this->cache = SystemSetting::where('group', self::GROUP)->pluck('value', 'key')->all();
    }

    public function flush(): void
    {
        $this->cache = null;
    }
}
