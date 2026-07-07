@extends('layouts.app')
@section('title','Update v2.5 | ISO Admin')
@section('page_title','Update v2.5')
@section('content')
<div class="card">
    <h2>ISO Admin Command Framework v2.5</h2>
    <p class="muted">This update adds user profiles, System Administrator access, direct user permissions, leave-type settings, cron-job access fixes and extra vehicle profile fields.</p>
    <div class="grid cols-2">
        <p><strong>Direct user permission table:</strong> {{ $permissionUserInstalled ? 'Installed' : 'Pending' }}</p>
        <p><strong>Leave types:</strong> {{ $leaveTypesInstalled ? 'Installed' : 'Pending' }}</p>
        <p><strong>Vehicle fields:</strong> {{ $vehicleColumnsInstalled ? 'Installed' : 'Pending' }}</p>
        <p><strong>System Administrator role:</strong> {{ $systemAdministratorReady ? 'Ready' : 'Pending' }}</p>
        <p><strong>v2.5 permissions:</strong> {{ $permissionCount }}/5</p>
    </div>
    <form method="post" action="{{ route('updates.v2_5.apply') }}" style="margin-top:16px">
        @csrf
        <button class="btn primary" type="submit">Apply v2.5 Update</button>
    </form>
</div>
@endsection
