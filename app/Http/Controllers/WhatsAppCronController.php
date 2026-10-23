<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsApp\WhatsAppBroadcaster;
use App\Services\WhatsApp\WhatsAppMessenger;
use App\Services\WhatsApp\WhatsAppNotifier;
use App\Services\WhatsApp\WhatsAppSettings;
use Throwable;

class WhatsAppCronController extends Controller
{
    public function run(string $key, WhatsAppSettings $settings, WhatsAppMessenger $messenger, WhatsAppNotifier $notifier, WhatsAppBroadcaster $broadcaster)
    {
        $expected = $settings->installed() ? (string) $settings->get('whatsapp_cron_key', '') : '';
        abort_unless($expected !== '' && hash_equals($expected, $key), 403);

        try {
            $result = [
                'queued' => $messenger->dispatchQueued(100),
                'employee_document_reminders' => $notifier->employeeDocumentReminders(),
                'vehicle_document_reminders' => $notifier->vehicleDocumentReminders(),
                'webhook_events_pruned' => WhatsAppWebhookEvent::where('created_at', '<', now()->subDays(14))->delete(),
            ];
            $broadcaster->refreshAll();
        } catch (Throwable $exception) {
            $settings->set('whatsapp_last_cron_at', now()->toDateTimeString());
            $settings->set('whatsapp_last_cron_message', 'Failed: ' . mb_substr($exception->getMessage(), 0, 400));

            return response()->json(['ok' => false, 'error' => $exception->getMessage()], 500);
        }

        $settings->set('whatsapp_last_cron_at', now()->toDateTimeString());
        $settings->set('whatsapp_last_cron_message', sprintf(
            'Sent %d queued (%d failed, %d retrying). Reminders: %d employee, %d vehicle.',
            $result['queued']['sent'],
            $result['queued']['failed'],
            $result['queued']['requeued'],
            $result['employee_document_reminders'],
            $result['vehicle_document_reminders'],
        ));

        return response()->json(['ok' => true] + $result);
    }
}
