@extends('layouts.app')
@section('title','Update v2.6.11 | ISO Admin')
@section('page_title','Version 2.6.11 Update')
@section('content')
<div class="card">
    <h2>Attendance Import Recovery</h2>
    <p class="muted">Restores the morning attendance CSV workflow and supports both attendance export formats: the old event-log file with Name + Time, and the daily summary file with Person ID, Name, Date, Check-In and Check-out.</p>
    <div class="grid cols-3" style="margin-top:16px">
        <div class="metric-box"><span>Current Version</span><strong style="font-size:18px">{{ $systemVersion ?? 'Unknown' }}</strong></div>
        <div class="metric-box"><span>Attendance Tables</span><strong style="font-size:18px">{{ $attendanceInstalled ? 'Ready' : 'Pending' }}</strong></div>
        <div class="metric-box"><span>Latest Attendance Date</span><strong style="font-size:18px">{{ $latestAttendanceDate ?? 'None' }}</strong></div>
        <div class="metric-box"><span>Daily Records</span><strong style="font-size:18px">{{ $attendanceDayCount }}</strong></div>
    </div>
    <form method="post" action="{{ route('updates.v2_6_11.apply') }}" style="margin-top:18px">
        @csrf
        <button class="btn primary" type="submit">Apply v2.6.11 Update</button>
    </form>
</div>
@endsection
