@extends('layouts.app')
@section('title','Google API Settings | ISO Admin')
@section('page_title','Google API Settings')
@section('content')
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">🗺️</div>
        <div>
            <h2>Google API Settings</h2>
            <p>System Administrator-only Google Maps settings for vehicle tracking maps and route history.</p>
        </div>
    </div>
</div>

<div class="alert warning">
    Use a restricted Google Maps browser API key. Restrict it by HTTP referrer to <strong>isoadmin.co.za</strong> and enable the Maps JavaScript API.
</div>

<form method="post" action="{{ route('google_api_settings.update') }}">
    @csrf
    @method('PUT')

    <div class="grid cols-2">
        <div class="card">
            <h2>Maps JavaScript API</h2>
            <label class="check">
                <input type="checkbox" name="google_maps_enabled" value="1" @checked(old('google_maps_enabled', optional($settings->get('google_maps_enabled'))->value) == '1')>
                <span>Enable Google Maps in ISO Admin</span>
            </label>
            <div class="form-row">
                <label>Google Maps API Key</label>
                <input type="password" name="google_maps_api_key" value="{{ old('google_maps_api_key', optional($settings->get('google_maps_api_key'))->value) }}" autocomplete="new-password">
                <p class="muted small">Used on authenticated vehicle map pages. Required for fleet map and route map rendering.</p>
            </div>
            <div class="form-row">
                <label>Google Map ID</label>
                <input type="text" name="google_maps_map_id" value="{{ old('google_maps_map_id', optional($settings->get('google_maps_map_id'))->value) }}">
                <p class="muted small">Optional. If supplied, Advanced Markers are used. If blank, the map falls back to the standard marker layer.</p>
            </div>
        </div>

        <div class="card">
            <h2>Default Map View</h2>
            <div class="form-row">
                <label>Default Latitude</label>
                <input type="number" step="0.000001" name="google_maps_default_latitude" value="{{ old('google_maps_default_latitude', optional($settings->get('google_maps_default_latitude'))->value ?? '-26.204103') }}" required>
            </div>
            <div class="form-row">
                <label>Default Longitude</label>
                <input type="number" step="0.000001" name="google_maps_default_longitude" value="{{ old('google_maps_default_longitude', optional($settings->get('google_maps_default_longitude'))->value ?? '28.047305') }}" required>
            </div>
            <div class="form-row">
                <label>Default Zoom</label>
                <input type="number" min="1" max="20" name="google_maps_default_zoom" value="{{ old('google_maps_default_zoom', optional($settings->get('google_maps_default_zoom'))->value ?? '7') }}" required>
            </div>
        </div>
    </div>

    <div style="height:16px"></div>
    <div class="actions right">
        <button class="btn primary" type="submit">Save Google API Settings</button>
    </div>
</form>
@endsection
