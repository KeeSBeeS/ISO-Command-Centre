@extends('layouts.app')
@section('title','Version 2.2.1 Repair | ISO Admin')
@section('page_title','Version 2.2.1 Repair')
@section('content')
<div class="card">
    <h2>Version 2.2.1: Shared Hosting Update Repair</h2>
    <p class="muted">This repair removes fragile database transaction wrapping from the web updater. Shared-hosting MySQL/MariaDB can auto-commit schema changes, which can cause <strong>PDOException: There is no active transaction</strong>.</p>

    <div class="grid cols-3" style="margin:16px 0">
        <div class="card metric"><span>CRM tables ready</span><strong>{{ $crmInstalled ? 'Yes' : 'No' }}</strong></div>
        <div class="card metric"><span>Leave removal ready</span><strong>{{ $leaveRemovalReady ? 'Yes' : 'No' }}</strong></div>
        <div class="card metric"><span>Permissions installed</span><strong>{{ $permissionCount }}/7</strong></div>
    </div>

    <form method="post" action="{{ route('updates.v2_2_1.apply') }}">
        @csrf
        <button class="btn primary" type="submit">Apply Version 2.2.1 Repair</button>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <h2>What this fixes</h2>
    <ul class="muted" style="line-height:1.8">
        <li>Fixes <strong>PDOException: There is no active transaction</strong> during web updates.</li>
        <li>Rechecks the v2.2 CRM client tables.</li>
        <li>Rechecks the v2.2 director leave-removal columns.</li>
        <li>Re-applies the v2.2 CRM and leave-removal permissions.</li>
        <li>Clears available Laravel cache files on shared hosting.</li>
    </ul>
</div>
@endsection
