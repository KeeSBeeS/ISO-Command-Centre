@extends('layouts.app')
@section('title','Update v2.6.5 | ISO Admin')
@section('page_title','Update v2.6.5')
@section('content')
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">🗓️</div>
        <div>
            <h2>Version 2.6.5 Update</h2>
            <p>Calendar filters and Cartrack API sync hardening.</p>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert success">{{ session('success') }}</div>
@endif

<div class="grid cols-2">
    <div class="card">
        <h2>Calendar Filtering</h2>
        <p class="muted">Adds selectable calendar item types so users can show only the reminders they need.</p>
        <ul class="muted">
            <li>Public holidays</li>
            <li>Leave</li>
            <li>Employee document reminders</li>
            <li>Vehicle document reminders</li>
            <li>Attendance exceptions</li>
            <li>Vehicle services</li>
            <li>Tracking sync reminders</li>
        </ul>
    </div>
    <div class="card">
        <h2>Cartrack Sync Fix</h2>
        <p class="muted">Updates the API sync to pull live vehicle tracking/status records from <code>/rest/vehicles/status</code> first, then merge/fallback to <code>/rest/vehicles</code>.</p>
        <ul class="muted">
            <li>Better response parser for nested API payloads</li>
            <li>Improved matching by Cartrack ID, registration and device number</li>
            <li>Unmatched remote vehicle diagnostics</li>
            <li>Last sync message in Vehicle Tracking Settings</li>
        </ul>
    </div>
</div>

<div style="height:14px"></div>
<div class="card">
    <h2>Apply Update</h2>
    <p class="muted">Current recorded version: {{ $systemVersion ?? 'unknown' }}</p>
    <form method="post" action="{{ route('updates.v2_6_5.apply') }}">
        @csrf
        <button class="btn primary" type="submit">Apply Version 2.6.5</button>
    </form>
</div>
@endsection
