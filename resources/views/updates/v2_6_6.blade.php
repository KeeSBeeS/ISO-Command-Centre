@extends('layouts.app')
@section('title','Update v2.6.6 | ISO Admin')
@section('page_title','Update v2.6.6')
@section('content')
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">🗺️</div>
        <div>
            <h2>Version 2.6.6 Update</h2>
            <p>Google API restoration and vehicle tracking maps.</p>
        </div>
    </div>
</div>

<div class="grid cols-2">
    <div class="card">
        <h2>Google API Settings</h2>
        <p class="muted">Restores Google API settings under System Settings for System Administrator only.</p>
        <ul class="muted">
            <li>Google Maps API key</li>
            <li>Optional Google Map ID</li>
            <li>Default map centre and zoom</li>
            <li>System Administrator-only permission</li>
        </ul>
        <p class="muted small">Installed: {{ $googleApiSettingsInstalled ? 'Yes' : 'No' }}</p>
    </div>
    <div class="card">
        <h2>Vehicle Maps</h2>
        <p class="muted">Adds map views using the LAT/LONG captured from the tracking API sync.</p>
        <ul class="muted">
            <li>Main vehicle fleet map</li>
            <li>Vehicle-specific latest position map</li>
            <li>Route history polyline from stored tracking snapshots</li>
            <li>Fallback marker support if no Google Map ID is configured</li>
        </ul>
    </div>
</div>

<div style="height:14px"></div>
<div class="card">
    <h2>Apply Update</h2>
    <p class="muted">Current recorded version: {{ $systemVersion ?? 'unknown' }}</p>
    <form method="post" action="{{ route('updates.v2_6_6.apply') }}">
        @csrf
        <button class="btn primary" type="submit">Apply Version 2.6.6</button>
    </form>
</div>
@endsection
