@extends('layouts.app')
@section('title','Update v1.5 | ISO Admin')
@section('page_title','Version 1.5 Update')
@section('content')
<div class="card">
    <h2>Dashboard Customisation + Fuel ODO Calculation</h2>
    <p class="muted">This update adds per-user dashboard widget preferences and changes manual fuel-ups so KM travelled is calculated from odometer readings.</p>
    <ul class="muted">
        <li>Manual fuel-ups now use latest ODO only.</li>
        <li>KM travelled is calculated from the previous lower odometer reading.</li>
        <li>KM/L is calculated from calculated KM and litres.</li>
        <li>Every user can edit their own dashboard widgets.</li>
        <li>Widgets can be reordered and resized to small, medium or large.</li>
    </ul>
</div>
<div style="height:14px"></div>
<div class="grid cols-2">
    <div class="card metric"><span>Dashboard Preferences Table</span><strong style="font-size:22px">{{ $dashboardPreferencesInstalled ? 'Installed' : 'Missing' }}</strong></div>
    <div class="card metric"><span>Dashboard Customise Permission</span><strong style="font-size:22px">{{ $permissionExists ? 'Installed' : 'Missing' }}</strong></div>
</div>
<div style="height:14px"></div>
@if(session('success'))<div class="alert">{{ session('success') }}</div><div style="height:14px"></div>@endif
<form method="post" action="{{ route('updates.v1_5.apply') }}">
    @csrf
    <button class="btn primary" type="submit">Run v1.5 Update</button>
    <a class="btn" href="{{ route('dashboard') }}">Back to Dashboard</a>
</form>
@endsection
