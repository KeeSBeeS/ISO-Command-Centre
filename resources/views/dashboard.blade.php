@extends('layouts.app')
@section('title','Dashboard | ISO Admin')
@section('page_title','Dashboard')
@section('content')
@php
    $user = auth()->user();
    $widgetMeta = [
        'company_summary' => ['icon' => 'employees', 'tone' => '', 'title' => 'Company', 'route' => 'employees.index'],
        'quick_actions' => ['icon' => 'arrow-right', 'tone' => '', 'title' => 'Quick actions', 'route' => null],
        'leave_calendar' => ['icon' => 'leave', 'tone' => 'warn', 'title' => 'Leave', 'route' => 'leave.index'],
        'attendance_today' => ['icon' => 'attendance', 'tone' => '', 'title' => "Today's attendance", 'route' => 'attendance.index'],
        'employee_documents' => ['icon' => 'documents', 'tone' => 'info', 'title' => 'Employee documents', 'route' => 'employee_documents.reminders'],
        'vehicles' => ['icon' => 'vehicles', 'tone' => '', 'title' => 'Vehicles', 'route' => 'vehicles.index'],
        'fuel_this_month' => ['icon' => 'fuel', 'tone' => '', 'title' => 'Fuel this month', 'route' => 'vehicles.fuel.select'],
        'vehicle_reminders' => ['icon' => 'documents', 'tone' => 'warn', 'title' => 'Vehicle documents', 'route' => 'vehicles.reminders'],
        'vehicle_service_reminders' => ['icon' => 'settings', 'tone' => 'warn', 'title' => 'Vehicle services', 'route' => 'vehicles.service_reminders'],
        'access_model' => ['icon' => 'permissions', 'tone' => '', 'title' => 'Access model', 'route' => 'roles.index'],
    ];
    $quickIcons = [
        'add_employee' => 'plus', 'permission_matrix' => 'permissions', 'customers' => 'customers', 'leave' => 'leave',
        'calendar' => 'calendar', 'attendance' => 'attendance', 'attendance_upload' => 'upload', 'document_reminders' => 'documents',
        'vehicles' => 'vehicles', 'add_fuel' => 'fuel', 'vehicle_tracking' => 'tracking', 'vehicle_services' => 'settings',
        'vehicle_docs' => 'documents', 'core_settings' => 'settings',
    ];
    $link = fn (?string $route) => $route && \Illuminate\Support\Facades\Route::has($route) ? route($route) : null;

    // "Needs attention": only what this person may see, and only when there is something to do.
    $attention = array_values(array_filter([
        ['show' => ($leaveReady ?? false) && $user->hasPermission('leave.view') && ($pendingLeaveCount ?? 0) > 0, 'tone' => 'warn', 'icon' => 'leave',
            'title' => trans_choice(':count leave request waiting|:count leave requests waiting', $pendingLeaveCount ?? 0), 'detail' => 'Waiting for a decision', 'route' => 'leave.index'],
        ['show' => $documentsReady && $user->hasPermission('employee_documents.view') && $expiredDocumentCount > 0, 'tone' => 'danger', 'icon' => 'documents',
            'title' => trans_choice(':count employee document expired|:count employee documents expired', $expiredDocumentCount), 'detail' => 'Replace them to stay compliant', 'route' => 'employee_documents.reminders'],
        ['show' => $documentsReady && $user->hasPermission('employee_documents.view') && $expiringDocumentCount > 0, 'tone' => 'info', 'icon' => 'documents',
            'title' => trans_choice(':count document reminder due|:count document reminders due', $expiringDocumentCount), 'detail' => 'Expiring soon', 'route' => 'employee_documents.reminders'],
        ['show' => $attendanceReady && $user->hasPermission('attendance.view') && $todaySingleRecordCount > 0, 'tone' => 'warn', 'icon' => 'attendance',
            'title' => trans_choice(':count single clock-in today|:count single clock-ins today', $todaySingleRecordCount), 'detail' => 'Check-out still missing', 'route' => 'attendance.index'],
        ['show' => $vehicleServiceReady && $user->hasPermission('vehicle.service.reminders.view') && $vehicleServiceReminderCount > 0, 'tone' => 'warn', 'icon' => 'vehicles',
            'title' => trans_choice(':count vehicle service due|:count vehicle services due', $vehicleServiceReminderCount), 'detail' => 'Due soon or overdue', 'route' => 'vehicles.service_reminders'],
        ['show' => $vehicleDocumentsReady && $user->hasPermission('vehicle.reminders.view') && $vehicleDocumentReminderCount > 0, 'tone' => 'info', 'icon' => 'documents',
            'title' => trans_choice(':count vehicle document reminder|:count vehicle document reminders', $vehicleDocumentReminderCount), 'detail' => 'Licence disks, NATIS and others', 'route' => 'vehicles.reminders'],
    ], fn ($item) => $item['show']));

    $canEditDashboard = $dashboardPreferencesReady && $user->hasPermission('dashboard.customize') && \Illuminate\Support\Facades\Route::has('dashboard.edit');
