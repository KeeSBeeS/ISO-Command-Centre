@extends('layouts.app')
@section('title','Update v2.6.3 | ISO Admin')
@section('page_title','Update v2.6.3')
@section('content')
<div class="card">
    <div class="page-head">
        <div class="page-head-main">
            <div class="page-head-icon">🛠️</div>
            <div>
                <h2>Version 2.6.3 Public Holiday Repair</h2>
                <p>Repairs older public holiday table structures, reseeds South African holidays and rebuilds attendance-day late/public-holiday flags.</p>
            </div>
        </div>
    </div>

    <div class="grid cols-4">
        <div class="card metric"><span>Public Holidays Table</span><strong style="font-size:18px">{{ $publicHolidaysInstalled ? 'Found' : 'Create' }}</strong></div>
        <div class="card metric"><span>Name Column</span><strong style="font-size:18px">{{ $publicHolidayNameColumn ? 'Ready' : 'Repair' }}</strong></div>
        <div class="card metric"><span>Seeded Holidays</span><strong>{{ $publicHolidayCount }}</strong></div>
        <div class="card metric"><span>Attendance Columns</span><strong style="font-size:18px">{{ $attendanceColumnsInstalled ? 'Ready' : 'Repair' }}</strong></div>
    </div>

    <div class="card" style="margin-top:16px">
        <h3>Repair Applied</h3>
        <ul class="muted">
            <li>Adds missing public holiday columns on older installations.</li>
            <li>Fixes the missing <code>name</code> column error from v2.6.2.</li>
            <li>Reseeds South African public holidays.</li>
            <li>Rebuilds attendance records so public holidays are excluded from late tracking.</li>
            <li>Re-syncs System Administrator permissions.</li>
        </ul>
    </div>

    <form method="post" action="{{ route('updates.v2_6_3.apply') }}" style="margin-top:18px">
        @csrf
        <button class="btn primary" type="submit">Run v2.6.3 Repair</button>
    </form>
</div>
@endsection
