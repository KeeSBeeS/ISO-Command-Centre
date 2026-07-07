@extends('layouts.app')
@section('title','Version 2.1 Update | ISO Admin')
@section('page_title','Version 2.1 Update')
@section('content')
<div class="card">
    <h2>Version 2.1: Leave Management & Calendar Visual Repair</h2>
    <p class="muted">This update adds full leave management, fixes the sick-note/clear 404 issue, makes calendar leave items clickable, and improves the visual layout with icons.</p>

    <div class="grid cols-2" style="margin:16px 0">
        <div class="card metric"><span>Leave columns ready</span><strong>{{ $leaveColumnsReady ? 'Yes' : 'No' }}</strong></div>
        <div class="card metric"><span>New permissions installed</span><strong>{{ $permissionCount }}/3</strong></div>
    </div>

    <form method="post" action="{{ route('updates.v2_1.apply') }}">
        @csrf
        <button class="btn primary" type="submit">Apply Version 2.1 Update</button>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <h2>Included</h2>
    <ul class="muted" style="line-height:1.8">
        <li>Director override from Sick Leave to Unpaid Leave.</li>
        <li>404-safe leave/sick clear routes.</li>
        <li>Sick Leave extension for authorised admin personnel.</li>
        <li>Unpaid Leave, Normal Leave and Family Responsibility Leave under each employee.</li>
        <li>One clickable calendar entry per leave date.</li>
        <li>Icon-based visual UI on employee profiles and calendar events.</li>
        <li>New permissions: employee_leave.mark, employee_leave.manage, employee_leave.override.</li>
    </ul>
</div>
@endsection
