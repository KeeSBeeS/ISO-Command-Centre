@extends('layouts.app')
@section('title','Update v2.5.3 | ISO Admin')
@section('page_title','Update v2.5.3')
@section('content')
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">🔧</div>
        <div>
            <h2>Version 2.5.3 - Core Settings</h2>
            <p>Adds protected Core Settings under System Settings for System Administrators only.</p>
        </div>
    </div>
</div>

<div class="grid cols-2">
    <div class="card">
        <h2>Install Status</h2>
        <p><span class="pill {{ $coreSettingsInstalled ? '' : 'off' }}">{{ $coreSettingsInstalled ? 'Core settings table installed' : 'Core settings table missing' }}</span></p>
        <p><span class="pill {{ $permissionCount === 2 ? '' : 'off' }}">{{ $permissionCount }}/2 permissions ready</span></p>
        <p><span class="pill {{ $systemAdministratorHasPermissions ? '' : 'off' }}">{{ $systemAdministratorHasPermissions ? 'System Administrator has access' : 'System Administrator access pending' }}</span></p>
    </div>
    <div class="card">
        <h2>What This Adds</h2>
        <ul class="muted">
            <li>System Settings → Core Settings menu item</li>
            <li>System Administrator-only access enforcement</li>
            <li>Core platform identity settings</li>
            <li>Notification, attendance, document and vehicle reminder defaults</li>
            <li>Updated permission matrix entries</li>
        </ul>
    </div>
</div>

<div style="height:16px"></div>
<div class="card">
    <h2>Apply Update</h2>
    <p class="muted">This update is safe to run more than once. It does not remove existing functionality.</p>
    <form method="post" action="{{ route('updates.v2_5_3.apply') }}">
        @csrf
        <button class="btn primary" type="submit">Run v2.5.3 Update</button>
    </form>
</div>
@endsection
