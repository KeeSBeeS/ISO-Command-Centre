@extends('layouts.app')
@section('title','Calendar | ISO Admin')
@section('page_title','Calendar')
@section('content')
@php
    $activeFilterQuery = array_filter([
        'types' => $selectedTypes ?? [],
        'filter_submitted' => ($filterSubmitted ?? false) ? 1 : null,
    ], fn($value) => $value !== null && $value !== []);
@endphp
<style>
    .calendar-toolbar{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:14px}.calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:8px}.calendar-weekday{font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);padding:0 8px 6px}.calendar-day{min-height:138px;border:1px solid var(--line);border-radius:16px;padding:10px;background:rgba(255,255,255,.035)}.calendar-day.outside{opacity:.45;background:rgba(255,255,255,.02)}.calendar-day.today{border-color:rgba(139,220,101,.65);box-shadow:0 0 0 1px rgba(139,220,101,.15) inset}.calendar-date{display:flex;align-items:center;justify-content:space-between;font-weight:900;margin-bottom:8px}.calendar-date small{font-weight:700;color:var(--muted)}.calendar-event{display:block;border-radius:12px;padding:7px 8px;margin-top:6px;background:rgba(255,255,255,.055);border:1px solid var(--line);font-size:12px;text-decoration:none;color:var(--text);line-height:1.35}.calendar-event strong{display:block}.calendar-event .meta{color:var(--muted)}.calendar-event.public_holiday{background:rgba(245,185,76,.16);border-color:rgba(245,185,76,.35);color:#ffe2a4}.calendar-event.leave{background:rgba(18,163,116,.16);border-color:rgba(139,220,101,.22)}.calendar-event.employee_document,.calendar-event.vehicle_document{background:rgba(78,160,255,.14);border-color:rgba(78,160,255,.28)}.calendar-event.vehicle_service{background:rgba(255,137,74,.15);border-color:rgba(255,137,74,.28)}.calendar-event.attendance{background:rgba(255,74,74,.13);border-color:rgba(255,74,74,.3)}.calendar-event.tracking{background:rgba(152,112,255,.14);border-color:rgba(152,112,255,.3)}.calendar-event.pending,.calendar-event.warning,.calendar-event.late,.calendar-event.overdue,.calendar-event.expired{box-shadow:0 0 0 1px rgba(245,185,76,.12) inset}.calendar-more{font-size:12px;color:var(--muted);margin-top:6px}.legend{display:flex;flex-wrap:wrap;gap:8px}.legend span{border:1px solid var(--line);border-radius:999px;padding:7px 10px;font-size:12px;color:var(--muted);background:rgba(255,255,255,.035)}.calendar-filter-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.calendar-filter-tile{border:1px solid var(--line);background:rgba(255,255,255,.035);border-radius:14px;padding:10px;display:flex;gap:8px;align-items:center;font-weight:800}.calendar-filter-tile input{width:18px;height:18px}.calendar-filter-tile .filter-icon{font-size:18px}.calendar-filter-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;margin-top:12px}.reminder-list{display:grid;gap:8px}.reminder-row{display:flex;justify-content:space-between;gap:12px;align-items:center;border:1px solid var(--line);border-radius:14px;padding:10px;background:rgba(255,255,255,.035)}.reminder-row a{text-decoration:none;color:var(--text)}@media(max-width:1100px){.calendar-filter-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:920px){.calendar-grid{grid-template-columns:repeat(2,1fr)}.calendar-weekday{display:none}.calendar-toolbar{display:block}.calendar-toolbar .actions{margin-top:12px}.calendar-day{min-height:auto}}@media(max-width:620px){.calendar-grid,.calendar-filter-grid{grid-template-columns:1fr}.reminder-row{display:block}.reminder-row .actions{margin-top:8px}.calendar-filter-actions{justify-content:flex-start}.calendar-filter-actions .btn{width:100%}}
</style>

<div class="calendar-toolbar">
    <div>
        <h2 style="margin:0">{{ $monthStart->format('F Y') }}</h2>
        <p class="muted">Central company calendar. Week starts on Sunday and can show leave, public holidays, attendance exceptions, document expiries, vehicle service reminders and tracking reminders.</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('calendar.index', array_merge(['month' => $previousMonth], $activeFilterQuery)) }}">Previous</a>
        <a class="btn" href="{{ route('calendar.index', array_merge(['month' => now()->format('Y-m')], $activeFilterQuery)) }}">Current</a>
        <a class="btn" href="{{ route('calendar.index', array_merge(['month' => $nextMonth], $activeFilterQuery)) }}">Next</a>
        @if(auth()->user()->hasPermission('leave.create'))<a class="btn primary" href="{{ route('leave.create') }}">Add Leave</a>@endif
    </div>
</div>

<div class="card" style="margin-bottom:14px">
    <form method="get" action="{{ route('calendar.index') }}">
        <input type="hidden" name="month" value="{{ $monthStart->format('Y-m') }}">
        <input type="hidden" name="filter_submitted" value="1">
        <div class="actions" style="justify-content:space-between;margin-bottom:10px">
            <div>
                <h2>Calendar Filters</h2>
                <p class="muted">Select only the reminder types you want to see. This keeps the calendar readable on mobile.</p>
            </div>
            <div class="legend">
                <span>{{ $calendarEvents->count() }} visible dated items</span>
                <span>{{ $undatedReminders->count() }} visible operational reminders</span>
            </div>
        </div>
        <div class="calendar-filter-grid">
            @foreach($availableTypes as $typeKey => $type)
                <label class="calendar-filter-tile">
                    <input type="checkbox" name="types[]" value="{{ $typeKey }}" @checked(in_array($typeKey, $selectedTypes ?? [], true))>
                    <span class="filter-icon">{{ $type['icon'] }}</span>
                    <span>{{ $type['label'] }}</span>
                </label>
            @endforeach
        </div>
        <div class="calendar-filter-actions">
            <button class="btn primary" type="submit">Apply Filters</button>
            <a class="btn" href="{{ route('calendar.index', ['month' => $monthStart->format('Y-m')]) }}">Show All</a>
        </div>
    </form>
