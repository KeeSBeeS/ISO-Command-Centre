@extends('layouts.app')
@section('title',$employee->name.' | ISO Admin')
@section('page_title','Employee Profile')
@section('content')
<div class="grid cols-2">
    <div class="card">
        <h2>{{ $employee->name }}</h2>
        <p class="muted">{{ $employee->email }}</p>
        <div><span class="pill {{ $employee->status === 'active' ? '' : 'off' }}">{{ ucfirst($employee->status) }}</span></div>
        <hr style="border-color:var(--line);border-width:0 0 1px;margin:18px 0">
        <p><strong>Job Title:</strong> {{ optional($employee->profile)->job_title ?? $employee->position ?? 'Not set' }}</p>
        <p><strong>Employee Code:</strong> {{ $employee->employee_code ?? 'Not set' }}</p>
        <p><strong>Attendance CSV Name:</strong> {{ $employee->attendance_name ?? $employee->name }}</p>
        <p><strong>Phone:</strong> {{ optional($employee->profile)->phone ?? $employee->phone ?? 'Not set' }}</p>
        <p><strong>Mobile:</strong> {{ optional($employee->profile)->mobile ?? 'Not set' }}</p>
        <p><strong>Started:</strong> {{ optional(optional($employee->profile)->started_at)->format('Y-m-d') ?? 'Not set' }}</p>
        <div class="actions">
            @if(auth()->user()->hasPermission('employees.edit'))<a class="btn primary" href="{{ route('employees.edit',$employee) }}">Edit Employee</a>@endif
            @if(auth()->user()->hasPermission('employee_documents.upload') && \Illuminate\Support\Facades\Schema::hasTable('employee_documents'))
                <a class="btn" href="{{ route('employee_documents.create',$employee) }}">Attach Document</a>
            @endif
            @if(auth()->user()->hasPermission('employees.delete') && auth()->id() !== $employee->id)
                <form method="post" action="{{ route('employees.destroy',$employee) }}" onsubmit="return confirm('Deactivate this employee?')">@csrf @method('DELETE')<button class="btn danger" type="submit">Deactivate</button></form>
            @endif
        </div>
    </div>
    <div class="grid">
        <div class="card"><h2>Departments</h2>@forelse($employee->departments as $department)<span class="pill">{{ $department->name }}</span>@empty<p class="muted">No department assigned.</p>@endforelse</div>
        <div class="card"><h2>Roles</h2>@forelse($employee->roles as $role)<span class="pill">{{ $role->name }}</span>@empty<p class="muted">No role assigned.</p>@endforelse</div>
    </div>
