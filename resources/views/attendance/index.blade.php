@extends('layouts.app')
@section('title','Time Attendance | ISO Admin')
@section('page_title','Time Attendance')
@section('content')
<style>
    .pill.bad{border-color:rgba(229,72,77,.4);background:rgba(229,72,77,.12);color:#ffd7d9}
    .pill.bad::before{background:#e5484d;box-shadow:0 0 0 4px rgba(229,72,77,.12)}
    .att-tabs{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
    .att-tabs a{border:1px solid var(--line);border-radius:14px;padding:10px 16px;font-weight:850;color:var(--muted)}
    .att-tabs a.active{background:linear-gradient(135deg,var(--brand),var(--brand3));color:#fff;border-color:rgba(139,220,101,.34)}
    .num-good{color:#8bdc65;font-weight:850}
    .num-bad{color:#ff9d9f;font-weight:850}
    .num-warn{color:#ffe4ad;font-weight:850}
</style>

<div class="actions right" style="margin-bottom:14px">
    @if(auth()->user()->hasPermission('attendance.manual_upload'))
        <a class="btn primary" href="{{ route('attendance.manual_upload') }}">Director CSV Upload</a>
    @endif
    @if(auth()->user()->hasPermission('attendance.import'))
        <a class="btn" href="{{ route('attendance.upload') }}">Standard Upload</a>
        <form method="post" action="{{ route('attendance.email.fetch') }}" onsubmit="return confirm('Check cc@isoadmin.co.za now and import unread CSV attachments?')">@csrf<button class="btn" type="submit">Fetch Email</button></form>
    @endif
    <a class="btn" href="{{ route('attendance.imports') }}">Import History</a>
</div>

<div class="att-tabs">
    <a href="{{ route('attendance.index', array_filter(['date_from' => $dateFrom, 'date_to' => $dateTo, 'search' => $search])) }}" class="{{ $viewMode === 'overview' ? 'active' : '' }}">Employee Overview</a>
    <a href="{{ route('attendance.index', array_filter(['view' => 'daily', 'date_from' => $dateFrom, 'date_to' => $dateTo, 'search' => $search])) }}" class="{{ $viewMode === 'daily' ? 'active' : '' }}">Daily Log</a>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="page-head-main" style="justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap">
        <div>
            <h2 style="margin:0">{{ $periodLabel }}</h2>
            <p class="muted small" style="margin:6px 0 0">Office hours: weekdays {{ $attendanceStartTime }} – {{ $attendanceCloseTime }}. Weekends and public holidays are not working days. Latest imported day: {{ $latestAttendanceDate ?? 'none' }}.</p>
        </div>
        <div class="actions right">
            <a class="btn" href="{{ route('attendance.index', array_filter(['view' => $viewMode === 'daily' ? 'daily' : null, 'date_from' => $previousMonth->toDateString(), 'date_to' => $previousMonth->copy()->endOfMonth()->toDateString(), 'search' => $search])) }}">← {{ $previousMonth->format('M Y') }}</a>
            <a class="btn" href="{{ route('attendance.index', array_filter(['view' => $viewMode === 'daily' ? 'daily' : null, 'search' => $search])) }}">Current Month</a>
            <a class="btn" href="{{ route('attendance.index', array_filter(['view' => $viewMode === 'daily' ? 'daily' : null, 'date_from' => $nextMonth->toDateString(), 'date_to' => $nextMonth->copy()->endOfMonth()->toDateString(), 'search' => $search])) }}">{{ $nextMonth->format('M Y') }} →</a>
        </div>
    </div>
    <form method="get" class="form-grid" style="margin-top:14px;grid-template-columns:repeat(4,1fr)">
        @if($viewMode === 'daily')<input type="hidden" name="view" value="daily">@endif
        <div class="form-row"><label>Date From</label><input type="date" name="date_from" value="{{ $dateFrom }}"></div>
        <div class="form-row"><label>Date To</label><input type="date" name="date_to" value="{{ $dateTo }}"></div>
        <div class="form-row"><label>Employee Search</label><input type="text" name="search" value="{{ $search }}" placeholder="Name, code or email"></div>
        <div class="form-row" style="align-self:end"><button class="btn primary full" type="submit">Apply Filter</button></div>
        @if($viewMode === 'daily')
        <div class="form-row" style="grid-column:1/-1"><label>Daily Log Flags</label>
            <label class="checkbox-line" style="display:inline-flex;gap:6px;margin-right:14px"><input type="checkbox" name="late_only" value="1" {{ request()->boolean('late_only') ? 'checked' : '' }} style="width:auto"> Late only</label>
            <label class="checkbox-line" style="display:inline-flex;gap:6px;margin-right:14px"><input type="checkbox" name="early_only" value="1" {{ request()->boolean('early_only') ? 'checked' : '' }} style="width:auto"> Early leave only</label>
            <label class="checkbox-line" style="display:inline-flex;gap:6px;margin-right:14px"><input type="checkbox" name="missing_only" value="1" {{ request()->boolean('missing_only') ? 'checked' : '' }} style="width:auto"> Missing checkout only</label>
            <label class="checkbox-line" style="display:inline-flex;gap:6px"><input type="checkbox" name="public_holidays_only" value="1" {{ request()->boolean('public_holidays_only') ? 'checked' : '' }} style="width:auto"> Public holidays only</label>
        </div>
        @endif
    </form>
</div>

<div class="grid cols-4">
    <div class="card metric"><span>Employees Tracked</span><strong>{{ $totals['employees'] }}</strong></div>
    <div class="card metric"><span>Late Arrivals</span><strong>{{ $totals['late_days'] }}</strong><span class="muted small">{{ $totals['late_label'] }} total</span></div>
    <div class="card metric"><span>Early Departures</span><strong>{{ $totals['early_days'] }}</strong><span class="muted small">{{ $totals['early_label'] }} total</span></div>
    <div class="card metric"><span>Missing Checkouts</span><strong>{{ $totals['missing_checkout_days'] }}</strong></div>
    <div class="card metric"><span>Absent Days</span><strong>{{ $totals['absent_days'] }}</strong></div>
    <div class="card metric"><span>On Leave Days</span><strong>{{ $totals['on_leave_days'] }}</strong></div>
    <div class="card metric"><span>Days Worked</span><strong>{{ $totals['present_days'] }}</strong><span class="muted small">of {{ $totals['scheduled_days'] }} scheduled</span></div>
    <div class="card metric"><span>Latest Import</span><strong style="font-size:18px">{{ optional(optional($latestImport)->created_at)->format('Y-m-d H:i') ?? 'None' }}</strong></div>
</div>
<div style="height:16px"></div>

@if($viewMode === 'overview')
<div class="card">
    <h2>Employee Overview</h2>
    <p class="muted small">One row per tracked employee for the selected period. Absent means a scheduled workday with no check-in and no approved leave. Employees with the most exceptions are listed first. Click a name for the full per-day history.</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Attendance</th>
                    <th>Days Worked</th>
                    <th>Late</th>
                    <th>Left Early</th>
                    <th>No Checkout</th>
                    <th>On Leave</th>
                    <th>Absent</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($overviewRows as $row)
                @php($rowEmployee = $row['employee'])
                @php($rowCode = $rowEmployee->employee_code ?: $rowEmployee->id)
                <tr>
                    <td>
                        <a href="{{ route('attendance.show', $rowCode) }}"><strong>{{ $rowEmployee->name }}</strong></a><br>
                        <span class="muted small">{{ $rowEmployee->employee_code ? 'Code: '.$rowEmployee->employee_code : 'User ID: '.$rowEmployee->id }}</span>
                    </td>
                    <td>
                        @if($row['attendance_rate'] !== null)
                            <span class="{{ $row['attendance_rate'] >= 90 ? 'num-good' : ($row['attendance_rate'] >= 70 ? 'num-warn' : 'num-bad') }}">{{ $row['attendance_rate'] }}%</span>
                            <br><span class="muted small">{{ $row['evaluable_days'] }} scheduled day(s)</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>{{ $row['present_days'] }}</td>
                    <td>
                        @if($row['late_days'] > 0)
                            <span class="num-bad">{{ $row['late_days'] }}×</span><br><span class="muted small">{{ $row['late_label'] }} total</span>
                        @else
                            <span class="muted">0</span>
                        @endif
                    </td>
                    <td>
                        @if($row['early_days'] > 0)
                            <span class="num-bad">{{ $row['early_days'] }}×</span><br><span class="muted small">{{ $row['early_label'] }} total</span>
                        @else
                            <span class="muted">0</span>
                        @endif
                    </td>
                    <td>
                        @if($row['missing_checkout_days'] > 0)
                            <span class="num-warn">{{ $row['missing_checkout_days'] }}×</span>
                        @else
                            <span class="muted">0</span>
                        @endif
                    </td>
                    <td>
                        @if($row['on_leave_days'] > 0)
                            <span class="num-warn">{{ $row['on_leave_days'] }}</span>
                        @else
                            <span class="muted">0</span>
                        @endif
                    </td>
                    <td>
                        @if($row['absent_days'] > 0)
                            <span class="num-bad">{{ $row['absent_days'] }}</span>
                        @else
                            <span class="muted">0</span>
                        @endif
                        @if($row['non_working_punch_days'] > 0)
                            <br><span class="muted small">+{{ $row['non_working_punch_days'] }} weekend/holiday punch(es)</span>
                        @endif
                    </td>
                    <td class="actions right"><a class="btn" href="{{ route('attendance.show', $rowCode) }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">No tracked employees found for this filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@else
<div class="card">
    <h2>Daily Attendance Log</h2>
    <p class="muted small">Earliest punch of the day is the check-in and the latest punch is the checkout. Office hours: {{ $attendanceStartTime }} – {{ $attendanceCloseTime }}, weekdays only.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Employee</th><th>Check-In</th><th>Checkout</th><th>Hours</th><th>Punches</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($days as $day)
                @php($employeeRouteCode = $day->user->employee_code ?: $day->user_id)
                <tr>
                    <td>{{ optional($day->attendance_date)->format('Y-m-d') }}<br><span class="muted small">{{ optional($day->attendance_date)->format('l') }}</span></td>
                    <td>
                        <a href="{{ route('attendance.show', $employeeRouteCode) }}"><strong>{{ $day->user->name }}</strong></a><br>
                        <span class="muted small">{{ $day->user->employee_code ? 'Code: '.$day->user->employee_code : 'User ID: '.$day->user_id }}</span>
                    </td>
                    <td>{{ optional($day->start_time)->format('H:i:s') ?? '-' }}</td>
                    <td>{{ optional($day->end_time)->format('H:i:s') ?? '-' }}</td>
                    <td>{{ $day->work_hours }}</td>
                    <td>{{ $day->record_count }}</td>
                    <td>
                        @if($day->is_public_holiday ?? false)
                            <span class="pill warn">Public Holiday</span><br><span class="muted small">{{ $day->public_holiday_name }}</span>
                        @elseif($day->is_weekend ?? false)
                            <span class="pill warn">Weekend</span>
                        @else
                            @if($day->is_late ?? false)
                                <span class="pill bad">{{ $day->late_label }}</span>
                            @endif
                            @if($day->is_early_leave ?? false)
                                <span class="pill bad">{{ $day->early_leave_label }}</span>
                            @endif
                            @if($day->start_time && !$day->end_time)
                                <span class="pill warn">No checkout</span>
                            @endif
                            @if(!$day->start_time)
                                <span class="pill off">No punch</span>
                            @endif
                            @if($day->start_time && $day->end_time && !($day->is_late ?? false) && !($day->is_early_leave ?? false))
                                <span class="pill">On time</span>
                            @endif
                        @endif
                    </td>
                    <td class="actions right"><a class="btn" href="{{ route('attendance.show', $employeeRouteCode) }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No attendance records found for this filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $days->links() }}</div>
</div>
@endif
@endsection
