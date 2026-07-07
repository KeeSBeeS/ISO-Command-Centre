@extends('layouts.app')
@section('title','Leave Request | ISO Admin')
@section('page_title','Leave Request')
@section('content')
<div class="card">
    <div class="actions" style="justify-content:space-between">
        <div>
            <h2 style="margin-bottom:6px">{{ $leaveRequest->user->name }}</h2>
            <p class="muted">{{ optional($leaveRequest->leaveType)->name ?? 'Leave' }} · {{ $leaveRequest->date_range_label }}</p>
        </div>
        <span class="pill {{ $leaveRequest->status === 'approved' ? '' : ($leaveRequest->status === 'pending' ? 'warning' : 'off') }}">{{ $leaveRequest->status_label }}</span>
    </div>
    <div class="grid cols-4" style="margin-top:14px">
        <div class="card metric"><span>Total Days</span><strong>{{ number_format((float)$leaveRequest->total_days, 2) }}</strong></div>
        <div class="card metric"><span>Deductible</span><strong style="font-size:18px">{{ $leaveRequest->is_deductible ? 'Yes' : 'No' }}</strong></div>
        <div class="card metric"><span>Reviewed By</span><strong style="font-size:18px">{{ optional($leaveRequest->reviewer)->name ?? '-' }}</strong></div>
        <div class="card metric"><span>Reviewed At</span><strong style="font-size:18px">{{ optional($leaveRequest->reviewed_at)->format('Y-m-d H:i') ?? '-' }}</strong></div>
    </div>
    <div style="height:14px"></div>
    <div class="grid cols-2">
        <div><h3>Reason</h3><p class="muted">{{ $leaveRequest->reason ?: 'No reason supplied.' }}</p></div>
        <div><h3>Manager Notes</h3><p class="muted">{{ $leaveRequest->manager_notes ?: 'No manager notes.' }}</p></div>
    </div>
    <div class="actions" style="margin-top:18px">
        <a class="btn" href="{{ route('leave.index') }}">Back</a>
        @if(auth()->user()->hasPermission('leave.manage') && $leaveRequest->status === 'pending')
            <form method="post" action="{{ route('leave.approve', $leaveRequest) }}">@csrf<button class="btn primary" type="submit">Approve</button></form>
            <form method="post" action="{{ route('leave.decline', $leaveRequest) }}" onsubmit="return confirm('Decline this leave request?')">@csrf<button class="btn danger" type="submit">Decline</button></form>
        @endif
        @if($leaveRequest->status !== 'cancelled')
            <form method="post" action="{{ route('leave.cancel', $leaveRequest) }}" onsubmit="return confirm('Cancel this leave request?')">@csrf<button class="btn" type="submit">Cancel Leave</button></form>
        @endif
    </div>
</div>
@endsection
