@extends('layouts.app')
@section('title','Update v2.6.9 | ISO Admin')
@section('page_title','Version 2.6.9 Update')
@section('content')
<div class="card">
    <h2>Vehicle Dashboard, Fuel Tracking & Quick Actions</h2>
    <p class="muted">This update upgrades the vehicle page into a fleet dashboard, adds a vehicle-selection flow for fuel capture, and makes homepage Quick Actions editable for permitted users.</p>
    <div class="metric-row" style="margin-top:14px">
        <div class="metric-box"><span>Current Version</span><strong style="font-size:18px">{{ $systemVersion ? 'v'.$systemVersion : 'Unknown' }}</strong></div>
        <div class="metric-box"><span>Quick Action Preferences</span><strong style="font-size:18px">{{ $quickActionsReady ? 'Installed' : 'Pending' }}</strong></div>
        <div class="metric-box"><span>Target Version</span><strong style="font-size:18px">v2.6.9</strong></div>
    </div>
    <form method="post" action="{{ route('updates.v2_6_9.apply') }}" style="margin-top:18px">
        @csrf
        <button class="btn primary" type="submit">Apply v2.6.9 Update</button>
    </form>
</div>

<div style="height:14px"></div>
<div class="card">
    <h2>Included</h2>
    <ul class="muted">
        <li>/vehicles becomes a dashboard with fleet fuel tracking metrics.</li>
        <li>Monthly fuel-ups, litres, cost, average KM/L, top vehicles by spend and recent fuel-ups.</li>
        <li>Add Fuel now opens a vehicle selector first.</li>
        <li>Homepage Quick Actions can be shown/hidden and reordered by users with <code>dashboard.quick_actions.manage</code>.</li>
        <li>System Administrator permissions are re-synced.</li>
    </ul>
</div>
@endsection
