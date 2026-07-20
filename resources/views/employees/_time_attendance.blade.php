{{-- Self-contained employee Time & Attendance date-range register (v2.9.3). --}}
{{-- Include with: @include('employees._time_attendance')  (needs $employee in scope). --}}
@php($attendanceOverview = \App\Support\EmployeeAttendanceOverview::build(request(), $employee))
@if($attendanceOverview && auth()->user()->hasAnyPermission(['attendance.view', 'attendance.late.view']))
<div class="card" id="time-attendance">
    <div class="actions" style="justify-content:space-between;align-items:flex-start">
        <div>
            <h2 style="margin-bottom:6px">Time &amp; Attendance</h2>
            <p class="muted">Daily clock-in / clock-out register against the {{ $attendanceOverview['timetable'] }} shift. Select a date range to see check-in, checkout and how late each day was at a glance.</p>
        </div>
        @if(auth()->user()->hasPermission('attendance.view') && $attendanceOverview['has_data'] && \Illuminate\Support\Facades\Route::has('attendance.show'))
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
