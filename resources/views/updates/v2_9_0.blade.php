@extends('layouts.app')
@section('title','Update v2.9.0 | ISO Admin')
@section('page_title','Version 2.9.0 Update')
@section('content')
@if(session('success'))
    <div class="alert success">{{ session('success') }}</div>
@endif
<div class="card">
    <h2>Attendance Platform Redesign, Leave Allocations &amp; Sick Leave Cycles</h2>
    <p class="muted">
        Applies the attendance overhaul: the earliest punch of a day is the check-in and the latest punch is the checkout,
        late arrivals are measured against the 06:00 office start, early departures against the 15:00 office close,
        and weekends and public holidays are treated as non-working days.
        Installs the director-managed annual leave allocations (1 January – 31 December leave year) and the
        36-month sick leave cycle tracker (6 weeks of paid sick leave per cycle).
        All existing attendance days are rebuilt with the new rules.
    </p>
    <div class="grid cols-3" style="margin-top:16px">
        <div class="metric-box"><span>Current Version</span><strong style="font-size:18px">{{ $systemVersion ?? 'Unknown' }}</strong></div>
        <div class="metric-box"><span>Attendance Tables</span><strong style="font-size:18px">{{ $attendanceInstalled ? 'Ready' : 'Pending' }}</strong></div>
        <div class="metric-box"><span>New Attendance Columns</span><strong style="font-size:18px">{{ $attendanceColumnsInstalled ? 'Installed' : 'Pending' }}</strong></div>
        <div class="metric-box"><span>Leave Allocations Table</span><strong style="font-size:18px">{{ $allocationsInstalled ? 'Installed' : 'Pending' }}</strong></div>
        <div class="metric-box"><span>Sick Leave Register Table</span><strong style="font-size:18px">{{ $sickRecordsInstalled ? 'Installed' : 'Pending' }}</strong></div>
        <div class="metric-box"><span>Daily Records To Rebuild</span><strong style="font-size:18px">{{ $attendanceDayCount }}</strong></div>
    </div>
    <form method="post" action="{{ route('updates.v2_9_0.apply') }}" style="margin-top:18px">
        @csrf
        <button class="btn primary" type="submit">Apply v2.9.0 Update</button>
    </form>
</div>
@endsection
