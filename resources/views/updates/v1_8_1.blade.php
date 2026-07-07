@extends('layouts.app')
@section('title','Version 1.8.1 Repair | ISO Admin')
@section('page_title','Version 1.8.1 Repair')
@section('content')
<div class="card">
    <h2>Vehicle Analytics Display Repair</h2>
    <p class="muted">This repair fixes the v1.8 vehicle profile layout so the stats, graphs and PDF export area display directly on the vehicle page instead of being tied to the current assignment block. It also clears Laravel cached routes/views where the host allows file deletion.</p>
    <div class="grid cols-3">
        <div class="card metric"><span>Export Permission</span><strong>{{ $permissionExists ? 'Ready' : 'Missing' }}</strong></div>
        <div class="card metric"><span>Director Permission</span><strong>{{ $directorHasPermission ? 'Ready' : 'Needs Apply' }}</strong></div>
        <div class="card metric"><span>Manager Permission</span><strong>{{ $managerHasPermission ? 'Ready' : 'Needs Apply' }}</strong></div>
    </div>
    <form method="post" action="{{ route('updates.v1_8_1.apply') }}" style="margin-top:16px">
        @csrf
        <button class="btn primary" type="submit">Apply Version 1.8.1 Repair</button>
    </form>
</div>
<div style="height:14px"></div>
<div class="card">
    <h2>What This Repair Does</h2>
    <ul class="muted">
        <li>Moves Vehicle Stats & Graphs outside the assignment panel so it appears on every vehicle profile.</li>
        <li>Keeps the period cards: this month, last month, last 3 months and lifetime.</li>
        <li>Keeps fuel cost, KM/L and ODO graphs.</li>
        <li>Keeps Download PDF and Email PDF report options.</li>
        <li>Re-applies <code>vehicle.reports.export</code> to directors and managers.</li>
        <li>Clears application cache, route cache and compiled Blade views where the shared host allows it.</li>
    </ul>
</div>
@endsection