</div>
<div style="height:14px"></div>
@if($attendanceOverview && auth()->user()->hasAnyPermission(['attendance.view', 'attendance.late.view']))
<div class="card" id="time-attendance">
    <div class="actions" style="justify-content:space-between;align-items:flex-start">
        <div>
            <h2 style="margin-bottom:6px">Time &amp; Attendance</h2>
            <p class="muted">Daily clock-in / clock-out register against the {{ $attendanceOverview['timetable'] }} shift. Select a date range to see check-in, checkout and how late each day was at a glance.</p>
        </div>
        @if(auth()->user()->hasPermission('attendance.view') && $attendanceOverview['has_data'])
            <a class="btn" href="{{ route('attendance.show', $employee->employee_code ?: $employee->id) }}">Open Full History</a>
        @endif
    </div>

    <form method="get" action="{{ route('employees.show', $employee) }}#time-attendance" class="form-grid" style="margin-top:12px">
        <div class="form-row">
            <label>Date From</label>
            <input type="date" name="att_from" value="{{ $attendanceOverview['date_from'] }}">
        </div>
        <div class="form-row">
            <label>Date To</label>
            <input type="date" name="att_to" value="{{ $attendanceOverview['date_to'] }}">
        </div>
        <div class="form-row" style="align-self:end">
            <button class="btn primary full" type="submit">Apply Date Range</button>
        </div>
        <div class="form-row" style="align-self:end">
            <a class="btn full" href="{{ route('employees.show', $employee) }}#time-attendance">Reset</a>
        </div>
    </form>

    <p class="muted small" style="margin:2px 0 0">
        Showing <strong>{{ $attendanceOverview['date_from'] }}</strong> to <strong>{{ $attendanceOverview['date_to'] }}</strong>.
        @if($attendanceOverview['has_data'])
            Records available from <strong>{{ $attendanceOverview['available_from'] }}</strong> to <strong>{{ $attendanceOverview['available_to'] }}</strong>.
        @else
            No attendance has been imported for this employee yet.
        @endif
        @if($attendanceOverview['range_capped'])
            <br><span class="pill off">Range limited to the most recent 366 days.</span>
        @endif
    </p>

    <div class="grid cols-4" style="margin-top:14px">
        <div class="card metric"><span>Days Present</span><strong>{{ $attendanceOverview['summary']['present'] }}</strong></div>
        <div class="card metric"><span>Days Absent</span><strong>{{ $attendanceOverview['summary']['absent'] }}</strong></div>
        <div class="card metric"><span>Late Days</span><strong>{{ $attendanceOverview['summary']['late_days'] }}</strong></div>
        <div class="card metric"><span>Total Late Time</span><strong style="font-size:24px">{{ $attendanceOverview['summary']['late_label'] }}</strong></div>
        <div class="card metric"><span>Early Leave Days</span><strong>{{ $attendanceOverview['summary']['early_days'] }}</strong></div>
        <div class="card metric"><span>Total Early Leave</span><strong style="font-size:24px">{{ $attendanceOverview['summary']['early_label'] }}</strong></div>
        <div class="card metric"><span>Working Days</span><strong>{{ $attendanceOverview['summary']['working_days'] }}</strong></div>
        <div class="card metric"><span>Public Holidays</span><strong>{{ $attendanceOverview['summary']['public_holidays'] }}</strong></div>
    </div>

    <div class="table-wrap" style="margin-top:14px">
        <table>
            <thead>
                <tr><th>Date</th><th>Day</th><th>Shift</th><th>Check In</th><th>Check Out</th><th>Late By</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse($attendanceOverview['rows'] as $row)
                    <tr>
                        <td><strong>{{ $row['date']->format('Y-m-d') }}</strong></td>
                        <td class="muted">{{ $row['weekday'] }}</td>
                        <td class="muted small">{{ $attendanceOverview['timetable'] }}</td>
                        <td>{{ $row['check_in'] ?? '-' }}</td>
                        <td>{{ $row['check_out'] ?? '-' }}</td>
                        <td>
                            @if($row['late_label'])
                                <span class="pill off">{{ $row['late_label'] }}</span>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if($row['status'] === 'holiday')
                                <span class="pill warning">Public Holiday</span>@if($row['holiday_name'])<br><span class="muted small">{{ $row['holiday_name'] }}</span>@endif
                            @elseif($row['status'] === 'absent')
                                <span class="pill off">Absent</span>
                            @elseif($row['late_label'])
                                <span class="pill off">Late</span>
                            @else
                                <span class="pill">On time</span>
                            @endif
                            @if($row['early_label'])<br><span class="muted small">Left {{ $row['early_label'] }} early</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No attendance days fall within the selected range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="muted small" style="margin-top:10px">Weekends are hidden unless a clock-in was recorded. "Late By" and "Early Leave" are measured against the {{ $attendanceOverview['start_time'] }} start and {{ $attendanceOverview['close_time'] }} close times configured in Core Settings.</p>
</div>
<div style="height:14px"></div>
@endif

