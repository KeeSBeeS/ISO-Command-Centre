@extends('layouts.app')
@section('title','Employee Attendance Records | ISO Admin')
@section('page_title','Employee Attendance Records')
@section('content')
<div class="actions" style="margin-bottom:14px">
    <a class="btn" href="{{ route('attendance.index') }}">Back to Attendance Dashboard</a>
    <a class="btn" href="{{ route('attendance.imports') }}">Import History</a>
</div>

<div class="card employee-attendance-hero">
    <div class="page-head-main" style="justify-content:space-between;gap:18px;align-items:flex-start">
        <div>
            <p class="muted small" style="margin:0 0 6px">/attendance/{{ $employeeCode }}</p>
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
        </div>
        <div class="pill">Office {{ $attendanceStartTime }} - {{ $attendanceCloseTime }}</div>
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
            <input type="text" name="search" value="{{ request('search') }}" placeholder="CSV name or status">
        </div>
        <div class="form-row" style="align-self:end">
            <button class="btn primary full" type="submit">Filter Records</button>
        </div>
        <div class="form-row" style="align-self:end">
            <a class="btn full" href="{{ route('attendance.show', $employeeCode) }}">Clear Filters</a>
        </div>
    </form>
</div>

<div style="height:16px"></div>

<div class="grid cols-4">
    <div class="card metric"><span>Imported Raw Records</span><strong>{{ $summary['raw_count'] }}</strong></div>
    <div class="card metric"><span>Days With Records</span><strong>{{ $summary['day_count'] }}</strong></div>
    <div class="card metric"><span>Import Batches</span><strong>{{ $summary['import_count'] }}</strong></div>
    <div class="card metric"><span>Attendance Days</span><strong>{{ $lateSummary['days'] }}</strong></div>
    <div class="card metric"><span>Late Days</span><strong>{{ $lateSummary['late_days'] }}</strong></div>
    <div class="card metric"><span>Total Late Time</span><strong style="font-size:22px">{{ $lateSummary['late_label'] }}</strong></div>
    <div class="card metric"><span>Early Leave Days</span><strong>{{ $lateSummary['early_leave_days'] }}</strong></div>
    <div class="card metric"><span>Total Early Leave</span><strong style="font-size:22px">{{ $lateSummary['early_leave_label'] }}</strong></div>
</div>

<div style="height:16px"></div>

<div class="grid cols-2">
    <div class="card">
        <h2>Record Timeline</h2>
        <p><strong>First imported punch:</strong> {{ $summary['first_record'] ? \Illuminate\Support\Carbon::parse($summary['first_record'])->format('Y-m-d H:i:s') : 'None' }}</p>
        <p><strong>Latest imported punch:</strong> {{ $summary['last_record'] ? \Illuminate\Support\Carbon::parse($summary['last_record'])->format('Y-m-d H:i:s') : 'None' }}</p>
        <p class="muted small">This page shows all raw records linked to this employee from imported CSV files. Use the filters above for date-specific audits.</p>
    </div>

    <div class="card">
        <h2>Status Breakdown</h2>
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
    <h2>Import Sources</h2>
    <p class="muted small">Latest import batches that produced records for this employee.</p>
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
    <h2>Daily Attendance Summary</h2>
    <p class="muted small">Daily rebuilt attendance records for this employee. These are the grouped day summaries created from the raw imported punches.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Start</th><th>Checkout</th><th>Hours</th><th>Records</th><th>Status</th><th>Source Names</th></tr></thead>
            <tbody>
            @forelse($dailySummaries as $day)
                <tr>
                    <td>{{ optional($day->attendance_date)->format('Y-m-d') }}</td>
                    <td>{{ optional($day->start_time)->format('H:i:s') ?? '-' }}<br><span class="muted small">{{ $day->first_status }}</span></td>
                    <td>{{ optional($day->end_time)->format('H:i:s') ?? '-' }}<br><span class="muted small">{{ $day->last_status }}</span></td>
                    <td>{{ $day->work_hours }}</td>
                    <td>{{ $day->record_count }}</td>
                    <td>
                        @if($day->is_public_holiday ?? false)
                            <span class="pill warning">Public Holiday</span><br><span class="muted small">{{ $day->public_holiday_name }}</span>
                        @elseif($day->is_late ?? false)
                            <span class="pill off">Late</span><br><span class="muted small">{{ $day->late_label }}</span>
                        @elseif($day->anomalies)
                            <span class="pill off">Flagged</span><br><span class="muted small">{{ $day->anomalies }}</span>
                        @else
                            <span class="pill">OK</span>
                        @endif
                    </td>
                    <td>{{ $day->source_names }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No daily summaries found for this employee and filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $dailySummaries->links() }}</div>
</div>

<div style="height:16px"></div>

<div class="card">
    <h2>All Imported Raw Records</h2>
    <p class="muted small">Every raw CSV punch record currently linked to this employee is shown here. Records are sorted from newest to oldest.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Recorded Time</th><th>Date</th><th>Name in CSV</th><th>Status</th><th>Import Source</th><th>Imported At</th></tr></thead>
            <tbody>
            @forelse($rawRecords as $record)
                <tr>
                    <td>{{ optional($record->recorded_at)->format('Y-m-d H:i:s') ?? '-' }}</td>
                    <td>{{ optional($record->attendance_date)->format('Y-m-d') ?? '-' }}</td>
                    <td>{{ $record->employee_name }}</td>
                    <td><span class="pill">{{ $record->attendance_status ?: 'Blank' }}</span></td>
                    <td>
                        {{ optional($record->import)->filename ?? 'Unknown file' }}<br>
                        <span class="muted small">{{ ucfirst(str_replace('_',' ', optional($record->import)->source ?? 'unknown')) }}</span>
                    </td>
                    <td>{{ optional($record->created_at)->format('Y-m-d H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No raw imported records found for this employee and filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $rawRecords->links() }}</div>
</div>

<style>
    .employee-attendance-hero{background:linear-gradient(135deg,rgba(15,23,42,.04),rgba(59,130,246,.06))}
    .status-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid rgba(148,163,184,.18)}
    .status-row:last-child{border-bottom:none}
</style>
@endsection
