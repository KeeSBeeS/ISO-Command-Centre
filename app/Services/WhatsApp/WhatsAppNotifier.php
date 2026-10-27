<?php

namespace App\Services\WhatsApp;

use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\VehicleDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Business-initiated WhatsApp notifications for platform events. Every notification uses an approved
 * template and a notification key so the same event is never sent twice.
 */
class WhatsAppNotifier
{
    public function __construct(private WhatsAppSettings $settings, private WhatsAppMessenger $messenger)
    {
    }

    public function leaveDecision(LeaveRequest $leave): void
    {
        if (!in_array($leave->status, ['approved', 'declined'], true)
            || !$this->settings->ready()
            || !$this->settings->bool('whatsapp_notify_leave_enabled')) {
            return;
        }

        $template = (string) $this->settings->get('whatsapp_leave_template', '');
        $user = $leave->user;
        if ($template === '' || !$user) {
            return;
        }

        $this->notifyUser($user, $template, [
            $user->name,
            optional($leave->leaveType)->name ?? 'Leave',
            $leave->date_range_label,
            ucfirst($leave->status),
        ], 'leave:' . $leave->id . ':' . $leave->status . ':' . optional($leave->reviewed_at)->timestamp);
    }

    public function employeeDocumentReminders(): int
    {
        if (!$this->settings->ready()
            || !$this->settings->bool('whatsapp_notify_documents_enabled')
            || !Schema::hasTable('employee_documents')) {
            return 0;
        }

        $template = (string) $this->settings->get('whatsapp_document_template', '');
        if ($template === '') {
            return 0;
        }

        $sent = 0;
        EmployeeDocument::with('employee.profile')
            ->reminderDue()
            ->whereDate('expires_at', '>=', now()->subDays(30)->toDateString())
            ->limit(200)
            ->get()
            ->each(function (EmployeeDocument $document) use ($template, &$sent) {
                if (!$document->employee || $document->employee->status !== 'active') {
                    return;
                }

                $sent += (int) $this->notifyUser($document->employee, $template, [
                    $document->employee->name,
                    $document->title ?: $document->type_label,
                    optional($document->expires_at)->format('d M Y'),
                ], 'employee_document:' . $document->id . ':' . optional($document->expires_at)->toDateString());
            });

        return $sent;
    }

    public function vehicleDocumentReminders(): int
    {
        if (!$this->settings->ready()
            || !$this->settings->bool('whatsapp_notify_vehicle_documents_enabled')
            || !Schema::hasTable('vehicle_documents')
            || !Schema::hasTable('vehicle_assignments')) {
            return 0;
        }

        $template = (string) $this->settings->get('whatsapp_vehicle_document_template', '');
        if ($template === '') {
            return 0;
        }

        $sent = 0;
        VehicleDocument::with('vehicle.currentAssignment.user.profile')
            ->where('status', 'active')
            ->where('has_expiry', true)
            ->whereNotNull('reminder_date')
            ->whereDate('reminder_date', '<=', now()->toDateString())
            ->whereDate('expires_at', '>=', now()->subDays(30)->toDateString())
            ->limit(200)
            ->get()
            ->each(function (VehicleDocument $document) use ($template, &$sent) {
                $vehicle = $document->vehicle;
                $driver = $vehicle?->currentAssignment?->user;
                if (!$driver || $driver->status !== 'active') {
                    return;
                }

                $sent += (int) $this->notifyUser($driver, $template, [
                    $driver->name,
                    trim($vehicle->make . ' ' . $vehicle->model . ' ' . ($vehicle->registration_number ? '(' . $vehicle->registration_number . ')' : '')),
                    $document->title ?: ucfirst(str_replace('_', ' ', (string) $document->document_type)),
                    optional($document->expires_at)->format('d M Y'),
                ], 'vehicle_document:' . $document->id . ':' . optional($document->expires_at)->toDateString() . ':' . $driver->id);
            });

        return $sent;
    }

    private function notifyUser(User $user, string $template, array $params, string $notificationKey): bool
    {
        try {
            $contact = $this->messenger->contactForUser($user);
            if (!$contact) {
                return false;
            }

            $message = $this->messenger->sendTemplate($contact, $template, $params, null, null, 'notification', $notificationKey);

            return $message !== null && $message->status !== 'failed';
        } catch (Throwable $exception) {
            Log::warning('WhatsApp notification failed', ['key' => $notificationKey, 'error' => $exception->getMessage()]);

            return false;
        }
    }
}
