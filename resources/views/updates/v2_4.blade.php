@extends('layouts.app')
@section('title','Version 2.4 Update | ISO Admin')
@section('page_title','Version 2.4 Update')
@section('content_class','content-wide')
@section('content')
<div class="card">
    <h2>Version 2.4: Leave Allocation + Overtime</h2>
    <p class="muted">This update adds annual leave allocation/balance tracking and an overtime register linked to client sites.</p>

    <div class="grid cols-4" style="margin:16px 0">
        <div class="card metric"><span>Leave allocation table</span><strong>{{ $leaveAllocationReady ? 'Yes' : 'No' }}</strong></div>
        <div class="card metric"><span>Overtime table</span><strong>{{ $overtimeReady ? 'Yes' : 'No' }}</strong></div>
        <div class="card metric"><span>New permissions</span><strong>{{ $permissionCount }}/4</strong></div>
        <div class="card metric"><span>Version</span><strong>{{ $version }}</strong></div>
    </div>

    <form method="post" action="{{ route('updates.v2_4.apply') }}">
        @csrf
        <button class="btn primary" type="submit">Apply Version 2.4 Update</button>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <h2>What this adds</h2>
    <ul class="muted" style="line-height:1.8">
        <li>Directors can remove any leave entry from any employee.</li>
        <li>Directors can allocate annual leave days per employee per year.</li>
        <li>Normal leave usage is calculated from employee leave records.</li>
        <li>Leave balances show on employee profiles.</li>
        <li>Overtime can be captured for any employee.</li>
        <li>Overtime is linked to a client site and can be marked as installation, service, or both.</li>
        <li>Overtime entries appear on the calendar and are clickable.</li>
    </ul>
</div>
@endsection
