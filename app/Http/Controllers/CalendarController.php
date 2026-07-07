<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));

        try {
            $monthStart = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        } catch (\Throwable $exception) {
            $monthStart = now()->startOfMonth();
        }

        $monthEnd = $monthStart->copy()->endOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);
        $today = now()->toDateString();
        $currentMonthVisible = $monthStart->format('Y-m') === now()->format('Y-m');
        $user = $request->user();

        $availableTypes = $this->availableCalendarTypes($user);
        $filterSubmitted = $request->has('filter_submitted');
        $selectedTypes = $request->input('types');

        if (!$filterSubmitted || !is_array($selectedTypes)) {
            $selectedTypes = array_keys($availableTypes);
        } else {
            $selectedTypes = array_values(array_intersect($selectedTypes, array_keys($availableTypes)));
        }

        $selectedTypeLookup = array_fill_keys($selectedTypes, true);

        $eventsByDate = [];
        $undatedReminders = [];

        $publicHolidays = collect();
        if (Schema::hasTable('public_holidays')) {
            $publicHolidays = PublicHoliday::query()
                ->whereBetween('holiday_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
                ->orderBy('holiday_date')
                ->get()
                ->keyBy(fn (PublicHoliday $holiday) => $holiday->holiday_date->toDateString());

            foreach ($publicHolidays as $holiday) {
                $this->addDatedEvent($eventsByDate, $holiday->holiday_date->toDateString(), [
                    'type' => 'public_holiday',
                    'icon' => '🇿🇦',
                    'title' => $holiday->name ?: 'Public Holiday',
                    'subtitle' => ($holiday->is_company_closed ?? true) ? 'Company closed · attendance excluded' : 'Public holiday',
                    'status' => 'holiday',
                    'url' => null,
                ]);
            }
        }

        $leaveRequests = collect();
        if (Schema::hasTable('leave_requests') && $user->hasPermission('leave.view')) {
            $leaveRequests = LeaveRequest::with(['user', 'leaveType'])
                ->visibleTo($user)
                ->where(function ($query) use ($gridStart, $gridEnd) {
                    $query->whereBetween('start_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
                        ->orWhereBetween('end_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
                        ->orWhere(function ($nested) use ($gridStart, $gridEnd) {
                            $nested->whereDate('start_date', '<=', $gridStart->toDateString())
                                ->whereDate('end_date', '>=', $gridEnd->toDateString());
                        });
                })
                ->orderBy('start_date')
                ->orderBy('end_date')
                ->get();

            foreach ($leaveRequests as $leave) {
                $cursor = $leave->start_date->copy()->max($gridStart);
                $end = $leave->end_date->copy()->min($gridEnd);

                while ($cursor->lte($end)) {
                    $this->addDatedEvent($eventsByDate, $cursor->toDateString(), [
                        'type' => 'leave',
                        'icon' => '🌴',
                        'title' => $leave->user->name,
                        'subtitle' => (optional($leave->leaveType)->name ?? 'Leave') . ' · ' . $leave->status_label,
                        'status' => $leave->status,
                        'url' => route('leave.show', $leave),
                    ]);
                    $cursor->addDay();
                }
            }
        }

        if (Schema::hasTable('employee_documents') && $user->hasPermission('employee_documents.view')) {
            $employeeDocuments = EmployeeDocument::with('employee')
                ->where('status', 'active')
                ->where('has_expiry', true)
                ->where(function ($query) use ($gridStart, $gridEnd) {
                    $query->whereBetween('reminder_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
                        ->orWhereBetween('expires_at', [$gridStart->toDateString(), $gridEnd->toDateString()]);
                })
                ->orderBy('expires_at')
                ->limit(250)
                ->get();

            foreach ($employeeDocuments as $document) {
                $employeeName = optional($document->employee)->name ?: 'Employee';

                if ($document->reminder_date) {
                    $this->addDatedEvent($eventsByDate, $document->reminder_date->toDateString(), [
                        'type' => 'employee_document',
                        'icon' => '📄',
                        'title' => 'Employee document renewal',
                        'subtitle' => $employeeName . ' · ' . $document->type_label . ' · expires ' . optional($document->expires_at)->format('Y-m-d'),
                        'status' => 'reminder',
                        'url' => route('employee_documents.reminders'),
                    ]);
                }

                if ($document->expires_at && (!$document->reminder_date || !$document->expires_at->isSameDay($document->reminder_date))) {
                    $this->addDatedEvent($eventsByDate, $document->expires_at->toDateString(), [
                        'type' => 'employee_document',
                        'icon' => '⚠️',
                        'title' => 'Employee document expires',
                        'subtitle' => $employeeName . ' · ' . $document->type_label,
                        'status' => 'expired',
                        'url' => route('employee_documents.reminders', ['filter' => 'expired']),
                    ]);
                }
            }
        }

        if (Schema::hasTable('vehicle_documents') && $user->hasPermission('vehicle.documents.view')) {
            $vehicleDocuments = VehicleDocument::with('vehicle')
                ->where('status', 'active')
                ->where('has_expiry', true)
                ->where(function ($query) use ($gridStart, $gridEnd) {
                    $query->whereBetween('reminder_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
                        ->orWhereBetween('expires_at', [$gridStart->toDateString(), $gridEnd->toDateString()]);
                })
                ->orderBy('expires_at')
                ->limit(250)
                ->get();

            foreach ($vehicleDocuments as $document) {
                $vehicleName = optional($document->vehicle)->display_name ?: 'Vehicle';

                if ($document->reminder_date) {
                    $this->addDatedEvent($eventsByDate, $document->reminder_date->toDateString(), [
                        'type' => 'vehicle_document',
                        'icon' => '🚗',
                        'title' => 'Vehicle document renewal',
                        'subtitle' => $vehicleName . ' · ' . $document->type_label . ' · expires ' . optional($document->expires_at)->format('Y-m-d'),
                        'status' => 'reminder',
                        'url' => route('vehicles.reminders'),
                    ]);
                }

                if ($document->expires_at && (!$document->reminder_date || !$document->expires_at->isSameDay($document->reminder_date))) {
                    $this->addDatedEvent($eventsByDate, $document->expires_at->toDateString(), [
                        'type' => 'vehicle_document',
                        'icon' => '⚠️',
                        'title' => 'Vehicle document expires',
                        'subtitle' => $vehicleName . ' · ' . $document->type_label,
                        'status' => 'expired',
                        'url' => route('vehicles.reminders', ['filter' => 'expired']),
                    ]);
                }
            }
        }

        if (Schema::hasTable('attendance_days') && Schema::hasColumn('attendance_days', 'is_late') && $user->hasAnyPermission(['attendance.view', 'attendance.late.view'])) {
            $attendanceDays = AttendanceDay::with('user')
                ->whereBetween('attendance_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
                ->where(function ($query) {
                    $query->where('is_late', true)
                        ->orWhereNull('end_time')
                        ->orWhereNotNull('anomalies');
                })
                ->workingDays()
                ->orderBy('attendance_date')
                ->limit(300)
                ->get();

            foreach ($attendanceDays as $attendanceDay) {
                $isSinglePunch = !$attendanceDay->end_time;
                $this->addDatedEvent($eventsByDate, $attendanceDay->attendance_date->toDateString(), [
                    'type' => 'attendance',
                    'icon' => $attendanceDay->is_late ? '⏱️' : '⚠️',
                    'title' => $attendanceDay->is_late ? 'Late clock-in' : 'Attendance check',
                    'subtitle' => optional($attendanceDay->user)->name . ' · ' . ($isSinglePunch ? 'single punch / no checkout' : $attendanceDay->late_label),
                    'status' => $attendanceDay->is_late ? 'late' : 'warning',
                    'url' => route('attendance.show', $attendanceDay),
                ]);
            }
        }

        if (Schema::hasTable('vehicles') && $user->hasPermission('vehicle.service.reminders.view')) {
            $vehicles = Vehicle::active()->with(['serviceRecords', 'fuelUps'])->orderBy('make')->orderBy('model')->get();

            foreach ($vehicles as $vehicle) {
                $service = $vehicle->service_summary;

                if (in_array($service['state'], ['due-soon', 'overdue', 'no-baseline'], true)) {
                    $event = [
                        'type' => 'vehicle_service',
                        'icon' => '🔧',
                        'title' => $service['label'],
                        'subtitle' => $vehicle->display_name . ($service['km_remaining'] !== null ? ' · ' . number_format((int) $service['km_remaining']) . ' km remaining' : ' · service baseline required'),
                        'status' => $service['state'],
                        'url' => route('vehicles.show', $vehicle),
                    ];

                    $undatedReminders[] = $event;

                    if ($currentMonthVisible) {
                        $this->addDatedEvent($eventsByDate, $today, $event);
                    }
                }
            }
        }

        if (Schema::hasTable('vehicles') && Schema::hasColumn('vehicles', 'tracking_last_sync_at') && Schema::hasColumn('vehicles', 'cartrack_vehicle_id') && $user->hasPermission('vehicle_tracking.view')) {
            $trackingCutoff = now()->subDay();
            $trackingVehicles = Vehicle::active()
                ->where(function ($query) use ($trackingCutoff) {
                    $query->whereNotNull('cartrack_vehicle_id')
                        ->where(function ($nested) use ($trackingCutoff) {
                            $nested->whereNull('tracking_last_sync_at')
                                ->orWhere('tracking_last_sync_at', '<', $trackingCutoff);
                        });
                })
                ->orderBy('make')
                ->orderBy('model')
                ->limit(100)
                ->get();

            foreach ($trackingVehicles as $vehicle) {
                $event = [
                    'type' => 'tracking',
                    'icon' => '🛰️',
                    'title' => 'Tracking sync due',
                    'subtitle' => $vehicle->display_name . ' · last sync ' . ($vehicle->tracking_last_sync_at ? $vehicle->tracking_last_sync_at->diffForHumans() : 'never'),
                    'status' => 'warning',
                    'url' => route('vehicle_tracking.index'),
                ];

                $undatedReminders[] = $event;

                if ($currentMonthVisible) {
                    $this->addDatedEvent($eventsByDate, $today, $event);
                }
            }
        }

        $eventsByDate = $this->filterEventsByType($eventsByDate, $selectedTypeLookup);
        $undatedReminders = array_values(array_filter($undatedReminders, fn (array $event) => isset($selectedTypeLookup[$event['type'] ?? ''])));
        $summary = $this->summariseEvents($eventsByDate, $undatedReminders);

        $days = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $date = $cursor->toDateString();
            $dayEvents = collect($eventsByDate[$date] ?? [])->sortBy(function ($event) {
                return match ($event['type'] ?? '') {
                    'public_holiday' => 0,
                    'attendance' => 1,
                    'leave' => 2,
                    'employee_document', 'vehicle_document' => 3,
                    'vehicle_service' => 4,
                    'tracking' => 5,
                    default => 9,
                };
            })->values();

            $days[$date] = [
                'date' => $cursor->copy(),
                'isCurrentMonth' => $cursor->month === $monthStart->month,
                'isToday' => $date === $today,
                'events' => $dayEvents,
            ];
            $cursor->addDay();
        }

        $calendarEvents = collect($eventsByDate)->flatten(1)->values();

        return view('calendar.index', [
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'gridStart' => $gridStart,
            'gridEnd' => $gridEnd,
            'days' => $days,
            'leaveRequests' => $leaveRequests,
            'publicHolidays' => $publicHolidays->filter(fn ($holiday) => $holiday->holiday_date->betweenIncluded($monthStart, $monthEnd)),
            'calendarEvents' => $calendarEvents,
            'undatedReminders' => collect($undatedReminders),
            'summary' => $summary,
            'availableTypes' => $availableTypes,
            'selectedTypes' => $selectedTypes,
            'selectedTypeLookup' => $selectedTypeLookup,
            'filterSubmitted' => $filterSubmitted,
            'previousMonth' => $monthStart->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $monthStart->copy()->addMonth()->format('Y-m'),
            'calendarQuery' => [
                'types' => $selectedTypes,
                'filter_submitted' => $filterSubmitted ? 1 : null,
            ],
        ]);
    }

    private function addDatedEvent(array &$eventsByDate, string $date, array $event): void
    {
        $eventsByDate[$date] ??= [];
        $eventsByDate[$date][] = $event;
    }

    private function availableCalendarTypes($user): array
    {
        $types = [
            'public_holiday' => ['label' => 'Public Holidays', 'icon' => '🇿🇦'],
        ];

        if ($user->hasPermission('leave.view')) {
            $types['leave'] = ['label' => 'Leave', 'icon' => '🌴'];
        }
        if ($user->hasPermission('employee_documents.view')) {
            $types['employee_document'] = ['label' => 'Employee Documents', 'icon' => '📄'];
        }
        if ($user->hasPermission('vehicle.documents.view')) {
            $types['vehicle_document'] = ['label' => 'Vehicle Documents', 'icon' => '🚗'];
        }
        if ($user->hasAnyPermission(['attendance.view', 'attendance.late.view'])) {
            $types['attendance'] = ['label' => 'Attendance', 'icon' => '⏱️'];
        }
        if ($user->hasPermission('vehicle.service.reminders.view')) {
            $types['vehicle_service'] = ['label' => 'Vehicle Services', 'icon' => '🔧'];
        }
        if ($user->hasPermission('vehicle_tracking.view')) {
            $types['tracking'] = ['label' => 'Tracking Sync', 'icon' => '🛰️'];
        }

        return $types;
    }

    private function filterEventsByType(array $eventsByDate, array $selectedTypeLookup): array
    {
        $filtered = [];

        foreach ($eventsByDate as $date => $events) {
            $dateEvents = array_values(array_filter($events, fn (array $event) => isset($selectedTypeLookup[$event['type'] ?? ''])));
            if ($dateEvents) {
                $filtered[$date] = $dateEvents;
            }
        }

        return $filtered;
    }

    private function summariseEvents(array $eventsByDate, array $undatedReminders): array
    {
        $summary = [
            'public_holiday' => 0,
            'leave' => 0,
            'employee_document' => 0,
            'vehicle_document' => 0,
            'vehicle_service' => 0,
            'attendance' => 0,
            'tracking' => 0,
        ];

        foreach (collect($eventsByDate)->flatten(1) as $event) {
            if (isset($summary[$event['type'] ?? ''])) {
                $summary[$event['type']]++;
            }
        }

        foreach ($undatedReminders as $event) {
            if (isset($summary[$event['type'] ?? ''])) {
                $summary[$event['type']]++;
            }
        }

        return $summary;
    }
}
