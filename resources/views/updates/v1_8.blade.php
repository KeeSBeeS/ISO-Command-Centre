@extends('layouts.app')
@section('title','Version 1.8 Update | ISO Admin')
@section('page_title','Version 1.8 Update')
@section('content')
<div class="card">
    <h2>Vehicle Analytics & PDF Reports</h2>
    <p class="muted">This update adds enhanced vehicle stats, graph panels, and vehicle PDF report exports.</p>
    <div class="grid cols-2">
        <div class="card metric"><span>Export Permission</span><strong>{{ $permissionExists ? 'Ready' : 'Missing' }}</strong></div>
        <div class="card metric"><span>PDF Engine</span><strong>Built-in</strong></div>
    </div>
    <form method="post" action="{{ route('updates.v1_8.apply') }}" style="margin-top:16px">
        @csrf
        <button class="btn primary" type="submit">Apply Version 1.8 Update</button>
    </form>
</div>
<div style="height:14px"></div>
<div class="card">
    <h2>What v1.8 Adds</h2>
    <ul class="muted">
        <li>Vehicle profile analytics for this month, last month, last 3 months and lifetime.</li>
        <li>Fuel spend, litres, distance, KM/L, cost per KM and average price/litre metrics.</li>
        <li>Mobile-friendly SVG graphs for monthly fuel cost, efficiency trend and odometer trend.</li>
        <li>Download vehicle report as PDF.</li>
        <li>Email vehicle PDF report directly from ISO Admin.</li>
        <li>New permission: <code>vehicle.reports.export</code>.</li>
    </ul>
</div>
@endsection
