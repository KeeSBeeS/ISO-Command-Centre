@extends('layouts.app')
@section('title','Employees | ISO Admin')
@section('page_title','Employees')
@section('content')
<div class="card">
    <form method="get" class="form-grid" style="align-items:end">
        <div class="form-row"><label>Search</label><input type="text" name="search" value="{{ request('search') }}" placeholder="Name, email or employee code"></div>
        <div class="form-row"><label>Status</label><select name="status"><option value="">All</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option></select></div>
        <div class="actions"><button class="btn" type="submit">Filter</button><a class="btn" href="{{ route('employees.index') }}">Reset</a></div>
        @if(auth()->user()->hasPermission('employees.create'))<div class="actions right"><a class="btn primary" href="{{ route('employees.create') }}">Add Employee</a></div>@endif
    </form>
</div>
<div style="height:14px"></div>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Employee</th><th>Department</th><th>Roles</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($employees as $employee)
                <tr>
                    <td><strong>{{ $employee->name }}</strong><br><span class="muted small">{{ $employee->email }}</span><br><span class="muted small">{{ $employee->position }}</span></td>
                    <td>@forelse($employee->departments as $department)<span class="pill">{{ $department->name }}</span>@empty<span class="muted">None</span>@endforelse</td>
                    <td>@forelse($employee->roles as $role)<span class="pill">{{ $role->name }}</span>@empty<span class="muted">None</span>@endforelse</td>
                    <td><span class="pill {{ $employee->status === 'active' ? '' : 'off' }}">{{ ucfirst($employee->status) }}</span></td>
                    <td class="actions right"><a class="btn" href="{{ route('employees.show',$employee) }}">View</a>@if(auth()->user()->hasPermission('employees.edit'))<a class="btn" href="{{ route('employees.edit',$employee) }}">Edit</a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No employees loaded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $employees->links() }}</div>
</div>
@endsection