@endphp

<div class="page-head">
    <div>
        <p class="muted small" style="margin:0 0 2px">{{ now()->timezone(config('iso.display_timezone'))->format('l, j F Y') }}</p>
        <h2>Welcome back, {{ \Illuminate\Support\Str::of($user->name)->before(' ') }}</h2>
    </div>
    <div class="actions">
        @if($canEditDashboard)
            <a class="btn" href="{{ route('dashboard.edit') }}"><x-icon name="edit" size="18" />Customise</a>
        @endif
        @if($attendanceReady && $user->hasPermission('attendance.view') && $link('attendance.index'))
            <a class="btn" href="{{ route('attendance.index') }}"><x-icon name="report" size="18" />Attendance</a>
        @endif
        @if($attendanceReady && $user->hasPermission('attendance.import') && $link('attendance.upload'))
            <a class="btn primary" href="{{ route('attendance.upload') }}"><x-icon name="upload" size="18" />Import attendance</a>
        @endif
    </div>
</div>

@if(count($attention))
    <section class="card dash-attention" aria-labelledby="attention-title">
        <h2 id="attention-title">Needs attention</h2>
        <div class="dash-attention-list">
            @foreach($attention as $item)
                <a class="dash-attention-item {{ $item['tone'] }}" href="{{ $link($item['route']) ?? '#' }}">
                    <span class="stat-icon {{ $item['tone'] }}"><x-icon :name="$item['icon']" size="18" /></span>
                    <span><strong>{{ $item['title'] }}</strong><span>{{ $item['detail'] }}</span></span>
                </a>
            @endforeach
        </div>
    </section>
@endif

