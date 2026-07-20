@extends('layouts.app')
@section('title','Calendar | ISO Admin')
@section('page_title','Calendar')
@section('content')
@php
    $activeFilterQuery = array_filter([
        'types' => $selectedTypes ?? [],
        'filter_submitted' => ($filterSubmitted ?? false) ? 1 : null,
    ], fn($value) => $value !== null && $value !== []);

    $statusPillClass = function (?string $status): string {
        return match ($status) {
            'approved', 'ok' => '',
            'pending', 'due-soon', 'warning', 'reminder', 'holiday' => 'warn',
            default => 'off',
        };
    };
@endphp
<style>
    /* Page header */
    .cal-toolbar{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:14px}
    .cal-toolbar .actions{flex-wrap:wrap}

    /* Filters */
    .legend{display:flex;flex-wrap:wrap;gap:8px}
    .legend span{border:1px solid var(--line);border-radius:999px;padding:7px 10px;font-size:12px;color:var(--muted);background:rgba(15,23,42,.035)}
    .cal-filter-head{display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:14px}
    .cal-filter-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    .cal-chip{position:relative;display:flex;gap:9px;align-items:center;border:1px solid var(--line);background:rgba(15,23,42,.03);border-radius:14px;padding:11px 12px;font-weight:800;font-size:13px;cursor:pointer;transition:background .18s ease,border-color .18s ease,transform .18s ease}
    .cal-chip:hover{transform:translateY(-1px);border-color:rgba(15,23,42,.22)}
    .cal-chip:focus-within{outline:2px solid rgba(14,157,104,.55);outline-offset:2px}
    .cal-chip input{position:absolute;opacity:0;width:0;height:0}
    .cal-chip .chip-icon{font-size:17px;flex:0 0 auto}
    .cal-chip .chip-check{margin-left:auto;flex:0 0 16px;width:16px;height:16px;border-radius:6px;border:1px solid var(--line);background:rgba(15,23,42,.04);display:grid;place-items:center;font-size:11px;color:transparent}
    .cal-chip.is-active{background:linear-gradient(135deg,rgba(14,157,104,.18),rgba(56,193,114,.06));border-color:var(--line-strong)}
    .cal-chip.is-active .chip-check{background:linear-gradient(135deg,var(--brand),var(--brand2));border-color:transparent;color:#061017}
    .cal-filter-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;margin-top:12px}

    /* Month grid */
    .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:8px}
    .cal-weekday{font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.09em;color:var(--muted2);padding:0 8px 8px;text-align:center}
    .cal-day{min-width:0;min-height:132px;border:1px solid var(--line);border-radius:16px;padding:9px;background:rgba(15,23,42,.03);display:flex;flex-direction:column;transition:border-color .18s ease,background .18s ease}
    .cal-day:hover{background:rgba(15,23,42,.05)}
    .cal-day.outside{opacity:.4;background:rgba(15,23,42,.015)}
    .cal-day.today{border-color:rgba(14,157,104,.6);background:rgba(14,157,104,.06);box-shadow:0 0 0 1px rgba(14,157,104,.15) inset}
    .cal-date{display:flex;align-items:center;justify-content:space-between;font-weight:900;font-size:13px;margin-bottom:7px}
    .cal-date .today-pill{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:#061017;background:linear-gradient(135deg,var(--brand),var(--brand2));border-radius:999px;padding:2px 7px}
    .cal-events{display:flex;flex-direction:column;gap:5px;min-width:0}
    .cal-event{display:block;min-width:0;border-radius:10px;padding:6px 8px;background:rgba(15,23,42,.05);border:1px solid var(--line);border-left:3px solid var(--muted2);font-size:11.5px;line-height:1.3;text-decoration:none;color:var(--text)}
    .cal-event strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .cal-event .meta{color:var(--muted);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .cal-event.public_holiday{background:rgba(184,121,10,.14);border-color:rgba(184,121,10,.3);border-left-color:var(--warn)}
    .cal-event.leave{background:rgba(14,157,104,.14);border-color:rgba(56,193,114,.22);border-left-color:var(--brand2)}
    .cal-event.employee_document,.cal-event.vehicle_document{background:rgba(47,111,237,.13);border-color:rgba(47,111,237,.26);border-left-color:var(--info)}
    .cal-event.vehicle_service{background:rgba(255,137,74,.14);border-color:rgba(255,137,74,.28);border-left-color:#ff894a}
    .cal-event.attendance{background:rgba(217,54,62,.12);border-color:rgba(217,54,62,.28);border-left-color:var(--danger)}
    .cal-event.tracking{background:rgba(152,112,255,.13);border-color:rgba(152,112,255,.28);border-left-color:#9870ff}
    .cal-more{font-size:11px;color:var(--muted);margin-top:auto;padding-top:4px}
    .cal-empty{font-size:11.5px;color:var(--muted2)}

    /* Reminder centre */
    .reminder-list{display:grid;gap:8px}
    .reminder-row{display:flex;justify-content:space-between;gap:12px;align-items:center;border:1px solid var(--line);border-radius:14px;padding:11px 12px;background:rgba(15,23,42,.03)}
    .reminder-icon{flex:0 0 36px;width:36px;height:36px;border-radius:12px;display:grid;place-items:center;background:rgba(15,23,42,.06);border:1px solid var(--line);font-size:16px}
    .reminder-main{display:flex;gap:11px;align-items:center;min-width:0}
    .reminder-text strong{display:block;font-size:13.5px}
    .reminder-text span{display:block}

    @media(max-width:1100px){.cal-filter-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:920px){
        .cal-grid{grid-template-columns:repeat(2,1fr)}
        .cal-weekday{display:none}
        .cal-toolbar{display:block}
        .cal-toolbar .actions{margin-top:12px}
        .cal-day{min-height:auto}
    }
    @media(max-width:620px){
        .cal-grid,.cal-filter-grid{grid-template-columns:1fr}
        .reminder-row{display:block}
        .reminder-row .actions{margin-top:8px}
        .cal-filter-actions{justify-content:flex-start}
        .cal-filter-actions .btn{width:100%}
    }
</style>

<div class="page-head cal-toolbar">
    <div class="page-head-main">
        <div class="page-head-icon">📅</div>
        <div>
            <h2>{{ $monthStart->format('F Y') }}</h2>
            <p>Central company calendar — leave, public holidays, attendance exceptions, document expiries and fleet reminders in one view.</p>
        </div>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('calendar.index', array_merge(['month' => $previousMonth], $activeFilterQuery)) }}">‹ Previous</a>
        <a class="btn" href="{{ route('calendar.index', array_merge(['month' => now()->format('Y-m')], $activeFilterQuery)) }}">Current</a>
        <a class="btn" href="{{ route('calendar.index', array_merge(['month' => $nextMonth], $activeFilterQuery)) }}">Next ›</a>
        @if(auth()->user()->hasPermission('leave.create'))<a class="btn primary" href="{{ route('leave.create') }}">+ Add Leave</a>@endif
    </div>
</div>

<div class="grid cols-4" style="margin-bottom:14px">
    <div class="card metric"><span>🌴 Leave Records</span><strong>{{ $summary['leave'] ?? 0 }}</strong></div>
    <div class="card metric"><span>📄 Document Reminders</span><strong>{{ ($summary['employee_document'] ?? 0) + ($summary['vehicle_document'] ?? 0) }}</strong></div>
    <div class="card metric"><span>⏱️ Attendance Exceptions</span><strong>{{ $summary['attendance'] ?? 0 }}</strong></div>
    <div class="card metric"><span>🔧 Fleet Reminders</span><strong>{{ ($summary['vehicle_service'] ?? 0) + ($summary['tracking'] ?? 0) }}</strong></div>
</div>

<div class="card" style="margin-bottom:14px">
    <form method="get" action="{{ route('calendar.index') }}">
        <input type="hidden" name="month" value="{{ $monthStart->format('Y-m') }}">
        <input type="hidden" name="filter_submitted" value="1">
        <div class="cal-filter-head">
            <div>
                <h3 style="margin:0 0 4px">Calendar Filters</h3>
                <p class="muted small" style="margin:0">Choose the reminder types you want to see, then apply.</p>
            </div>
            <div class="legend">
                <span>{{ $calendarEvents->count() }} dated items</span>
                <span>{{ $undatedReminders->count() }} operational reminders</span>
            </div>
        </div>
        <div class="cal-filter-grid">
            @foreach($availableTypes as $typeKey => $type)
                @php($isActive = in_array($typeKey, $selectedTypes ?? [], true))
                <label class="cal-chip {{ $isActive ? 'is-active' : '' }}">
                    <input type="checkbox" name="types[]" value="{{ $typeKey }}" @checked($isActive)>
                    <span class="chip-icon">{{ $type['icon'] }}</span>
                    <span>{{ $type['label'] }}</span>
                    <span class="chip-check">✓</span>
                </label>
            @endforeach
        </div>
        <div class="cal-filter-actions">
            <button class="btn primary" type="submit">Apply Filters</button>
            <a class="btn" href="{{ route('calendar.index', ['month' => $monthStart->format('Y-m')]) }}">Show All</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="cal-grid" style="margin-bottom:8px">
        <div class="cal-weekday">Sunday</div>
        <div class="cal-weekday">Monday</div>
        <div class="cal-weekday">Tuesday</div>
        <div class="cal-weekday">Wednesday</div>
        <div class="cal-weekday">Thursday</div>
        <div class="cal-weekday">Friday</div>
        <div class="cal-weekday">Saturday</div>
    </div>
    <div class="cal-grid">
        @foreach($days as $day)
            <div class="cal-day {{ $day['isCurrentMonth'] ? '' : 'outside' }} {{ $day['isToday'] ? 'today' : '' }}">
                <div class="cal-date">
                    <span>{{ $day['date']->format('D d') }}</span>
                    @if($day['isToday'])<span class="today-pill">Today</span>@endif
                </div>

                <div class="cal-events">
                    @forelse($day['events']->take(4) as $event)
                        @php($eventTag = ($event['type'] ?? '') . ' ' . ($event['status'] ?? ''))
                        @php($eventTitle = trim(($event['title'] ?? '') . ' — ' . ($event['subtitle'] ?? ''), ' —'))
                        @if(!empty($event['url']))
                            <a class="cal-event {{ $eventTag }}" href="{{ $event['url'] }}" title="{{ $eventTitle }}">
                                <strong>{{ $event['icon'] ?? '•' }} {{ $event['title'] }}</strong>
                                <span class="meta">{{ $event['subtitle'] ?? '' }}</span>
                            </a>
                        @else
                            <span class="cal-event {{ $eventTag }}" title="{{ $eventTitle }}">
                                <strong>{{ $event['icon'] ?? '•' }} {{ $event['title'] }}</strong>
                                <span class="meta">{{ $event['subtitle'] ?? '' }}</span>
                            </span>
                        @endif
                    @empty
                        <span class="cal-empty">No selected reminders</span>
                    @endforelse
                </div>

                @if($day['events']->count() > 4)
                    <div class="cal-more">+{{ $day['events']->count() - 4 }} more</div>
                @endif
            </div>
        @endforeach
    </div>
</div>

<div style="height:14px"></div>
<div class="card">
    <div class="actions" style="justify-content:space-between;margin-bottom:10px">
        <div><h2 style="margin:0 0 4px">Operational Reminder Centre</h2><p class="muted small" style="margin:0">ODO-based and sync-based reminders that don't sit on a single date. Calendar filters also apply here.</p></div>
    </div>
    <div class="reminder-list">
        @forelse($undatedReminders as $reminder)
            <div class="reminder-row">
                <div class="reminder-main">
                    <div class="reminder-icon">{{ $reminder['icon'] ?? '•' }}</div>
                    <div class="reminder-text">
                        <strong>{{ $reminder['title'] }}</strong>
                        <span class="muted small">{{ $reminder['subtitle'] ?? '' }}</span>
                    </div>
                </div>
                <div class="actions">
                    <span class="pill {{ $statusPillClass($reminder['status'] ?? null) }}">{{ ucfirst(str_replace('-', ' ', $reminder['status'] ?? 'info')) }}</span>
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
                    <tr><td>{{ $leave->user->name }}</td><td>{{ optional($leave->leaveType)->name ?? 'Leave' }}</td><td>{{ $leave->date_range_label }}</td><td><span class="pill {{ $leave->status === 'approved' ? '' : ($leave->status === 'pending' ? 'warn' : 'off') }}">{{ $leave->status_label }}</span></td></tr>
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
                    <tr><td>{{ $holiday->holiday_date->format('Y-m-d') }}</td><td>{{ $holiday->name }}</td><td><span class="pill warn">Company closed</span></td></tr>
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
