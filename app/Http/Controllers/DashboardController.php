<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\AttendanceImport;
use App\Models\DashboardWidgetPreference;
use App\Models\Department;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\QuickActionPreference;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleFuelUp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $metrics = $this->metrics($user);
        $availableWidgets = $this->availableWidgets($user, $metrics);
        $dashboardWidgets = $this->resolvedWidgets($user, $availableWidgets);
        $availableQuickActions = $this->availableQuickActions($user, $metrics);
        $quickActions = $this->resolvedQuickActions($user, $availableQuickActions);

        return view('dashboard', array_merge($metrics, [
            'dashboardWidgets' => $dashboardWidgets,
            'dashboardPreferencesReady' => Schema::hasTable('dashboard_widget_preferences'),
            'quickActions' => $quickActions,
            'quickActionPreferencesReady' => Schema::hasTable('quick_action_preferences'),
        ]));
    }

    public function edit(Request $request)
    {
        abort_unless(Schema::hasTable('dashboard_widget_preferences'), 404, 'Run the v1.5 update first.');

        $user = $request->user();
        $metrics = $this->metrics($user);
        $availableWidgets = $this->availableWidgets($user, $metrics);
        $dashboardWidgets = $this->resolvedWidgets($user, $availableWidgets, true);

        return view('dashboard_edit', [
            'dashboardWidgets' => $dashboardWidgets,
            'sizes' => $this->sizes(),
            'quickActionPreferencesReady' => Schema::hasTable('quick_action_preferences'),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless(Schema::hasTable('dashboard_widget_preferences'), 404, 'Run the v1.5 update first.');

        $user = $request->user();
        $available = collect($this->availableWidgets($user, $this->metrics($user)))->keyBy('key');

        $data = $request->validate([
            'widgets' => ['nullable', 'array'],
            'widgets.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:500'],
            'widgets.*.size' => ['nullable', 'in:small,medium,large'],
            'widgets.*.visible' => ['nullable', 'boolean'],
        ]);

        foreach ($available as $key => $widget) {
            $input = $data['widgets'][$key] ?? [];

            DashboardWidgetPreference::updateOrCreate(
                ['user_id' => $user->id, 'widget_key' => $key],
                [
                    'sort_order' => (int) ($input['sort_order'] ?? $widget['default_order']),
                    'size' => $input['size'] ?? $widget['default_size'],
                    'is_visible' => array_key_exists('visible', $input),
                ]
            );
        }

        return redirect()->route('dashboard')->with('success', 'Dashboard layout saved.');
    }

    public function editQuickActions(Request $request)
    {
        abort_unless(Schema::hasTable('quick_action_preferences'), 404, 'Run the v2.6.9 update first.');

        $user = $request->user();
        $metrics = $this->metrics($user);
        $quickActions = $this->resolvedQuickActions($user, $this->availableQuickActions($user, $metrics), true);

        return view('dashboard_quick_actions', compact('quickActions'));
    }

    public function updateQuickActions(Request $request)
    {
        abort_unless(Schema::hasTable('quick_action_preferences'), 404, 'Run the v2.6.9 update first.');

        $user = $request->user();
        $available = collect($this->availableQuickActions($user, $this->metrics($user)))->keyBy('key');

        $data = $request->validate([
            'actions' => ['nullable', 'array'],
            'actions.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:500'],
            'actions.*.visible' => ['nullable', 'boolean'],
        ]);

        foreach ($available as $key => $action) {
            $input = $data['actions'][$key] ?? [];

            QuickActionPreference::updateOrCreate(
                ['user_id' => $user->id, 'action_key' => $key],
                [
                    'sort_order' => (int) ($input['sort_order'] ?? $action['default_order']),
                    'is_visible' => array_key_exists('visible', $input),
                ]
            );
        }

        return redirect()->route('dashboard')->with('success', 'Quick Actions shortcuts saved.');
    }

    private function metrics(?User $viewer = null): array
    {
        $attendanceReady = Schema::hasTable('attendance_days');
        $documentsReady = Schema::hasTable('employee_documents');
        $vehiclesReady = Schema::hasTable('vehicles');
        $fuelReady = Schema::hasTable('vehicle_fuel_ups');
        $vehicleDocumentsReady = Schema::hasTable('vehicle_documents');
        $vehicleServiceReady = Schema::hasTable('vehicle_service_records') && Schema::hasColumn('vehicles', 'service_interval_km');
        $leaveReady = Schema::hasTable('leave_requests') && Schema::hasTable('leave_types');
        $vehicleServiceReminderCount = 0;

        if ($vehicleServiceReady) {
            $vehicleServiceReminderCount = Vehicle::where('status', 'active')->get()->filter(function (Vehicle $vehicle) {
                return in_array($vehicle->service_summary['state'], ['overdue', 'due-soon'], true);
            })->count();
        }

        return [
            'employeeCount' => User::count(),
            'activeEmployeeCount' => User::where('status', 'active')->count(),
            'departmentCount' => Department::count(),
            'roleCount' => Role::count(),
            'attendanceReady' => $attendanceReady,
            'todayAttendanceCount' => $attendanceReady ? AttendanceDay::whereDate('attendance_date', now()->toDateString())->count() : 0,
            'todaySingleRecordCount' => $attendanceReady ? AttendanceDay::whereDate('attendance_date', now()->toDateString())->where('record_count', '<=', 1)->count() : 0,
            'latestAttendanceImport' => Schema::hasTable('attendance_imports') ? AttendanceImport::latest()->first() : null,
            'documentsReady' => $documentsReady,
            'activeDocumentCount' => $documentsReady ? EmployeeDocument::where('status', 'active')->count() : 0,
            'expiringDocumentCount' => $documentsReady ? EmployeeDocument::reminderDue()->count() : 0,
            'expiredDocumentCount' => $documentsReady ? EmployeeDocument::where('status', 'active')->where('has_expiry', true)->whereDate('expires_at', '<', now()->toDateString())->count() : 0,
            'complianceMissingDocumentsCount' => $documentsReady ? User::where('status', 'active')->whereDoesntHave('documents')->count() : 0,
            'complianceNonCompliantCount' => $documentsReady ? User::where('status', 'active')->whereHas('documents', function ($query) {
                $query->where('status', 'active')->where('has_expiry', true)->whereDate('expires_at', '<', now()->toDateString());
            })->count() : 0,
            'vehiclesReady' => $vehiclesReady,
            'vehicleCount' => $vehiclesReady ? Vehicle::count() : 0,
            'activeVehicleCount' => $vehiclesReady ? Vehicle::where('status', 'active')->count() : 0,
            'fuelReady' => $fuelReady,
            'fuelUpsThisMonth' => $fuelReady ? VehicleFuelUp::whereDate('fuelup_date', '>=', now()->startOfMonth()->toDateString())->count() : 0,
            'fuelLitresThisMonth' => $fuelReady ? VehicleFuelUp::whereDate('fuelup_date', '>=', now()->startOfMonth()->toDateString())->sum('litres') : 0,
            'fuelCostThisMonth' => $fuelReady ? VehicleFuelUp::whereDate('fuelup_date', '>=', now()->startOfMonth()->toDateString())->sum('total_cost') : 0,
            'vehicleDocumentsReady' => $vehicleDocumentsReady,
            'vehicleDocumentReminderCount' => $vehicleDocumentsReady ? VehicleDocument::reminderDue()->count() : 0,
            'vehicleServiceReady' => $vehicleServiceReady,
            'vehicleServiceReminderCount' => $vehicleServiceReminderCount,
            'leaveReady' => $leaveReady,
            'pendingLeaveCount' => $leaveReady ? LeaveRequest::query()->when($viewer, fn ($query) => $query->visibleTo($viewer))->where('status', 'pending')->count() : 0,
            'approvedLeaveThisMonth' => $leaveReady ? LeaveRequest::query()->when($viewer, fn ($query) => $query->visibleTo($viewer))->where('status', 'approved')->whereDate('start_date', '>=', now()->startOfMonth()->toDateString())->whereDate('start_date', '<=', now()->endOfMonth()->toDateString())->count() : 0,
        ];
    }

    private function availableWidgets(User $user, array $metrics): array
    {
        $widgets = [
            [
                'key' => 'company_summary',
                'title' => 'Company Summary',
                'description' => 'Employees, departments and roles.',
                'default_size' => 'large',
                'default_order' => 10,
                'available' => true,
            ],
            [
                'key' => 'quick_actions',
                'title' => 'Quick Actions',
                'description' => 'Fast access to selected command areas.',
                'default_size' => 'medium',
                'default_order' => 20,
                'available' => true,
            ],
            [
                'key' => 'leave_calendar',
                'title' => 'Leave & Calendar',
                'description' => 'Leave requests and calendar visibility.',
                'default_size' => 'medium',
                'default_order' => 25,
                'available' => $metrics['leaveReady'] && $user->hasAnyPermission(['leave.view', 'calendar.view']),
            ],
            [
                'key' => 'attendance_today',
                'title' => 'Attendance Today',
                'description' => 'Daily attendance count and single-punch flags.',
                'default_size' => 'medium',
                'default_order' => 30,
                'available' => $metrics['attendanceReady'] && $user->hasPermission('attendance.view'),
            ],
            [
                'key' => 'employee_documents',
                'title' => 'Employee Documents',
                'description' => 'Active, expiring and expired employee documents.',
                'default_size' => 'medium',
                'default_order' => 40,
                'available' => $metrics['documentsReady'] && $user->hasPermission('employee_documents.view'),
            ],
            [
                'key' => 'employee_compliance',
                'title' => 'Employee Compliance',
                'description' => 'Active employee document compliance overview.',
                'default_size' => 'medium',
                'default_order' => 45,
                'available' => $metrics['documentsReady'] && $user->hasPermission('employee_compliance.view'),
            ],
            [
                'key' => 'vehicles',
                'title' => 'Vehicles',
                'description' => 'Fleet count and active vehicle summary.',
                'default_size' => 'medium',
                'default_order' => 50,
                'available' => $metrics['vehiclesReady'] && $user->hasPermission('vehicle.view'),
            ],
            [
                'key' => 'fuel_this_month',
                'title' => 'Fuel This Month',
                'description' => 'Fuel-up count, litres and cost this month.',
                'default_size' => 'medium',
                'default_order' => 60,
                'available' => $metrics['fuelReady'] && $user->hasPermission('vehicle.fuel.view'),
            ],
            [
                'key' => 'vehicle_reminders',
                'title' => 'Vehicle Reminders',
                'description' => 'NATIS, license disk and vehicle document reminders.',
                'default_size' => 'small',
                'default_order' => 70,
                'available' => $metrics['vehicleDocumentsReady'] && $user->hasPermission('vehicle.reminders.view'),
            ],
            [
                'key' => 'vehicle_service_reminders',
                'title' => 'Vehicle Service Reminders',
                'description' => 'Upcoming and overdue vehicle services based on latest ODO.',
                'default_size' => 'small',
                'default_order' => 75,
                'available' => $metrics['vehicleServiceReady'] && $user->hasPermission('vehicle.service.reminders.view'),
            ],
            [
                'key' => 'access_model',
                'title' => 'Access Model',
                'description' => 'Director, manager, employee and custom-role structure.',
                'default_size' => 'small',
                'default_order' => 80,
                'available' => true,
            ],
        ];

        return array_values(array_filter($widgets, fn ($widget) => $widget['available']));
    }

    private function resolvedWidgets(User $user, array $availableWidgets, bool $includeHidden = false): array
    {
        $preferences = collect();
        if (Schema::hasTable('dashboard_widget_preferences')) {
            $preferences = DashboardWidgetPreference::where('user_id', $user->id)->get()->keyBy('widget_key');
        }

        $widgets = [];
        foreach ($availableWidgets as $widget) {
            $preference = $preferences->get($widget['key']);
            $widget['size'] = $preference?->size ?: $widget['default_size'];
            $widget['sort_order'] = $preference?->sort_order ?? $widget['default_order'];
            $widget['is_visible'] = $preference ? (bool) $preference->is_visible : true;

            if ($includeHidden || $widget['is_visible']) {
                $widgets[] = $widget;
            }
        }

        usort($widgets, fn ($a, $b) => [$a['sort_order'], $a['title']] <=> [$b['sort_order'], $b['title']]);

        return $widgets;
    }

    private function availableQuickActions(User $user, array $metrics): array
    {
        $actions = [
            [
                'key' => 'add_employee',
                'icon' => '👤',
                'label' => 'Add Employee',
                'description' => 'Create a new employee profile.',
                'route' => 'employees.create',
                'permission' => 'employees.create',
                'default_order' => 10,
                'available' => true,
            ],
            [
                'key' => 'permission_matrix',
                'icon' => '🛡️',
                'label' => 'Permission Matrix',
                'description' => 'Manage role permissions.',
                'route' => 'roles.index',
                'permission' => 'roles.view',
                'default_order' => 20,
                'available' => true,
            ],
            [
                'key' => 'customers',
                'icon' => '🤝',
                'label' => 'Customers',
                'description' => 'Open customer records.',
                'route' => 'customers.index',
                'permission' => 'clients.view',
                'default_order' => 25,
                'available' => Schema::hasTable('customers'),
            ],
            [
                'key' => 'leave',
                'icon' => '🗓️',
                'label' => 'Leave',
                'description' => 'View leave requests.',
                'route' => 'leave.index',
                'permission' => 'leave.view',
                'default_order' => 30,
                'available' => $metrics['leaveReady'],
            ],
            [
                'key' => 'calendar',
                'icon' => '📅',
                'label' => 'Calendar',
                'description' => 'View operational calendar.',
                'route' => 'calendar.index',
                'permission' => 'calendar.view',
                'default_order' => 40,
                'available' => true,
            ],
            [
                'key' => 'attendance',
                'icon' => '⏱️',
                'label' => 'Attendance',
                'description' => 'Open time attendance.',
                'route' => 'attendance.index',
                'permission' => 'attendance.view',
                'default_order' => 50,
                'available' => $metrics['attendanceReady'],
            ],
            [
                'key' => 'attendance_upload',
                'icon' => '⬆️',
                'label' => 'Upload Attendance',
                'description' => 'Import attendance CSV.',
                'route' => 'attendance.upload',
                'permission' => 'attendance.import',
                'default_order' => 60,
                'available' => $metrics['attendanceReady'],
            ],
            [
                'key' => 'document_reminders',
                'icon' => '📄',
                'label' => 'Document Reminders',
                'description' => 'Employee document reminders.',
                'route' => 'employee_documents.reminders',
                'permission' => 'employee_documents.view',
                'default_order' => 70,
                'available' => $metrics['documentsReady'],
            ],
            [
                'key' => 'vehicles',
                'icon' => '🚗',
                'label' => 'Vehicles & Fuel',
                'description' => 'Open the vehicle dashboard.',
                'route' => 'vehicles.index',
                'permission' => 'vehicle.view',
                'default_order' => 80,
                'available' => $metrics['vehiclesReady'],
            ],
            [
                'key' => 'add_fuel',
                'icon' => '⛽',
                'label' => 'Add Fuel',
                'description' => 'Select a vehicle and capture fuel.',
                'route' => 'vehicles.fuel.select',
                'permission' => 'vehicle.fuel.manage',
                'default_order' => 90,
                'available' => $metrics['vehiclesReady'] && $metrics['fuelReady'],
            ],
            [
                'key' => 'vehicle_tracking',
                'icon' => '📍',
                'label' => 'Tracking',
                'description' => 'Open vehicle tracking dashboard.',
                'route' => 'vehicle_tracking.index',
                'permission' => 'vehicle_tracking.view',
                'default_order' => 100,
                'available' => $metrics['vehiclesReady'],
            ],
            [
                'key' => 'vehicle_services',
                'icon' => '🔧',
                'label' => 'Service Reminders',
                'description' => 'View service reminders.',
                'route' => 'vehicles.service_reminders',
                'permission' => 'vehicle.service.reminders.view',
                'default_order' => 110,
                'available' => $metrics['vehicleServiceReady'],
            ],
            [
                'key' => 'vehicle_docs',
                'icon' => '📎',
                'label' => 'Vehicle Documents',
                'description' => 'View vehicle document reminders.',
                'route' => 'vehicles.reminders',
                'permission' => 'vehicle.reminders.view',
                'default_order' => 120,
                'available' => $metrics['vehicleDocumentsReady'],
            ],
            [
                'key' => 'core_settings',
                'icon' => '⚙️',
                'label' => 'Core Settings',
                'description' => 'Open core system settings.',
                'route' => 'core_settings.index',
                'permission' => 'core_settings.view',
                'default_order' => 130,
                'available' => true,
            ],
        ];

        return collect($actions)
            ->filter(fn ($action) => ($action['available'] ?? true) && Route::has($action['route']) && $user->hasPermission($action['permission']))
            ->map(function ($action) {
                $action['url'] = route($action['route']);
                return $action;
            })
            ->values()
            ->all();
    }

    private function resolvedQuickActions(User $user, array $availableActions, bool $includeHidden = false): array
    {
        $preferences = collect();
        if (Schema::hasTable('quick_action_preferences')) {
            $preferences = QuickActionPreference::where('user_id', $user->id)->get()->keyBy('action_key');
        }

        $actions = [];
        foreach ($availableActions as $action) {
            $preference = $preferences->get($action['key']);
            $action['sort_order'] = $preference?->sort_order ?? $action['default_order'];
            $action['is_visible'] = $preference ? (bool) $preference->is_visible : true;

            if ($includeHidden || $action['is_visible']) {
                $actions[] = $action;
            }
        }

        usort($actions, fn ($a, $b) => [$a['sort_order'], $a['label']] <=> [$b['sort_order'], $b['label']]);

        return $actions;
    }

    private function sizes(): array
    {
        return [
            'small' => 'Small - compact metric only',
            'medium' => 'Medium - useful summary',
            'large' => 'Large - detailed dashboard block',
        ];
    }
}
