@extends('layouts.app')
@section('title','Cron Jobs | ISO Admin')
@section('page_title','Cron Jobs')
@section('content')
<div class="grid cols-2">
    <div class="card">
        <h2>Attendance Email Import</h2>
        <p class="muted">Imports CSV attachments from the attendance mailbox and deletes processed source emails if configured.</p>
        <p><strong>Status:</strong> <span class="pill {{ $attendanceRouteReady && $attendanceKeyConfigured ? '' : 'off' }}">{{ $attendanceRouteReady && $attendanceKeyConfigured ? 'Ready' : 'Needs setup' }}</span></p>
        @if($attendanceCronUrl)
            <label>Cron URL</label><input type="text" readonly value="{{ $attendanceCronUrl }}" onclick="this.select()">
        @else
            <p class="muted small">Set ATTENDANCE_IMPORT_KEY in .env to display the cron URL.</p>
        @endif
        @if(auth()->user()->hasPermission('cron_jobs.run'))
            <form method="post" action="{{ route('cron_jobs.attendance_email_import') }}" style="margin-top:12px">@csrf<button class="btn primary" type="submit">Run Email Import Now</button></form>
        @endif
    </div>
    <div class="card">
        <h2>Document Reminder Summary</h2>
        <p class="muted">Sends a summary of employee document reminders using the configured reminder key.</p>
        <p><strong>Status:</strong> <span class="pill {{ $documentReminderRouteReady && $documentReminderKeyConfigured ? '' : 'off' }}">{{ $documentReminderRouteReady && $documentReminderKeyConfigured ? 'Ready' : 'Needs setup' }}</span></p>
        @if($documentCronUrl)
            <label>Cron URL</label><input type="text" readonly value="{{ $documentCronUrl }}" onclick="this.select()">
        @else
            <p class="muted small">Set DOCUMENT_REMINDER_KEY in .env to display the cron URL.</p>
        @endif
    </div>
</div>
<div style="height:14px"></div>
<div class="card">
    <h2>Shared Hosting Cron Notes</h2>
    <p class="muted">Use DirectAdmin cron URL calls for these routes. The attendance import can also be run manually from this page by users with the <strong>cron_jobs.run</strong> permission, which fixes access issues where the old email import control was hidden by attendance permissions.</p>
</div>
@endsection
