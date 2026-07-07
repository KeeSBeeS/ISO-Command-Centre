@extends('layouts.app')
@section('title','Vehicle Tracking API Settings | ISO Admin')
@section('page_title','Vehicle Tracking API Settings')
@section('content')
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">🛰️</div>
        <div>
            <h2>Cartrack Fleet API Integration</h2>
            <p>System Administrator-only API settings for vehicle tracking sync.</p>
        </div>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('vehicle_tracking.index') }}">View Tracking</a>
    </div>
</div>

<div class="alert warning">
    Store Cartrack credentials here only. Do not place the API username or password inside JavaScript, public HTML or client-side code.
</div>

<form method="post" action="{{ route('vehicle_tracking.settings.update') }}">
    @csrf
    @method('PUT')
    <div class="grid cols-2">
        <div class="card">
            <h2>Connection</h2>
            <label class="check">
                <input type="checkbox" name="cartrack_enabled" value="1" @checked(old('cartrack_enabled', optional($settings->get('cartrack_enabled'))->value) == '1')>
                <span>Enable Cartrack Integration</span>
            </label>
            <div class="form-row">
                <label>Region Code</label>
                <input type="text" name="cartrack_region" value="{{ old('cartrack_region', optional($settings->get('cartrack_region'))->value ?? 'za') }}" required>
                <p class="muted small">South Africa normally uses <strong>za</strong>.</p>
            </div>
            <div class="form-row">
                <label>API Base URL</label>
                <input type="url" name="cartrack_base_url" value="{{ old('cartrack_base_url', optional($settings->get('cartrack_base_url'))->value ?? 'https://fleetapi-za.cartrack.com') }}" required>
                <p class="muted small">Do not add <code>/rest/vehicles</code>. Example: https://fleetapi-za.cartrack.com</p>
            </div>
            <div class="form-row">
                <label>API Username</label>
                <input type="text" name="cartrack_username" value="{{ old('cartrack_username', optional($settings->get('cartrack_username'))->value) }}" autocomplete="off">
            </div>
            <div class="form-row">
                <label>API Password</label>
                <input type="password" name="cartrack_password" value="{{ old('cartrack_password', optional($settings->get('cartrack_password'))->value) }}" autocomplete="new-password">
                <p class="muted small">Use the generated API password from Fleetweb API Settings.</p>
            </div>
            <div class="form-row">
                <label>Timeout Seconds</label>
                <input type="number" name="cartrack_timeout_seconds" min="5" max="120" value="{{ old('cartrack_timeout_seconds', optional($settings->get('cartrack_timeout_seconds'))->value ?? 20) }}" required>
            </div>
        </div>

        <div class="card">
            <h2>Sync Behaviour</h2>
            <label class="check"><input type="checkbox" name="cartrack_sync_odometer" value="1" @checked(old('cartrack_sync_odometer', optional($settings->get('cartrack_sync_odometer'))->value) == '1')><span>Update vehicle ODO from tracking when Cartrack reports a higher odometer reading.</span></label>
            <label class="check"><input type="checkbox" name="cartrack_sync_location" value="1" @checked(old('cartrack_sync_location', optional($settings->get('cartrack_sync_location'))->value) == '1')><span>Store latest location, speed, address and ignition state where available.</span></label>
            <label class="check"><input type="checkbox" name="cartrack_sync_status" value="1" @checked(old('cartrack_sync_status', optional($settings->get('cartrack_sync_status'))->value) == '1')><span>Store latest vehicle status where available.</span></label>

            <div class="form-row">
                <label>Vehicle Tracking Cron Key</label>
                <input type="text" name="cartrack_cron_key" value="{{ old('cartrack_cron_key', optional($settings->get('cartrack_cron_key'))->value) }}" required>
                <p class="muted small">Use a long random value. This protects the public shared-hosting cron endpoint.</p>
            </div>

            <div class="soft-divider"></div>
            <h3>Cron URL</h3>
            <p class="muted small">Add this URL in DirectAdmin cron to sync tracking without console access.</p>
            <input type="text" readonly value="{{ $cronUrl }}" onclick="this.select()">
        </div>
    </div>

    <div style="height:16px"></div>
    <div class="actions right">
        <button class="btn primary" type="submit">Save Cartrack Settings</button>
    </div>
</form>

<div style="height:14px"></div>
<div class="card">
    <h2>Connection Test</h2>
    <p class="muted">The test now checks <code>/rest/vehicles/status</code> first for live vehicle status/location/odometer data and also checks <code>/rest/vehicles</code> for the vehicle list.</p>
    @if(optional($settings->get('cartrack_last_sync_at'))->value || optional($settings->get('cartrack_last_sync_message'))->value)
        <div class="alert" style="margin-bottom:12px">
            <strong>Last sync:</strong> {{ optional($settings->get('cartrack_last_sync_at'))->value ?? 'Not recorded' }}<br>
            <span class="muted">{{ optional($settings->get('cartrack_last_sync_message'))->value ?? 'No diagnostic message recorded.' }}</span>
        </div>
    @endif
    <div class="actions">
        <form method="post" action="{{ route('vehicle_tracking.settings.test') }}">@csrf<button class="btn" type="submit">Test Connection</button></form>
        <form method="post" action="{{ route('vehicle_tracking.sync') }}">@csrf<button class="btn primary" type="submit">Run Full Sync</button></form>
    </div>
</div>
@endsection
