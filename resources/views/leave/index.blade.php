@extends('layouts.app')
@section('title','Leave | ISO Admin')
@section('page_title','Leave')
@section('content')
<div class="actions" style="justify-content:space-between;margin-bottom:14px">
    <div>
        <h2 style="margin:0">Leave Requests</h2>
        <p class="muted">Submit, review and approve employee leave. Deductible status is copied from the selected leave type.</p>
    </div>
    <div class="actions">
        @if(auth()->user()->hasPermission('calendar.view'))<a class="btn" href="{{ route('calendar.index') }}">📅 Calendar</a>@endif
        @if(auth()->user()->hasPermission('leave.create'))<a class="btn primary" href="{{ route('leave.create') }}">Request Leave</a>@endif
    </div>
</div>

<div class="grid cols-3">
    <div class="card metric"><span>Pending</span><strong>{{ $pendingCount }}</strong></div>
    <div class="card metric"><span>Approved</span><strong>{{ $approvedCount }}</strong></div>
    <div class="card metric"><span>Declined</span><strong>{{ $declinedCount }}</strong></div>
</div>
<div style="height:14px"></div>

<div class="card">
    <form method="get" class="form-grid">
        <div><label>Status</label><select name="status"><option value="">All</option><option value="pending" @selected(request('status')==='pending')>Pending</option><option value="approved" @selected(request('status')==='approved')>Approved</option><option value="declined" @selected(request('status')==='declined')>Declined</option><option value="cancelled" @selected(request('status')==='cancelled')>Cancelled</option></select></div>
        @if(auth()->user()->hasPermission('leave.manage'))<div><label>Employee</label><input type="text" name="employee" value="{{ request('employee') }}" placeholder="Name or email"></div>@endif
        <div class="actions" style="align-self:end"><button class="btn" type="submit">Filter</button><a class="btn" href="{{ route('leave.index') }}">Reset</a></div>
    </form>
</div>
<div style="height:14px"></div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Employee</th><th>Leave Type</th><th>Dates</th><th>Days</th><th>Deductible</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($leaveRequests as $leave)
                <tr>
                    <td><strong>{{ $leave->user->name }}</strong><br><span class="muted small">{{ $leave->user->email }}</span></td>
                    <td>{{ optional($leave->leaveType)->name ?? 'Leave' }}</td>
                    <td>{{ $leave->date_range_label }}</td>
                    <td>{{ number_format((float)$leave->total_days, 2) }}</td>
                    <td><span class="pill {{ $leave->is_deductible ? '' : 'off' }}">{{ $leave->is_deductible ? 'Deductible' : 'Non-deductible' }}</span></td>
                    <td><span class="pill {{ $leave->status === 'approved' ? '' : ($leave->status === 'pending' ? 'warning' : 'off') }}">{{ $leave->status_label }}</span></td>
                    <td><a class="btn" href="{{ route('leave.show', $leave) }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No leave requests found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $leaveRequests->links() }}</div>
</div>
@endsection
