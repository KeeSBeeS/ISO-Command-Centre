@extends('layouts.app')
@section('title','Dashboard | ISO Admin')
@section('page_title','Central Command Dashboard')
@section('content')
@php
    $widgetMeta = [
        'company_summary' => ['icon' => '🏢', 'kicker' => 'Company', 'title' => 'Company Summary'],
        'quick_actions' => ['icon' => '⚡', 'kicker' => 'Actions', 'title' => 'Quick Actions'],
        'leave_calendar' => ['icon' => '🗓️', 'kicker' => 'Leave', 'title' => 'Leave & Calendar'],
        'attendance_today' => ['icon' => '⏱️', 'kicker' => 'Attendance', 'title' => 'Today Attendance'],
        'employee_documents' => ['icon' => '📄', 'kicker' => 'Documents', 'title' => 'Employee Documents'],
        'employee_compliance' => ['icon' => '✅', 'kicker' => 'Compliance', 'title' => 'Employee Compliance'],
        'vehicles' => ['icon' => '🚗', 'kicker' => 'Fleet', 'title' => 'Vehicles'],
        'fuel_this_month' => ['icon' => '⛽', 'kicker' => 'Fuel', 'title' => 'Fuel This Month'],
        'vehicle_reminders' => ['icon' => '📎', 'kicker' => 'Vehicle Docs', 'title' => 'Vehicle Reminders'],
        'vehicle_service_reminders' => ['icon' => '🔧', 'kicker' => 'Vehicle Services', 'title' => 'Service Reminders'],
        'access_model' => ['icon' => '🛡️', 'kicker' => 'Security', 'title' => 'Access Model'],
    ];
@endphp
<style>
    .dashboard-hero{display:grid;grid-template-columns:1.2fr .8fr;gap:16px;margin-bottom:16px}.dashboard-hero .card{min-height:150px}.dashboard-toolbar{display:flex;justify-content:space-between;gap:12px;align-items:center}.dashboard-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.widget{min-height:132px}.widget.small{grid-column:span 1}.widget.medium{grid-column:span 2}.widget.large{grid-column:span 4}.widget-title{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;margin-bottom:10px}.widget-title-left{display:flex;gap:11px;align-items:flex-start}.widget-icon{width:42px;height:42px;border-radius:15px;display:grid;place-items:center;background:linear-gradient(135deg,rgba(18,163,116,.26),rgba(139,220,101,.09));border:1px solid rgba(139,220,101,.22);box-shadow:0 12px 26px rgba(0,0,0,.18)}.widget-title h2{margin:0}.widget-kicker{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.08em}.mini-metric{font-size:36px;font-weight:950;margin-top:6px;letter-spacing:-.05em}.widget-detail{margin-top:12px}.metric-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.metric-box{border:1px solid var(--line);border-radius:16px;padding:12px;background:rgba(255,255,255,.04)}.metric-box span{display:block;color:var(--muted);font-size:12px}.metric-box strong{display:block;font-size:24px;margin-top:6px;letter-spacing:-.03em}.quick-action-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.quick-action{display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:16px;padding:12px;background:rgba(255,255,255,.045);text-decoration:none;color:var(--text)}.quick-action:hover{border-color:rgba(139,220,101,.45);transform:translateY(-1px)}.quick-action-icon{width:36px;height:36px;border-radius:13px;display:grid;place-items:center;background:rgba(139,220,101,.1);border:1px solid rgba(139,220,101,.2)}.quick-action strong{display:block}.quick-action span{display:block;color:var(--muted);font-size:12px;margin-top:2px}.widget.small .hide-small,.widget.small .quick-action span{display:none}.widget.medium .show-large-only,.widget.small .show-large-only{display:none}@media(max-width:980px){.dashboard-hero{grid-template-columns:1fr}.dashboard-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.widget.small,.widget.medium,.widget.large{grid-column:span 2}.metric-row{grid-template-columns:repeat(2,1fr)}}@media(max-width:650px){.dashboard-toolbar{display:block}.dashboard-toolbar .actions{margin-top:10px}.dashboard-grid{grid-template-columns:1fr}.widget.small,.widget.medium,.widget.large{grid-column:span 1}.metric-row,.quick-action-grid{grid-template-columns:1fr}.widget-title-left{align-items:center}.widget-icon{width:38px;height:38px}}
