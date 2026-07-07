@extends('layouts.app')
@section('title','Update v2.6 | ISO Admin')
@section('page_title','Update v2.6')
@section('content')
<div class="card">
    <h2>Version 2.6 - Cartrack Vehicle Tracking API Integration</h2>
    <p class="muted">This update adds API settings, Cartrack vehicle sync, local vehicle linking, tracking snapshots and vehicle tracking permissions.</p>

    <div class="grid cols-2">
        <div class="kv"><span>Tracking table</span><strong>{{ $vehicleTrackingInstalled ? 'Installed' : 'Missing' }}</strong></div>
        <div class="kv"><span>Vehicle tracking columns</span><strong>{{ $vehicleColumnsInstalled ? 'Installed' : 'Missing' }}</strong></div>
        <div class="kv"><span>Tracking permissions</span><strong>{{ $permissionCount }}/5</strong></div>
        <div class="kv"><span>Shared hosting</span><strong>No console required</strong></div>
    </div>

    <div class="soft-divider"></div>

    <form method="post" action="{{ route('updates.v2_6.apply') }}">
        @csrf
        <button class="btn primary" type="submit">Run v2.6 Update</button>
    </form>
</div>
@endsection
