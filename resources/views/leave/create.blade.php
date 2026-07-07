@extends('layouts.app')
@section('title','Request Leave | ISO Admin')
@section('page_title','Request Leave')
@section('content')
<div class="card">
    <h2>Leave Request</h2>
    <p class="muted">Managers and Directors can capture leave on behalf of employees. Employee-submitted leave is pending until approved.</p>
    <form method="post" action="{{ route('leave.store') }}" class="form-grid" style="margin-top:14px">
        @csrf
        @if(auth()->user()->hasPermission('leave.manage'))
            <div><label>Employee</label><select name="user_id">@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }} - {{ $employee->email }}</option>@endforeach</select></div>
        @endif
        <div><label>Leave Type</label><select name="leave_type_id" required>@foreach($leaveTypes as $leaveType)<option value="{{ $leaveType->id }}">{{ $leaveType->name }} {{ $leaveType->is_deductible ? '(Deductible)' : '(Non-deductible)' }}</option>@endforeach</select></div>
        <div><label>Start Date</label><input type="date" name="start_date" required value="{{ old('start_date') }}"></div>
        <div><label>End Date</label><input type="date" name="end_date" required value="{{ old('end_date') }}"></div>
        <div class="form-row full"><label>Reason / Notes</label><textarea name="reason" rows="4" placeholder="Optional notes">{{ old('reason') }}</textarea></div>
        <div class="form-row full actions"><button class="btn primary" type="submit">Save Leave</button><a class="btn" href="{{ route('leave.index') }}">Cancel</a></div>
    </form>
</div>
@endsection