</style>

<div class="dashboard-hero">
    <div class="card">
        <div class="page-head" style="margin-bottom:0">
            <div class="page-head-main">
                <div class="page-head-icon">⌂</div>
                <div>
                    <h2>Central Command</h2>
                    <p>Your live operational dashboard. Widgets can be resized, hidden and reordered per user.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="dashboard-toolbar">
            <div>
                <h2 style="margin-bottom:6px">Dashboard Layout</h2>
                <p class="muted" style="margin:0">Small shows minimal information. Large shows deeper operational detail.</p>
            </div>
            <div class="actions">
                @if($dashboardPreferencesReady)
                    <a class="btn primary" href="{{ route('dashboard.edit') }}">⚙️ Edit Dashboard</a>
                @else
                    <span class="pill off">Run v1.5 to enable dashboard editing</span>
                @endif
                @if(($quickActionPreferencesReady ?? false) && auth()->user()->hasPermission('dashboard.quick_actions.manage'))
                    <a class="btn" href="{{ route('dashboard.quick_actions.edit') }}">⚡ Edit Shortcuts</a>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="dashboard-grid">
@foreach($dashboardWidgets as $widget)
    @php($meta = $widgetMeta[$widget['key']] ?? ['icon' => '▣', 'kicker' => 'Widget', 'title' => $widget['key']])
    <div class="card widget {{ $widget['size'] }}" data-widget="{{ $widget['key'] }}">
        @switch($widget['key'])
            @case('company_summary')
                <div class="widget-title"><div class="widget-title-left"><div class="widget-icon">{{ $meta['icon'] }}</div><div><div class="widget-kicker">{{ $meta['kicker'] }}</div><h2>{{ $meta['title'] }}</h2></div></div><span class="pill">{{ ucfirst($widget['size']) }}</span></div>
                <div class="mini-metric">{{ $activeEmployeeCount }}</div>
                <p class="muted">Active employees</p>
                <div class="widget-detail hide-small">
                    <div class="metric-row">
                        <div class="metric-box"><span>Total Employees</span><strong>{{ $employeeCount }}</strong></div>
                        <div class="metric-box"><span>Departments</span><strong>{{ $departmentCount }}</strong></div>
                        <div class="metric-box"><span>Roles</span><strong>{{ $roleCount }}</strong></div>
                        <div class="metric-box"><span>Status</span><strong style="font-size:18px">Live</strong></div>
                    </div>
                </div>
                @break

            @case('quick_actions')
                <div class="widget-title">
                    <div class="widget-title-left"><div class="widget-icon">{{ $meta['icon'] }}</div><div><div class="widget-kicker">{{ $meta['kicker'] }}</div><h2>{{ $meta['title'] }}</h2></div></div>
                    <div class="actions">
                        <span class="pill">{{ ucfirst($widget['size']) }}</span>
                        @if(($quickActionPreferencesReady ?? false) && auth()->user()->hasPermission('dashboard.quick_actions.manage'))
                            <a class="btn" href="{{ route('dashboard.quick_actions.edit') }}">Edit</a>
                        @endif
                    </div>
                </div>
                @if(count($quickActions ?? []))
                    <div class="quick-action-grid">
                        @foreach($quickActions as $action)
                            <a class="quick-action" href="{{ $action['url'] }}">
                                <span class="quick-action-icon">{{ $action['icon'] }}</span>
                                <span><strong>{{ $action['label'] }}</strong><span>{{ $action['description'] }}</span></span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="muted">No quick actions are available for your current permissions.</p>
                @endif
                <p class="muted hide-small" style="margin-top:10px">Shortcuts are filtered by your permissions and can be adjusted by users with shortcut-management access.</p>
                @break

            @case('leave_calendar')
                <div class="widget-title"><div class="widget-title-left"><div class="widget-icon">{{ $meta['icon'] }}</div><div><div class="widget-kicker">{{ $meta['kicker'] }}</div><h2>{{ $meta['title'] }}</h2></div></div><span class="pill">{{ ucfirst($widget['size']) }}</span></div>
                <div class="mini-metric">{{ $pendingLeaveCount ?? 0 }}</div>
                <p class="muted">Pending leave requests</p>
                <div class="widget-detail hide-small"><div class="metric-row"><div class="metric-box"><span>Pending</span><strong>{{ $pendingLeaveCount ?? 0 }}</strong></div><div class="metric-box"><span>Approved This Month</span><strong>{{ $approvedLeaveThisMonth ?? 0 }}</strong></div><div class="metric-box"><span>Leave</span><strong style="font-size:16px"><a href="{{ route('leave.index') }}">Open</a></strong></div><div class="metric-box"><span>Calendar</span><strong style="font-size:16px"><a href="{{ route('calendar.index') }}">Open</a></strong></div></div></div>
                @break

            @case('attendance_today')
                <div class="widget-title"><div class="widget-title-left"><div class="widget-icon">{{ $meta['icon'] }}</div><div><div class="widget-kicker">{{ $meta['kicker'] }}</div><h2>{{ $meta['title'] }}</h2></div></div><span class="pill">{{ ucfirst($widget['size']) }}</span></div>
                <div class="mini-metric">{{ $todayAttendanceCount }}</div>
                <p class="muted">Attendance day records today</p>
                <div class="widget-detail hide-small"><div class="metric-row"><div class="metric-box"><span>Single Punch Flags</span><strong>{{ $todaySingleRecordCount }}</strong></div><div class="metric-box"><span>Latest Import</span><strong style="font-size:16px">{{ optional(optional($latestAttendanceImport)->created_at)->format('Y-m-d H:i') ?? 'None' }}</strong></div><div class="metric-box"><span>Source</span><strong style="font-size:16px">{{ optional($latestAttendanceImport)->source ? ucfirst($latestAttendanceImport->source) : 'None' }}</strong></div><div class="metric-box"><span>Open</span><strong style="font-size:16px"><a href="{{ route('attendance.index') }}">View</a></strong></div></div></div>
                @break

            @case('employee_documents')
                <div class="widget-title"><div class="widget-title-left"><div class="widget-icon">{{ $meta['icon'] }}</div><div><div class="widget-kicker">{{ $meta['kicker'] }}</div><h2>{{ $meta['title'] }}</h2></div></div><span class="pill">{{ ucfirst($widget['size']) }}</span></div>
                <div class="mini-metric">{{ $expiringDocumentCount }}</div>
                <p class="muted">Reminder due</p>
                <div class="widget-detail hide-small"><div class="metric-row"><div class="metric-box"><span>Active</span><strong>{{ $activeDocumentCount }}</strong></div><div class="metric-box"><span>Reminder Due</span><strong>{{ $expiringDocumentCount }}</strong></div><div class="metric-box"><span>Expired</span><strong>{{ $expiredDocumentCount }}</strong></div><div class="metric-box"><span>Centre</span><strong style="font-size:16px"><a href="{{ route('employee_documents.reminders') }}">Open</a></strong></div></div></div>
                @break

            @case('employee_compliance')
                <div class="widget-title"><div class="widget-title-left"><div class="widget-icon">{{ $meta['icon'] }}</div><div><div class="widget-kicker">{{ $meta['kicker'] }}</div><h2>{{ $meta['title'] }}</h2></div></div><span class="pill">{{ ucfirst($widget['size']) }}</span></div>
                <div class="mini-metric">{{ $activeEmployeeCount - $complianceMissingDocumentsCount - $complianceNonCompliantCount }}</div>
                <p class="muted">Compliant employees</p>
                <div class="widget-detail hide-small"><div class="metric-row"><div class="metric-box"><span>Active Employees</span><strong>{{ $activeEmployeeCount }}</strong></div><div class="metric-box"><span>Compliant</span><strong>{{ $activeEmployeeCount - $complianceMissingDocumentsCount - $complianceNonCompliantCount }}</strong></div><div class="metric-box"><span>Missing Docs</span><strong>{{ $complianceMissingDocumentsCount }}</strong></div><div class="metric-box"><span>Overview</span><strong style="font-size:16px"><a href="{{ route('employee_compliance.index') }}">Open</a></strong></div></div></div>
                @break

            @case('vehicles')
                <div class="widget-title"><div class="widget-title-left"><div class="widget-icon">{{ $meta['icon'] }}</div><div><div class="widget-kicker">{{ $meta['kicker'] }}</div><h2>{{ $meta['title'] }}</h2></div></div><span class="pill">{{ ucfirst($widget['size']) }}</span></div>
                <div class="mini-metric">{{ $activeVehicleCount }}</div>
                <p class="muted">Active vehicles</p>
                <div class="widget-detail hide-small"><div class="metric-row"><div class="metric-box"><span>Total Vehicles</span><strong>{{ $vehicleCount }}</strong></div><div class="metric-box"><span>Active</span><strong>{{ $activeVehicleCount }}</strong></div><div class="metric-box"><span>Fuel-ups Month</span><strong>{{ $fuelUpsThisMonth }}</strong></div><div class="metric-box"><span>Open</span><strong style="font-size:16px"><a href="{{ route('vehicles.index') }}">Dashboard</a></strong></div></div></div>
                @break

            @case('fuel_this_month')
                <div class="widget-title"><div class="widget-title-left"><div class="widget-icon">{{ $meta['icon'] }}</div><div><div class="widget-kicker">{{ $meta['kicker'] }}</div><h2>{{ $meta['title'] }}</h2></div></div><span class="pill">{{ ucfirst($widget['size']) }}</span></div>
                <div class="mini-metric">{{ $fuelUpsThisMonth }}</div>
                <p class="muted">Fuel-ups this month</p>
                <div class="widget-detail hide-small"><div class="metric-row"><div class="metric-box"><span>Fuel-ups</span><strong>{{ $fuelUpsThisMonth }}</strong></div><div class="metric-box"><span>Litres</span><strong>{{ number_format((float)$fuelLitresThisMonth, 1) }}</strong></div><div class="metric-box"><span>Cost</span><strong style="font-size:18px">R {{ number_format((float)$fuelCostThisMonth, 2) }}</strong></div><div class="metric-box"><span>Add Fuel</span><strong style="font-size:16px"><a href="{{ route('vehicles.fuel.select') }}">Open</a></strong></div></div></div>
                @break

            @case('vehicle_reminders')
                <div class="widget-title"><div class="widget-title-left"><div class="widget-icon">{{ $meta['icon'] }}</div><div><div class="widget-kicker">{{ $meta['kicker'] }}</div><h2>{{ $meta['title'] }}</h2></div></div><span class="pill">{{ ucfirst($widget['size']) }}</span></div>
                <div class="mini-metric">{{ $vehicleDocumentReminderCount }}</div>
                <p class="muted">Due reminders</p>
                <div class="widget-detail hide-small"><a class="btn" href="{{ route('vehicles.reminders') }}">📎 Open Reminder Centre</a></div>
                @break

            @case('vehicle_service_reminders')
                <div class="widget-title"><div class="widget-title-left"><div class="widget-icon">{{ $meta['icon'] }}</div><div><div class="widget-kicker">{{ $meta['kicker'] }}</div><h2>{{ $meta['title'] }}</h2></div></div><span class="pill">{{ ucfirst($widget['size']) }}</span></div>
                <div class="mini-metric">{{ $vehicleServiceReminderCount }}</div>
                <p class="muted">Due soon or overdue</p>
                <div class="widget-detail hide-small"><a class="btn" href="{{ route('vehicles.service_reminders') }}">🔧 Open Service Centre</a></div>
                @break

            @case('access_model')
                <div class="widget-title"><div class="widget-title-left"><div class="widget-icon">{{ $meta['icon'] }}</div><div><div class="widget-kicker">{{ $meta['kicker'] }}</div><h2>{{ $meta['title'] }}</h2></div></div><span class="pill">{{ ucfirst($widget['size']) }}</span></div>
                <p class="muted">System Administrators sit above Directors. Managers receive operational access. Employees receive base dashboard access unless extra permissions are assigned.</p>
                <div class="hide-small"><span class="pill">System Admin</span><span class="pill">Director</span><span class="pill">Manager</span><span class="pill">Employee</span><span class="pill">Custom User Permissions</span></div>
                @break
        @endswitch
    </div>
@endforeach
</div>
@endsection
