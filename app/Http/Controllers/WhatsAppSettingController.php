<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\WhatsAppCloudApi;
use App\Services\WhatsApp\WhatsAppMessenger;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Http\Request;
use Throwable;

class WhatsAppSettingController extends Controller
{
    private const BOOLEANS = [
        'whatsapp_enabled',
        'whatsapp_require_opt_in',
        'whatsapp_auto_reply_enabled',
        'whatsapp_notify_leave_enabled',
        'whatsapp_notify_documents_enabled',
        'whatsapp_notify_vehicle_documents_enabled',
    ];

    public function index(WhatsAppSettings $settings)
    {
        abort_unless($settings->installed(), 404, 'Install the v2.9.1 WhatsApp package first.');

        return view('whatsapp.settings', [
            'settings' => $settings,
            'webhookUrl' => route('whatsapp.webhook.receive'),
            'cronUrl' => route('whatsapp.cron', ['key' => $settings->get('whatsapp_cron_key', 'set-a-cron-key')]),
            'canManage' => request()->user()->hasPermission('whatsapp.settings.manage'),
        ]);
    }

    public function update(Request $request, WhatsAppSettings $settings)
    {
        abort_unless($settings->installed(), 404, 'Install the v2.9.1 WhatsApp package first.');

        $data = $request->validate([
            'whatsapp_api_version' => ['required', 'regex:/^v\d+\.\d+$/'],
            'whatsapp_phone_number_id' => ['nullable', 'regex:/^\d{5,30}$/'],
            'whatsapp_business_account_id' => ['nullable', 'regex:/^\d{5,30}$/'],
            'whatsapp_access_token' => ['nullable', 'string', 'max:1000'],
            'whatsapp_app_secret' => ['nullable', 'string', 'max:255'],
            'whatsapp_webhook_verify_token' => ['required', 'string', 'min:12', 'max:120'],
            'whatsapp_default_country_code' => ['required', 'regex:/^\d{1,4}$/'],
            'whatsapp_cron_key' => ['required', 'string', 'min:12', 'max:120', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'whatsapp_auto_reply_message' => ['nullable', 'string', 'max:1000'],
            'whatsapp_template_language' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z_]+$/'],
            'whatsapp_leave_template' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9_]+$/'],
            'whatsapp_document_template' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9_]+$/'],
            'whatsapp_vehicle_document_template' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9_]+$/'],
        ], [
            'regex' => 'The :attribute format is invalid.',
        ]);

        // Secrets are never echoed back to the browser: blank means keep the stored value.
        foreach (['whatsapp_access_token', 'whatsapp_app_secret'] as $secret) {
            if (trim((string) ($data[$secret] ?? '')) === '' && !$request->boolean($secret . '_clear')) {
                unset($data[$secret]);
            }
        }

        foreach (self::BOOLEANS as $key) {
            $data[$key] = $request->boolean($key) ? '1' : '0';
        }

        foreach ($data as $key => $value) {
            $settings->set($key, is_string($value) ? trim($value) : $value);
        }

        return redirect()->route('whatsapp.settings')->with('success', 'WhatsApp Business settings updated.');
    }

    public function test(WhatsAppCloudApi $api, WhatsAppSettings $settings)
    {
        try {
            $info = $api->phoneNumberInfo();
        } catch (Throwable $exception) {
            return redirect()->route('whatsapp.settings')->withErrors(['whatsapp' => $exception->getMessage()]);
        }

        $message = 'Connected to ' . ($info['verified_name'] ?? 'WhatsApp Business') . ' on ' . ($info['display_phone_number'] ?? 'the configured number')
            . '. Quality rating: ' . ($info['quality_rating'] ?? 'unknown') . '.';

        if (!$settings->bool('whatsapp_enabled')) {
            return redirect()->route('whatsapp.settings')->with('warning', $message . ' The integration is still disabled; tick Enable WhatsApp Business to start messaging.');
        }

        return redirect()->route('whatsapp.settings')->with('success', $message);
    }

    public function sendTest(Request $request, WhatsAppMessenger $messenger)
    {
        $data = $request->validate([
            'test_number' => ['required', 'string', 'max:30'],
        ]);

        try {
            $contact = $messenger->contactForNumber($data['test_number']);
            // hello_world is pre-approved on every WhatsApp Business Account.
            $message = $messenger->sendTemplate($contact, 'hello_world', [], 'en_US', $request->user());
        } catch (Throwable $exception) {
            return redirect()->route('whatsapp.settings')->withErrors(['whatsapp' => $exception->getMessage()]);
        }

        if ($message->status === 'failed' || $message->status === 'queued') {
            return redirect()->route('whatsapp.settings')->withErrors(['whatsapp' => 'Test message failed: ' . $message->error_message]);
        }

        return redirect()->route('whatsapp.settings')->with('success', 'Test template hello_world sent to ' . $contact->phone_label . '.');
    }
}
