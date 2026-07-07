@extends('layouts.app')
@section('title','Update v2.6.2 | ISO Admin')
@section('page_title','Update v2.6.2')
@section('content')
<div class="card">
    <div class="page-head">
        <div class="page-head-main">
            <div class="page-head-icon">⏱️</div>
            <div>
                <h2>Version 2.6.2 Attendance Rules & Public Holidays</h2>
                <p>Applies the 09:00 clock-in rule, late clock-in tracking, public holiday calendar markers, and company-closed attendance handling.</p>
            </div>
        </div>
    </div>
    <div class="grid cols-4">
        <div class="card metric"><span>Attendance Columns</span><strong style="font-size:18px">{{ $attendanceColumnsInstalled ? 'Ready' : 'Install' }}</strong></div>
        <div class="card metric"><span>Public Holidays Table</span><strong style="font-size:18px">{{ $publicHolidaysInstalled ? 'Ready' : 'Install' }}</strong></div>
        <div class="card metric"><span>Seeded Holidays</span><strong>{{ $publicHolidayCount }}</strong></div>
        <div class="card metric"><span>Late Records</span><strong>{{ $lateRecordCount }}</strong></div>
    </div>
    <div class="card" style="margin-top:16px">
        <h3>Rules Applied</h3>
        <ul class="muted">
            <li>Clock-in is accepted until 09:00.</li>
            <li>If multiple records exist before 09:00, only the earliest is used as check-in.</li>
            <li>If no record exists before 09:00, the earliest available record is treated as late clock-in.</li>
            <li>Latest different timestamp is used as checkout; same clock-in/checkout is flagged and checkout stays blank.</li>
            <li>South African public holidays are marked on the calendar and excluded from late attendance tracking because the company is closed.</li>
        </ul>
    </div>
    <form method="post" action="{{ route('updates.v2_6_2.apply') }}" style="margin-top:18px">
        @csrf
        <button class="btn primary" type="submit">Run v2.6.2 Update</button>
    </form>
</div>
@endsection
