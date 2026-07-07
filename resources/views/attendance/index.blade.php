@extends('layouts.app')
@section('title','Time Attendance | ISO Admin')
@section('page_title','Time Attendance')
@section('content')
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

<div class="grid cols-4">
    <div class="card metric"><span>Employees Present</span><strong>{{ $presentCount }}</strong></div>
    <div class="card metric"><span>Absent / No Punch</span><strong>{{ $absentCount ?? 0 }}</strong></div>
    <div class="card metric"><span>Late Clock-ins</span><strong>{{ $lateClockInCount ?? 0 }}</strong></div>
    <div class="card metric"><span>Raw Records</span><strong>{{ $recordCount }}</strong></div>
    <div class="card metric"><span>Single Punch Flags</span><strong>{{ $singleRecordCount }}</strong></div>
    <div class="card metric"><span>Public Holiday Records</span><strong>{{ $publicHolidayAttendanceCount ?? 0 }}</strong></div>
    <div class="card metric"><span>Latest Import</span><strong style="font-size:18px">{{ optional(optional($latestImport)->created_at)->format('Y-m-d H:i') ?? 'None' }}</strong></div>
    <div class="card metric"><span>Latest Attendance Date</span><strong style="font-size:18px">{{ $latestAttendanceDate ?? 'None' }}</strong></div>
</div>
<div style="height:16px"></div>

<div class="card">
    <form method="get" class="form-grid">
        <div class="form-row"><label>Date From</label><input type="date" name="date_from" value="{{ $dateFrom }}"></div>
        <div class="form-row"><label>Date To</label><input type="date" name="date_to" value="{{ $dateTo }}"></div>
        <div class="form-row"><label>Employee Search</label><input type="text" name="search" value="{{ request('search') }}" placeholder="Name, attendance name or email"></div>
        <div class="form-row"><label>Flags</label><label class="checkbox-line"><input type="checkbox" name="late_only" value="1" {{ request()->boolean('late_only') ? 'checked' : '' }}> Late only</label><label class="checkbox-line"><input type="checkbox" name="public_holidays_only" value="1" {{ request()->boolean('public_holidays_only') ? 'checked' : '' }}> Public holidays only</label></div>
        <div class="form-row" style="align-self:end"><button class="btn primary full" type="submit">Filter</button></div>
    </form>
</div>
<div style="height:16px"></div>

<div class="card">
    <h2>Daily Attendance Summary</h2>
    <p class="muted small">Clock-in is accepted until 09:00. If more than one record exists before 09:00, the earliest one is kept as check-in. If no record exists before 09:00, the earliest available time is flagged late. Latest different time is used as checkout. Public holidays are company-closed days and are retained for audit only. This page defaults to the latest imported attendance date when no filter is selected.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Employee</th><th>Start</th><th>Checkout</th><th>Hours</th><th>Records</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($days as $day)
                <tr>
                    <td>{{ optional($day->attendance_date)->format('Y-m-d') }}</td>
                    <td><strong>{{ $day->user->name }}</strong><br><span class="muted small">{{ $day->source_names }}</span></td>
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
                    <td class="actions right"><a class="btn" href="{{ route('attendance.show',$day) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No attendance records found for this filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $days->links() }}</div>
</div>
@endsection
