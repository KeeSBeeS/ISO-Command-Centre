@extends('layouts.app')
@section('title','Departments | ISO Admin')
@section('page_title','Departments')
@section('content')
<div class="actions right" style="margin-bottom:14px">
    @if(auth()->user()->hasPermission('departments.create'))<a class="btn primary" href="{{ route('departments.create') }}">Add Department</a>@endif
</div>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Department</th><th>Description</th><th>Employees</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($departments as $department)
                <tr>
                    <td><strong>{{ $department->name }}</strong><br><span class="muted small">{{ $department->slug }}</span></td>
                    <td>{{ $department->description }}</td>
                    <td>{{ $department->users_count }}</td>
                    <td><span class="pill {{ $department->is_active ? '' : 'off' }}">{{ $department->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="actions right">
                        @if(auth()->user()->hasPermission('departments.edit'))<a class="btn" href="{{ route('departments.edit',$department) }}">Edit</a>@endif
                        @if(auth()->user()->hasPermission('departments.delete'))<form method="post" action="{{ route('departments.destroy',$department) }}" onsubmit="return confirm('Delete this department?')">@csrf @method('DELETE')<button class="btn danger" type="submit">Delete</button></form>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No departments created yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $departments->links() }}</div>
</div>
@endsection