<div class="dash-grid">
@foreach($dashboardWidgets as $widget)
    @php
        $meta = $widgetMeta[$widget['key']] ?? ['icon' => 'dashboard', 'tone' => '', 'title' => $widget['title'] ?? $widget['key'], 'route' => null];
        $open = $link($meta['route']);
        $size = $widget['size'];
    @endphp
    <section class="card dash-widget {{ $size }}" data-widget="{{ $widget['key'] }}" aria-label="{{ $meta['title'] }}">
        <div class="dash-widget-head">
            <span class="stat-icon {{ $meta['tone'] }}"><x-icon :name="$meta['icon']" size="18" /></span>
            <h2>{{ $meta['title'] }}</h2>
            @if($widget['key'] === 'quick_actions' && ($quickActionPreferencesReady ?? false) && $user->hasPermission('dashboard.quick_actions.manage') && $link('dashboard.quick_actions.edit'))
                <a class="dash-open" href="{{ route('dashboard.quick_actions.edit') }}">Edit</a>
            @elseif($open && $widget['key'] !== 'quick_actions')
                <a class="dash-open" href="{{ $open }}">Open</a>
            @endif
        </div>

        @switch($widget['key'])
            @case('company_summary')
                <div class="dash-figure">{{ $activeEmployeeCount }} <span>active employees</span></div>
                <div class="dash-detail">
                    <div class="metric-box"><span>All employees</span><strong>{{ $employeeCount }}</strong></div>
                    <div class="metric-box"><span>Departments</span><strong>{{ $departmentCount }}</strong></div>
                    <div class="metric-box"><span>Roles</span><strong>{{ $roleCount }}</strong></div>
                </div>
                @break

            @case('quick_actions')
                @if(count($quickActions ?? []))
                    <div class="dash-quick">
                        @foreach($quickActions as $action)
                            <a class="dash-quick-item" href="{{ $action['url'] }}">
                                <x-icon :name="$quickIcons[$action['key']] ?? 'arrow-right'" size="18" />
                                <span>{{ $action['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="muted">No shortcuts are available for your permissions.</p>
                @endif
                @break

            @case('leave_calendar')
                <div class="dash-figure">{{ $pendingLeaveCount ?? 0 }} <span>waiting for a decision</span></div>
                <div class="dash-detail">
                    <div class="metric-box"><span>Approved this month</span><strong>{{ $approvedLeaveThisMonth ?? 0 }}</strong></div>
                    @if($link('calendar.index') && $user->hasPermission('calendar.view'))
                        <a class="metric-box dash-link" href="{{ route('calendar.index') }}"><span>Calendar</span><strong>Open</strong></a>
                    @endif
                </div>
                @break

            @case('attendance_today')
                <div class="dash-figure">{{ $todayAttendanceCount }} <span>clocked in today</span></div>
                @if(($todayAttendanceRows ?? collect())->count() && $size !== 'small')
                    <div class="table-wrap dash-detail-table">
                        <table>
                            <thead><tr><th scope="col">Employee</th><th scope="col">In</th><th scope="col">Out</th><th scope="col">Status</th></tr></thead>
                            <tbody>
                            @foreach($todayAttendanceRows as $day)
                                <tr>
                                    <td>{{ optional($day->user)->name ?? '—' }}</td>
                                    <td>{{ optional($day->start_time)->format('H:i') ?? '—' }}</td>
                                    <td>{{ $day->record_count > 1 ? (optional($day->end_time)->format('H:i') ?? '—') : '—' }}</td>
                                    <td>
                                        @if($day->is_public_holiday)<span class="pill info">Public holiday</span>
                                        @elseif($day->is_late)<span class="pill warn">{{ $day->late_minutes ? $day->late_minutes.' min late' : 'Late' }}</span>
                                        @elseif($day->record_count <= 1)<span class="pill warn">No check-out</span>
                                        @else<span class="pill">On time</span>@endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="dash-detail">
                        <div class="metric-box"><span>Single clock-ins</span><strong>{{ $todaySingleRecordCount }}</strong></div>
                        <div class="metric-box"><span>Last import</span><strong class="dash-small-value">{{ optional(optional($latestAttendanceImport)->created_at)->format('j M H:i') ?? 'None yet' }}</strong></div>
                    </div>
                @endif
                @break

            @case('employee_documents')
                <div class="dash-figure">{{ $expiringDocumentCount }} <span>reminders due</span></div>
                <div class="dash-detail">
                    <div class="metric-box"><span>Active</span><strong>{{ $activeDocumentCount }}</strong></div>
                    <div class="metric-box"><span>Expired</span><strong>{{ $expiredDocumentCount }}</strong></div>
                </div>
                @break

            @case('vehicles')
                <div class="dash-figure">{{ $activeVehicleCount }} <span>active vehicles</span></div>
                <div class="dash-detail">
                    <div class="metric-box"><span>All vehicles</span><strong>{{ $vehicleCount }}</strong></div>
                    <div class="metric-box"><span>Fuel-ups this month</span><strong>{{ $fuelUpsThisMonth }}</strong></div>
                </div>
                @break

            @case('fuel_this_month')
                <div class="dash-figure">R {{ number_format((float) $fuelCostThisMonth, 0, '.', ' ') }} <span>spent on fuel</span></div>
                <div class="dash-detail">
                    <div class="metric-box"><span>Fuel-ups</span><strong>{{ $fuelUpsThisMonth }}</strong></div>
                    <div class="metric-box"><span>Litres</span><strong>{{ number_format((float) $fuelLitresThisMonth, 1, '.', ' ') }}</strong></div>
                </div>
                @break

            @case('vehicle_reminders')
                <div class="dash-figure">{{ $vehicleDocumentReminderCount }} <span>reminders due</span></div>
                @break

            @case('vehicle_service_reminders')
                <div class="dash-figure">{{ $vehicleServiceReminderCount }} <span>due soon or overdue</span></div>
                @break

            @case('access_model')
                <p class="muted" style="margin:0">System Administrators sit above Directors. Managers get operational access, and Employees get the basics unless a role adds more.</p>
                @break
        @endswitch
    </section>
@endforeach
</div>
@endsection

@push('head')
<style>
    .dash-attention { margin-bottom: 20px; }
    .dash-attention-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 10px; }
    .dash-attention-item { display: flex; align-items: flex-start; gap: 12px; padding: 12px; border-radius: var(--radius-sm); background: var(--panel2); color: var(--text); }
    .dash-attention-item:hover { text-decoration: none; color: var(--text); background: var(--bg-soft); }
    .dash-attention-item.warn { background: #fff7ed; }
    .dash-attention-item.danger { background: #fef3f2; }
    .dash-attention-item.info { background: #f2f7fd; }
    .dash-attention-item strong { display: block; font-size: 14px; }
    .dash-attention-item span span { display: block; font-size: 13px; color: var(--muted); }

    .dash-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; align-items: start; }
    .dash-widget.small { grid-column: span 1; }
    .dash-widget.medium { grid-column: span 2; }
    .dash-widget.large { grid-column: span 4; }
    .dash-widget-head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
    .dash-widget-head h2 { margin: 0; font-size: 16px; flex: 1; min-width: 0; }
    .dash-open { font-size: 14px; font-weight: 600; min-height: 32px; display: inline-flex; align-items: center; }
    .dash-figure { font-family: var(--font-display); font-size: 32px; font-weight: 600; line-height: 1.1; }
    .dash-figure span { display: block; margin-top: 4px; font-family: var(--font); font-size: 13px; font-weight: 500; color: var(--muted); }
    .dash-detail { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-top: 16px; }
    .dash-detail-table { margin-top: 16px; }
    .dash-detail-table table { min-width: 420px; }
    .dash-detail-table th, .dash-detail-table td { padding: 10px 12px; }
    .dash-link { color: var(--text); }
    .dash-link:hover { text-decoration: none; border-color: var(--line-strong); color: var(--text); }
    .dash-link strong { color: var(--brand); }
    .dash-small-value { font-size: 15px !important; }
    .dash-widget.small .dash-detail, .dash-widget.small .dash-detail-table { display: none; }
    .dash-quick { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 10px; }
    .dash-widget.small .dash-quick { grid-template-columns: 1fr; }
    .dash-quick-item { display: flex; align-items: center; gap: 10px; min-height: 44px; padding: 10px 12px; border: 1px solid var(--line); border-radius: var(--radius-sm); color: var(--text); font-weight: 600; }
    .dash-quick-item:hover { text-decoration: none; border-color: var(--line-strong); background: var(--panel2); color: var(--text); }
    .dash-quick-item .icon { color: var(--brand); }

    @media (max-width: 1080px) {
        .dash-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .dash-widget.medium, .dash-widget.large { grid-column: span 2; }
    }
    @media (max-width: 560px) {
        .dash-grid { grid-template-columns: minmax(0, 1fr); }
        .dash-widget.small, .dash-widget.medium, .dash-widget.large { grid-column: span 1; }
        .dash-figure { font-size: 28px; }
        .dash-quick { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .dash-quick-item { font-size: 13px; padding: 8px 10px; }
    }
</style>
@endpush