</div>

<div class="grid cols-4" style="margin-bottom:14px">
    <div class="card metric"><span>Leave Records</span><strong>{{ $summary['leave'] ?? 0 }}</strong></div>
    <div class="card metric"><span>Document Reminders</span><strong>{{ ($summary['employee_document'] ?? 0) + ($summary['vehicle_document'] ?? 0) }}</strong></div>
    <div class="card metric"><span>Attendance Exceptions</span><strong>{{ $summary['attendance'] ?? 0 }}</strong></div>
    <div class="card metric"><span>Fleet Reminders</span><strong>{{ ($summary['vehicle_service'] ?? 0) + ($summary['tracking'] ?? 0) }}</strong></div>
</div>

<div class="card" style="margin-bottom:14px">
    <div class="legend">
        @foreach($availableTypes as $typeKey => $type)
            @if(in_array($typeKey, $selectedTypes ?? [], true))
                <span>{{ $type['icon'] }} {{ $type['label'] }}</span>
            @endif
        @endforeach
        @if(empty($selectedTypes))
            <span>No item types selected</span>
        @endif
    </div>
</div>

<div class="card">
    <div class="calendar-grid" style="margin-bottom:8px">
        <div class="calendar-weekday">Sunday</div>
        <div class="calendar-weekday">Monday</div>
        <div class="calendar-weekday">Tuesday</div>
        <div class="calendar-weekday">Wednesday</div>
        <div class="calendar-weekday">Thursday</div>
        <div class="calendar-weekday">Friday</div>
        <div class="calendar-weekday">Saturday</div>
    </div>
    <div class="calendar-grid">
        @foreach($days as $day)
            <div class="calendar-day {{ $day['isCurrentMonth'] ? '' : 'outside' }} {{ $day['isToday'] ? 'today' : '' }}">
                <div class="calendar-date">
                    <span>{{ $day['date']->format('D d') }}</span>
                    @if($day['isToday'])<small>Today</small>@endif
                </div>

                @forelse($day['events']->take(5) as $event)
                    @php($eventTag = ($event['type'] ?? '') . ' ' . ($event['status'] ?? ''))
                    @if(!empty($event['url']))
                        <a class="calendar-event {{ $eventTag }}" href="{{ $event['url'] }}">
                            <strong>{{ $event['icon'] ?? '•' }} {{ $event['title'] }}</strong>
                            <span class="meta">{{ $event['subtitle'] ?? '' }}</span>
                        </a>
                    @else
                        <span class="calendar-event {{ $eventTag }}">
                            <strong>{{ $event['icon'] ?? '•' }} {{ $event['title'] }}</strong>
                            <span class="meta">{{ $event['subtitle'] ?? '' }}</span>
                        </span>
                    @endif
                @empty
                    <span class="muted small">No selected reminders</span>
                @endforelse

                @if($day['events']->count() > 5)
                    <div class="calendar-more">+{{ $day['events']->count() - 5 }} more selected reminders</div>
                @endif
            </div>
        @endforeach
    </div>
</div>

<div style="height:14px"></div>
<div class="card">
    <div class="actions" style="justify-content:space-between;margin-bottom:10px">
        <div><h2>Operational Reminder Centre</h2><p class="muted">ODO-based and sync-based reminders are listed here. Calendar filters also apply to this reminder centre.</p></div>
    </div>
    <div class="reminder-list">
        @forelse($undatedReminders as $reminder)
            <div class="reminder-row">
                <div>
                    <strong>{{ $reminder['icon'] ?? '•' }} {{ $reminder['title'] }}</strong>
                    <div class="muted small">{{ $reminder['subtitle'] ?? '' }}</div>
                </div>
                <div class="actions">
                    @if(!empty($reminder['url']))<a class="btn" href="{{ $reminder['url'] }}">Open</a>@endif
                </div>
            </div>
        @empty
            <p class="muted">No selected operational reminders are due.</p>
        @endforelse
    </div>
</div>

<div style="height:14px"></div>
<div class="grid cols-2">
    @if(in_array('leave', $selectedTypes ?? [], true))
    <div class="card">
        <h2>Leave This Month</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($leaveRequests as $leave)
                    <tr><td>{{ $leave->user->name }}</td><td>{{ optional($leave->leaveType)->name ?? 'Leave' }}</td><td>{{ $leave->date_range_label }}</td><td><span class="pill {{ $leave->status === 'approved' ? '' : ($leave->status === 'pending' ? 'warning' : 'off') }}">{{ $leave->status_label }}</span></td></tr>
                @empty
                    <tr><td colspan="4" class="muted">No leave requests in this month.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if(in_array('public_holiday', $selectedTypes ?? [], true))
    <div class="card">
        <h2>Public Holidays This Month</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Date</th><th>Holiday</th><th>Attendance</th></tr></thead>
                <tbody>
                @forelse($publicHolidays as $holiday)
                    <tr><td>{{ $holiday->holiday_date->format('Y-m-d') }}</td><td>{{ $holiday->name }}</td><td><span class="pill warning">Company closed</span></td></tr>
                @empty
                    <tr><td colspan="3" class="muted">No public holidays in this month.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
