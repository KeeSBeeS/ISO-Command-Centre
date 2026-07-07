@extends('layouts.app')
@section('title','Update v2.0 | ISO Admin')
@section('page_title','Version 2.0 Update')
@section('content')
<div class="card">
    <h2>ISO Admin Command Framework v{{ $version }}</h2>
    <p class="muted">Major update: calendar backbone, South African public holidays, attendance work policy, core settings, encrypted attachments, sick-status workflow and footer versioning.</p>
    <div class="grid cols-2">
        <div><span class="pill {{ $calendarInstalled ? '' : 'off' }}">Calendar {{ $calendarInstalled ? 'Installed' : 'Pending' }}</span></div>
        <div><span class="pill {{ $settingsInstalled ? '' : 'off' }}">Settings {{ $settingsInstalled ? 'Installed' : 'Pending' }}</span></div>
        <div><span class="pill {{ $sickInstalled ? '' : 'off' }}">Sick Workflow {{ $sickInstalled ? 'Installed' : 'Pending' }}</span></div>
        <div><span class="pill {{ $attendancePolicyReady ? '' : 'off' }}">Attendance Policy {{ $attendancePolicyReady ? 'Ready' : 'Pending' }}</span></div>
        <div><span class="pill {{ $encryptedDocsReady ? '' : 'off' }}">Encrypted Attachments {{ $encryptedDocsReady ? 'Ready' : 'Pending' }}</span></div>
    </div>
    <form method="post" action="{{ route('updates.v2_0.apply') }}" style="margin-top:18px">
        @csrf
        <button class="btn primary" type="submit">Apply Version 2.0</button>
    </form>
</div>
@endsection
