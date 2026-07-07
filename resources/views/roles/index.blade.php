@extends('layouts.app')
@section('title','Roles & Permissions | ISO Admin')
@section('page_title','Roles & Permissions')
@section('content')
<div class="actions right" style="margin-bottom:14px">
    @if(auth()->user()->hasPermission('roles.create'))<a class="btn primary" href="{{ route('roles.create') }}">Add Role</a>@endif
</div>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Role</th><th>Level</th><th>Users</th><th>Permissions</th><th>Type</th><th></th></tr></thead>
            <tbody>
            @forelse($roles as $role)
                <tr>
                    <td><strong>{{ $role->name }}</strong><br><span class="muted small">{{ $role->slug }}</span></td>
                    <td>{{ $role->level }}</td>
                    <td>{{ $role->users_count }}</td>
                    <td>{{ $role->permissions_count }}</td>
                    <td><span class="pill {{ $role->is_system ? '' : 'off' }}">{{ $role->is_system ? 'System' : 'Custom' }}</span></td>
                    <td class="actions right">
                        @if(auth()->user()->hasPermission('roles.edit'))<a class="btn" href="{{ route('roles.edit',$role) }}">Edit Matrix</a>@endif
                        @if(auth()->user()->hasPermission('roles.delete') && !$role->is_system)<form method="post" action="{{ route('roles.destroy',$role) }}" onsubmit="return confirm('Delete this role?')">@csrf @method('DELETE')<button class="btn danger" type="submit">Delete</button></form>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No roles created yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $roles->links() }}</div>
</div>
@endsection
