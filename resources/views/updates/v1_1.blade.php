@extends('layouts.app')
@section('title','Version 1.1 Update | ISO Admin')
@section('page_title','Version 1.1 Update')
@section('content')
<div class="grid cols-2">
    <div class="card">
        <h2>Time Attendance Update</h2>
        <p class="muted">This update adds attendance imports, daily attendance summaries and email-based CSV collection for cc@isoadmin.co.za.</p>
        <p><strong>Status:</strong> @if($attendanceInstalled)<span class="pill">Installed</span>@else<span class="pill off">Not installed</span>@endif</p>
        <form method="post" action="{{ route('updates.v1_1.apply') }}">
            @csrf
            <button class="btn primary" type="submit">Apply / Repair Version 1.1</button>
        </form>
    </div>
    <div class="card">
        <h2>After Update</h2>
        <p class="muted">Add these values to your .env file for email import:</p>
        <pre style="white-space:pre-wrap;background:rgba(0,0,0,.22);padding:12px;border-radius:14px;border:1px solid var(--line);overflow:auto"><code>ATTENDANCE_MAIL_HOST=mail.isoadmin.co.za
ATTENDANCE_MAIL_PORT=993
ATTENDANCE_MAIL_ENCRYPTION=ssl
ATTENDANCE_MAIL_USERNAME=cc@isoadmin.co.za
ATTENDANCE_MAIL_PASSWORD=your-mailbox-password
ATTENDANCE_MAILBOX=INBOX
ATTENDANCE_IMPORT_KEY=change-this-to-a-long-random-key
ATTENDANCE_DELETE_PROCESSED=true</code></pre>
    </div>
</div>
@endsection