@if(\Illuminate\Support\Facades\Schema::hasTable('employee_documents') && auth()->user()->hasPermission('employee_documents.view'))
<div class="card">
    <div class="actions" style="justify-content:space-between">
        <div>
            <h2 style="margin-bottom:6px">Employee Documents</h2>
            <p class="muted">Medicals, sick notes, warnings, certificates, company policies and expiry-controlled documents.</p>
        </div>
        <div class="actions">
            @if(auth()->user()->hasPermission('employee_documents.upload'))<a class="btn primary" href="{{ route('employee_documents.create',$employee) }}">Upload</a>@endif
            <a class="btn" href="{{ route('employee_documents.reminders') }}">Reminders</a>
        </div>
    </div>
    <div class="table-wrap" style="margin-top:12px">
        <table>
            <thead>
                <tr>
                    <th>Document</th>
                    <th>Type</th>
                    <th>Expiry</th>
                    <th>Reminder</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employee->documents->sortByDesc('created_at') as $document)
                    <tr>
                        <td>
                            <strong>{{ $document->title }}</strong><br>
                            <span class="muted small">{{ $document->original_filename }} · {{ $document->file_size_label }}</span>
                            @if($document->notes)<br><span class="muted small">{{ $document->notes }}</span>@endif
                        </td>
                        <td><span class="pill">{{ $document->type_label }}</span></td>
                        <td>{{ $document->has_expiry ? optional($document->expires_at)->format('Y-m-d') : 'No expiry' }}</td>
                        <td>
                            @if($document->has_expiry)
                                {{ optional($document->reminder_date)->format('Y-m-d') }}<br>
                                <span class="muted small">{{ $document->remind_days_before }} days before</span>
                            @else
                                <span class="muted">Not required</span>
                            @endif
                        </td>
                        <td><span class="pill {{ in_array($document->expiry_state, ['expired','inactive']) ? 'off' : '' }}">{{ str_replace('-', ' ', ucfirst($document->expiry_state)) }}</span></td>
                        <td>
                            <div class="actions">
                                <a class="btn" href="{{ route('employee_documents.download', $document) }}">Download</a>
                                @if($document->status === 'active' && auth()->user()->hasPermission('employee_documents.manage'))
                                    <form method="post" action="{{ route('employee_documents.inactive', $document) }}" onsubmit="return confirm('Mark this document as inactive?')">
                                        @csrf @method('PATCH')
                                        <button class="btn danger" type="submit">Inactive</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No documents uploaded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div style="height:14px"></div>
@endif

@php
    $vehiclePolicyValid = false;

    if (\Illuminate\Support\Facades\Schema::hasTable('employee_documents') && $employee->relationLoaded('documents')) {
        $vehiclePolicyValid = $employee->documents
            ->where('document_type', 'vehicle_policy')
            ->where('status', 'active')
            ->contains(function ($document) {
                return !$document->has_expiry
                    || !$document->expires_at
                    || $document->expires_at->gte(now()->startOfDay());
            });
    }
@endphp

@if(\Illuminate\Support\Facades\Schema::hasTable('vehicle_assignments') && auth()->user()->hasPermission('vehicle.view'))
<div class="card">
    <div class="actions" style="justify-content:space-between">
        <div>
            <h2 style="margin-bottom:6px">Assigned Vehicles</h2>
            <p class="muted">Active vehicle assignments for this employee/director.</p>
        </div>
        @if(auth()->user()->hasPermission('vehicle.create'))<a class="btn" href="{{ route('vehicles.index') }}">Open Vehicles</a>@endif
    </div>
    <div class="table-wrap" style="margin-top:12px">
        <table>
            <thead><tr><th>Vehicle</th><th>Assigned</th><th>Policy Warning</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($employee->currentVehicleAssignments as $assignment)
                    <tr>
                        <td><strong>{{ optional($assignment->vehicle)->display_name ?? 'Unknown vehicle' }}</strong><br><span class="muted small">Reg: {{ optional($assignment->vehicle)->registration_number ?? 'Not set' }}</span></td>
                        <td>{{ optional($assignment->assigned_at)->format('Y-m-d H:i') }}</td>
                        <td>
                            @if($vehiclePolicyValid ?? false)
                                <span class="pill">Vehicle Policy Valid</span>
                            @else
                                <span class="pill off">Vehicle Policy Outstanding</span>
                            @endif
                        </td>
                        <td>@if($assignment->vehicle)<a class="btn" href="{{ route('vehicles.show',$assignment->vehicle) }}">Open</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No active vehicle assignment.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div style="height:14px"></div>
@endif
<div class="card">
    <h2>Effective Permissions</h2>
    @php $permissions = $employee->effectivePermissions(); @endphp
    @forelse($permissions as $permission)<span class="pill">{{ $permission->slug }}</span>@empty<p class="muted">No permissions assigned.</p>@endforelse
    @if(\Illuminate\Support\Facades\Schema::hasTable('permission_user') && $employee->directPermissions->count())
        <hr style="border-color:var(--line);border-width:0 0 1px;margin:14px 0">
        <h3>Direct User Permissions</h3>
        @foreach($employee->directPermissions->sortBy('slug') as $permission)<span class="pill">{{ $permission->slug }}</span>@endforeach
    @endif
</div>
@endsection
