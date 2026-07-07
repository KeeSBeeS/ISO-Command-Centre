@extends('layouts.app')
@section('title','Update v2.6.4 | ISO Admin')
@section('page_title','Update v2.6.4')
@section('content')
<div class="card">
    <div class="page-head">
        <div class="page-head-main">
            <div class="page-head-icon">🗓️</div>
            <div>
                <h2>Version 2.6.4 Calendar Reminder Centre</h2>
                <p>Updates the calendar to start on Sunday and turns it into a central reminder view, not only a leave calendar.</p>
            </div>
        </div>
    </div>

    <div class="grid cols-3">
        <div class="card metric"><span>Week Start</span><strong style="font-size:18px">Sunday</strong></div>
        <div class="card metric"><span>Reminder Permission</span><strong style="font-size:18px">{{ $calendarReminderPermission ? 'Ready' : 'Add' }}</strong></div>
        <div class="card metric"><span>Current Version</span><strong style="font-size:18px">{{ $systemVersion ?: 'Unknown' }}</strong></div>
    </div>

    <div class="card" style="margin-top:16px">
        <h3>Calendar Scope</h3>
        <ul class="muted">
            <li>Calendar grid now starts on Sunday.</li>
            <li>Leave remains visible on the calendar.</li>
            <li>Public holidays remain marked and company-closed days remain excluded from attendance late tracking.</li>
            <li>Employee document expiry reminders are added when the user has document permissions.</li>
            <li>Vehicle document expiry reminders are added when the user has vehicle document permissions.</li>
            <li>Vehicle service reminders and Cartrack sync reminders are shown as operational reminders.</li>
            <li>Attendance exceptions, late clock-ins and single-punch days are shown when the user has attendance permissions.</li>
            <li>System Administrator permissions are re-synced to all permissions.</li>
        </ul>
    </div>

    <form method="post" action="{{ route('updates.v2_6_4.apply') }}" style="margin-top:18px">
        @csrf
        <button class="btn primary" type="submit">Run v2.6.4 Update</button>
    </form>
</div>
@endsection
