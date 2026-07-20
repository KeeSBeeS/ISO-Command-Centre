@extends('layouts.app')
@section('title','Employee Attendance | ISO Admin')
@section('page_title','Employee Attendance')
@section('content')
<style>
    .employee-attendance-hero{background:linear-gradient(135deg,rgba(15,23,42,.04),rgba(59,130,246,.06))}
    .status-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid rgba(148,163,184,.18)}
    .status-row:last-child{border-bottom:none}
    .wide-record-table{overflow-x:auto}
    .wide-record-table table{min-width:1450px}
    .pill.bad{border-color:rgba(229,72,77,.4);background:rgba(229,72,77,.12);color:#ffd7d9}
    .pill.bad::before{background:#e5484d;box-shadow:0 0 0 4px rgba(229,72,77,.12)}
    .num-bad{color:#ff9d9f;font-weight:850}
    .cycle-bar{height:10px;border-radius:999px;background:rgba(255,255,255,.08);overflow:hidden;margin-top:10px}
    .cycle-bar span{display:block;height:100%;background:linear-gradient(90deg,var(--brand),var(--brand3))}
    details.audit-section summary{cursor:pointer;font-weight:850;font-size:17px;padding:4px 0}
</style>

<div class="actions" style="margin-bottom:14px">
    <a class="btn" href="{{ route('attendance.index') }}">← Attendance Overview</a>
    <a class="btn" href="{{ route('attendance.imports') }}">Import History</a>
</div>

<div class="card employee-attendance-hero">
    <div class="page-head-main" style="justify-content:space-between;gap:18px;align-items:flex-start">
        <div>
            <h2 style="margin:0">{{ $employee->name }}</h2>
            <p class="muted" style="margin:8px 0 0">
                Employee Code: <strong>{{ $employee->employee_code ?: 'Not set' }}</strong>
                @if($employee->attendance_name)
                    · Attendance Name: <strong>{{ $employee->attendance_name }}</strong>
                @endif
                @if($employee->email)
                    · {{ $employee->email }}
                @endif
            </p>
            <p class="muted small" style="margin:6px 0 0">
                History available: <strong>{{ $historyDateFrom ?: 'none' }}</strong> to <strong>{{ $historyDateTo ?: 'none' }}</strong>.
                Showing: <strong>{{ $activeDateFrom ?: '-' }}</strong> to <strong>{{ $activeDateTo ?: '-' }}</strong>.
            </p>
        </div>
        <div class="pill">Office hours {{ $attendanceStartTime }} – {{ $attendanceCloseTime }}, weekdays</div>
    </div>
</div>

<div style="height:16px"></div>

<div class="card">
    <form method="get" class="form-grid">
        <div class="form-row">
            <label>Date From</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}">
        </div>
        <div class="form-row">
            <label>Date To</label>
            <input type="date" name="date_to" value="{{ $dateTo }}">
        </div>
        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="">All statuses</option>
                @foreach($statusBreakdown as $status)
                    <option value="{{ $status->attendance_status }}" @selected(request('status') === $status->attendance_status)>{{ $status->attendance_status ?: 'Blank' }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-row">
            <label>Search Imported Records</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="CSV name, status or checkpoint">
        </div>
        <div class="form-row" style="align-self:end">
            <button class="btn primary full" type="submit">Filter</button>
        </div>
        <div class="form-row" style="align-self:end">
            <a class="btn full" href="{{ route('attendance.show', $employeeCode) }}">Show Full History</a>
        </div>
    </form>
</div>

<div style="height:16px"></div>

<div class="grid cols-4">
    <div class="card metric"><span>Days Worked</span><strong>{{ $periodSummary['present_days'] }}</strong></div>
    <div class="card metric"><span>Late Arrivals</span><strong>{{ $periodSummary['late_days'] }}</strong><span class="muted small">{{ $periodSummary['late_label'] }} total</span></div>
    <div class="card metric"><span>Early Departures</span><strong>{{ $periodSummary['early_leave_days'] }}</strong><span class="muted small">{{ $periodSummary['early_leave_label'] }} total</span></div>
    <div class="card metric"><span>Missing Checkouts</span><strong>{{ $periodSummary['missing_checkout_days'] }}</strong></div>
    <div class="card metric"><span>Absent Days</span><strong>{{ $periodSummary['absent_days'] }}</strong><span class="muted small">No check-in, no leave</span></div>
    <div class="card metric"><span>On Leave Days</span><strong>{{ $periodSummary['on_leave_days'] }}</strong></div>
    <div class="card metric"><span>Imported Raw Records</span><strong>{{ $summary['raw_count'] }}</strong></div>
    <div class="card metric"><span>Days With Records</span><strong>{{ $summary['day_count'] }}</strong></div>
</div>

@if($sickCycleSummary || $leaveSummary)
<div style="height:16px"></div>
<div class="grid cols-2">
    @if($sickCycleSummary)
    <div class="card">
        <h2>Sick Leave Cycle</h2>
        <p class="muted small" style="margin:4px 0 10px">
            Cycle {{ $sickCycleSummary['cycle_number'] }}:
            <strong>{{ $sickCycleSummary['cycle_start']->format('Y-m-d') }}</strong> to <strong>{{ $sickCycleSummary['cycle_end']->format('Y-m-d') }}</strong>
            ({{ $sickCycleSummary['cycle_months'] }} months, anchored to the employee start date).
        </p>
        <div class="status-row"><span>Entitlement (6 weeks per cycle)</span><strong>{{ $sickCycleSummary['entitlement_days'] }} working days</strong></div>
        <div class="status-row"><span>Used this cycle</span><strong>{{ $sickCycleSummary['used_days'] }} day(s)</strong></div>
        <div class="status-row"><span>Remaining</span><strong>{{ $sickCycleSummary['remaining_days'] }} day(s)</strong></div>
        @if($sickCycleSummary['over_entitlement_days'] > 0)
            <div class="status-row"><span>Over entitlement</span><strong class="num-bad">{{ $sickCycleSummary['over_entitlement_days'] }} day(s)</strong></div>
        @endif
        <div class="cycle-bar"><span style="width:{{ $sickCycleSummary['used_percent'] }}%"></span></div>
        @if(auth()->user()->hasPermission('sick_leave.view'))
            <div class="actions" style="margin-top:12px"><a class="btn" href="{{ route('sick_leave.index') }}">Sick Leave Register</a></div>
        @endif
    </div>
    @endif
    @if($leaveSummary)
    <div class="card">
        <h2>Paid Leave {{ $leaveSummary['year'] }}</h2>
        <p class="muted small" style="margin:4px 0 10px">Annual paid leave runs 1 January to 31 December. Allocations are set by a director.</p>
        <div class="status-row"><span>Allocated for {{ $leaveSummary['year'] }}</span><strong>{{ rtrim(rtrim(number_format($leaveSummary['allocated_days'], 2), '0'), '.') }} day(s)</strong></div>
        <div class="status-row"><span>Carried over</span><strong>{{ rtrim(rtrim(number_format($leaveSummary['carried_over_days'], 2), '0'), '.') }} day(s)</strong></div>
        <div class="status-row"><span>Used (normal leave)</span><strong>{{ rtrim(rtrim(number_format($leaveSummary['used_normal_days'], 2), '0'), '.') }} day(s)</strong></div>
        <div class="status-row"><span>Remaining</span><strong>{{ rtrim(rtrim(number_format($leaveSummary['remaining_normal_days'], 2), '0'), '.') }} day(s)</strong></div>
        @if(!$leaveSummary['allocation'])
            <p class="muted small" style="margin-top:10px">No allocation captured for {{ $leaveSummary['year'] }} yet.</p>
        @endif
        @if(auth()->user()->hasPermission('leave_allocations.view'))
            <div class="actions" style="margin-top:12px"><a class="btn" href="{{ route('leave_allocations.index') }}">Leave Allocations</a></div>
        @endif
    </div>
    @endif
</div>
@endif

<div style="height:16px"></div>

<div class="card">
    <h2>Day-by-Day Attendance</h2>
    <p class="muted small">Every scheduled workday in the selected period. The earliest punch is the check-in, the latest punch is the checkout. Weekend or public holiday rows only appear when the employee badged in on such a day.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Day</th><th>Check-In</th><th>Checkout</th><th>Hours</th><th>Punches</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($dayLog as $entry)
                @php($day = $entry['day'])
                <tr>
                    <td><strong>{{ $entry['date'] }}</strong></td>
                    <td>{{ $entry['weekday'] }}@if($entry['holiday_name'])<br><span class="muted small">{{ $entry['holiday_name'] }}</span>@endif</td>
                    <td>{{ $day && $day->start_time ? $day->start_time->format('H:i:s') : '-' }}</td>
                    <td>{{ $day && $day->end_time ? $day->end_time->format('H:i:s') : '-' }}</td>
                    <td>{{ $day ? $day->work_hours : '-' }}</td>
                    <td>{{ $day ? $day->record_count : 0 }}</td>
                    <td><span class="pill {{ $entry['status']['class'] === 'ok' ? '' : $entry['status']['class'] }}">{{ $entry['status']['label'] }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No attendance history found for this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($dayLogTotal > $dayLog->count())
        <p class="muted small" style="margin-top:10px">Showing the most recent {{ $dayLog->count() }} of {{ $dayLogTotal }} days. Narrow the date filter to inspect older periods.</p>
    @endif
</div>

<div style="height:16px"></div>

<div class="card">
    <h2>Monthly Summary (Last 12 Months)</h2>
    <p class="muted small">Per-month totals so patterns of late arrivals, early departures and absences are easy to spot.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Month</th><th>Days Worked</th><th>Late</th><th>Left Early</th><th>No Checkout</th><th>On Leave</th><th>Absent</th><th>Attendance</th></tr></thead>
            <tbody>
            @forelse($monthlyTrend as $month)
                <tr>
                    <td><strong>{{ $month['month_label'] }}</strong></td>
                    <td>{{ $month['present_days'] }} / {{ $month['evaluable_days'] }}</td>
                    <td>@if($month['late_days'] > 0){{ $month['late_days'] }}× <span class="muted small">({{ $month['late_label'] }})</span>@else<span class="muted">0</span>@endif</td>
                    <td>@if($month['early_days'] > 0){{ $month['early_days'] }}× <span class="muted small">({{ $month['early_label'] }})</span>@else<span class="muted">0</span>@endif</td>
                    <td>{{ $month['missing_checkout_days'] ?: '0' }}</td>
                    <td>{{ $month['on_leave_days'] ?: '0' }}</td>
                    <td>@if($month['absent_days'] > 0)<span class="pill bad">{{ $month['absent_days'] }}</span>@else<span class="muted">0</span>@endif</td>
                    <td>{{ $month['attendance_rate'] !== null ? $month['attendance_rate'] . '%' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No monthly history available yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div style="height:16px"></div>

<details class="card audit-section">
    <summary>Audit Data · Raw Punches, Punch History and Import Sources</summary>
    <div style="height:12px"></div>

    <div class="grid cols-2">
        <div class="card">
            <h3>Record Timeline</h3>
            <p><strong>First imported punch:</strong> {{ $summary['first_record'] ? \Illuminate\Support\Carbon::parse($summary['first_record'])->format('Y-m-d H:i:s') : 'None' }}</p>
            <p><strong>Latest imported punch:</strong> {{ $summary['last_record'] ? \Illuminate\Support\Carbon::parse($summary['last_record'])->format('Y-m-d H:i:s') : 'None' }}</p>
            <p class="muted small">Import batches for this employee: {{ $summary['import_count'] }}</p>
        </div>
        <div class="card">
            <h3>Status Breakdown</h3>
            @forelse($statusBreakdown as $status)
                <div class="status-row">
                    <span>{{ $status->attendance_status ?: 'Blank' }}</span>
                    <strong>{{ $status->total }}</strong>
                </div>
            @empty
                <p class="muted">No imported statuses found for this employee.</p>
            @endforelse
        </div>
    </div>

    <div style="height:16px"></div>

    <div class="card">
        <h3>Punch History by Date</h3>
        <p class="muted small">Built directly from raw imported punch records — every date with records, even when the daily summary is missing or incomplete.</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Date</th><th>First Punch</th><th>Last Punch</th><th>Total Imported Records</th></tr></thead>
                <tbody>
                @forelse($punchHistory as $historyDay)
                    <tr>
                        <td><strong>{{ optional($historyDay->attendance_date)->format('Y-m-d') }}</strong></td>
                        <td>{{ $historyDay->first_punch ? \Illuminate\Support\Carbon::parse($historyDay->first_punch)->format('H:i:s') : '-' }}</td>
                        <td>{{ $historyDay->last_punch ? \Illuminate\Support\Carbon::parse($historyDay->last_punch)->format('H:i:s') : '-' }}</td>
                        <td>{{ $historyDay->total_records }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No raw punch history found for this employee and filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $punchHistory->links() }}</div>
    </div>

    <div style="height:16px"></div>

    <div class="card">
        <h3>Import Sources</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Imported</th><th>Source</th><th>File</th><th>Subject / Note</th><th>Matched</th><th>Skipped</th></tr></thead>
                <tbody>
                @forelse($imports as $import)
                    <tr>
                        <td>{{ optional($import->created_at)->format('Y-m-d H:i') }}</td>
                        <td>{{ ucfirst(str_replace('_',' ', $import->source ?? 'unknown')) }}</td>
                        <td>{{ $import->filename ?? 'Unknown' }}</td>
                        <td>{{ $import->received_subject ?? '-' }}</td>
                        <td>{{ $import->matched_rows }}</td>
                        <td>{{ $import->skipped_rows }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No import source records found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div style="height:16px"></div>

    <div class="card">
        <h3>All Imported Raw Records</h3>
        <p class="muted small">Every raw CSV punch record currently linked to this employee, newest first.</p>
        <div class="table-wrap wide-record-table">
            <table>
                <thead>
                    <tr>
                        <th>Recorded Time</th>
                        <th>Person ID</th>
                        <th>Name in CSV</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Attendance Check Point</th>
                        <th>Custom Name</th>
                        <th>Data Source</th>
                        <th>Handling Type</th>
                        <th>Temperature</th>
                        <th>Abnormal</th>
                        <th>Import Source</th>
                        <th>Imported At</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rawRecords as $record)
                    <tr>
                        <td>{{ optional($record->recorded_at)->format('Y-m-d H:i:s') ?? '-' }}</td>
                        <td>{{ $record->person_id ?? '-' }}</td>
                        <td>{{ $record->employee_name }}</td>
                        <td>{{ $record->department ?? '-' }}</td>
                        <td><span class="pill">{{ $record->attendance_status ?: 'Blank' }}</span></td>
                        <td>{{ $record->attendance_check_point ?? '-' }}</td>
                        <td>{{ $record->custom_name ?? '-' }}</td>
                        <td>{{ $record->data_source ?? '-' }}</td>
                        <td>{{ $record->handling_type ?? '-' }}</td>
                        <td>{{ $record->temperature ?? '-' }}</td>
                        <td>{{ $record->abnormal ?? '-' }}</td>
                        <td>
                            {{ optional($record->import)->filename ?? 'Unknown file' }}<br>
                            <span class="muted small">{{ ucfirst(str_replace('_',' ', optional($record->import)->source ?? 'unknown')) }}</span>
                        </td>
                        <td>{{ optional($record->created_at)->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="muted">No raw imported records found for this employee and filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $rawRecords->links() }}</div>
    </div>
</details>
@endsection
