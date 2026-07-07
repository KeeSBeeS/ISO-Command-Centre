@extends('layouts.app')
@section('title','Update v1.4 | ISO Admin')
@section('page_title','Version 1.4 Update')
@section('content')
<div class="card">
    <h2>Vehicle & Fuel Tracking Update</h2>
    <p class="muted">This updater adds vehicles, employee/director vehicle assignments, fuel-up tracking, CSV fuel import, NATIS/license documents and vehicle document expiry reminders.</p>
    <div style="margin:14px 0">
        <span class="pill {{ $vehiclesInstalled ? '' : 'off' }}">Vehicle Tables: {{ $vehiclesInstalled ? 'Installed' : 'Not Installed' }}</span>
        <span class="pill {{ $permissionCount >= 11 ? '' : 'off' }}">Permissions Found: {{ $permissionCount }}/11</span>
    </div>
    <form method="post" action="{{ route('updates.v1_4.apply') }}">
        @csrf
        <button class="btn primary" type="submit">Apply v1.4 Update</button>
        <a class="btn" href="{{ route('dashboard') }}">Back to Dashboard</a>
    </form>
</div>
@endsection
