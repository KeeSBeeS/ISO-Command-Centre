@extends('layouts.app')
@section('title','Leave Types | ISO Admin')
@section('page_title','Leave Types')
@section('content')
<div class="actions" style="justify-content:space-between;margin-bottom:14px">
    <div><h2 style="margin:0">Leave Type Settings</h2><p class="muted">Manage the leave categories employees can use and whether each type deducts from allocated leave.</p></div>
    @if(auth()->user()->hasPermission('leave_types.manage'))<a class="btn primary" href="{{ route('leave_types.create') }}">Add Leave Type</a>@endif
</div>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Code</th><th>Deductible</th><th>Status</th><th>Sort</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($leaveTypes as $leaveType)
                <tr>
                    <td><strong>{{ $leaveType->name }}</strong><br><span class="muted small">{{ $leaveType->description }}</span></td>
                    <td>{{ $leaveType->code }}</td>
                    <td><span class="pill {{ $leaveType->is_deductible ? '' : 'off' }}">{{ $leaveType->is_deductible ? 'Deducts leave' : 'Non-deductible' }}</span></td>
                    <td><span class="pill {{ $leaveType->is_active ? '' : 'off' }}">{{ $leaveType->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td>{{ $leaveType->sort_order }}</td>
                    <td><div class="actions">@if(auth()->user()->hasPermission('leave_types.manage'))<a class="btn" href="{{ route('leave_types.edit',$leaveType) }}">Edit</a>@if($leaveType->is_active)<form method="post" action="{{ route('leave_types.destroy',$leaveType) }}" onsubmit="return confirm('Mark this leave type inactive?')">@csrf @method('DELETE')<button class="btn danger" type="submit">Inactive</button></form>@endif @endif</div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No leave types configured.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $leaveTypes->links() }}</div>
</div>
@endsection
