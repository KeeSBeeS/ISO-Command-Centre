@extends('layouts.app')
@section('title','My Profile | ISO Admin')
@section('page_title','My Profile')
@section('content')
<div class="grid cols-2">
    <div class="card">
        <h2>{{ $user->name }}</h2>
        <p class="muted">{{ $user->email }}</p>
        <div><span class="pill {{ $user->status === 'active' ? '' : 'off' }}">{{ ucfirst($user->status) }}</span></div>
        <hr style="border-color:var(--line);border-width:0 0 1px;margin:18px 0">
        <p><strong>Job Title:</strong> {{ optional($user->profile)->job_title ?? $user->position ?? 'Not set' }}</p>
        <p><strong>Employee Code:</strong> {{ $user->employee_code ?? 'Not set' }}</p>
        <p><strong>Attendance CSV Name:</strong> {{ $user->attendance_name ?? $user->name }}</p>
        <p><strong>Phone:</strong> {{ optional($user->profile)->phone ?? $user->phone ?? 'Not set' }}</p>
        <p><strong>Mobile:</strong> {{ optional($user->profile)->mobile ?? 'Not set' }}</p>
        <p><strong>Started:</strong> {{ optional(optional($user->profile)->started_at)->format('Y-m-d') ?? 'Not set' }}</p>
        <div class="actions"><a class="btn primary" href="{{ route('password.edit') }}">Change Password</a></div>
    </div>
    <div class="grid">
        <div class="card"><h2>Departments</h2>@forelse($user->departments as $department)<span class="pill">{{ $department->name }}</span>@empty<p class="muted">No department assigned.</p>@endforelse</div>
        <div class="card"><h2>Roles</h2>@forelse($user->roles as $role)<span class="pill">{{ $role->name }}</span>@empty<p class="muted">No role assigned.</p>@endforelse</div>
    </div>
</div>
<div style="height:14px"></div>
@if(\Illuminate\Support\Facades\Schema::hasTable('employee_documents'))
<div class="card">
    <h2>My Documents</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Document</th><th>Type</th><th>Expiry</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($user->documents->sortByDesc('created_at') as $document)
                <tr>
                    <td><strong>{{ $document->title }}</strong><br><span class="muted small">{{ $document->original_filename }} · {{ $document->file_size_label }}</span></td>
                    <td><span class="pill">{{ $document->type_label }}</span></td>
                    <td>
                        {{ $document->has_expiry ? optional($document->expires_at)->format('Y-m-d') : 'No expiry' }}
                        @if($document->has_expiry)<br><span class="muted small">{{ $document->expiry_summary }}</span>@endif
                    </td>
                    <td>@include('employee_documents._status_pill')</td>
                    <td>@if(auth()->user()->hasPermission('employee_documents.view'))<a class="btn" href="{{ route('employee_documents.download', $document) }}">Download</a>@else<span class="muted">No access</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No documents attached to your profile.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div style="height:14px"></div>
@endif
@if(\Illuminate\Support\Facades\Schema::hasTable('vehicle_assignments') && auth()->user()->hasPermission('vehicle.view'))
<div class="card">
    <h2>My Assigned Vehicles</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Vehicle</th><th>Assigned</th><th>Policy</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($user->currentVehicleAssignments as $assignment)
                <tr>
                    <td><strong>{{ optional($assignment->vehicle)->display_name ?? 'Unknown vehicle' }}</strong><br><span class="muted small">Reg: {{ optional($assignment->vehicle)->registration_number ?? 'Not set' }}</span></td>
                    <td>{{ optional($assignment->assigned_at)->format('Y-m-d H:i') }}</td>
                    <td><span class="pill {{ $assignment->policy_warning ? 'off' : '' }}">{{ $assignment->policy_warning ? 'Warning' : 'Valid' }}</span></td>
                    <td>@if($assignment->vehicle)<a class="btn" href="{{ route('vehicles.show',$assignment->vehicle) }}">Open</a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No active vehicle assignment.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div style="height:14px"></div>
@endif
<div class="card">
    <h2>Effective Permissions</h2>
    @forelse($user->effectivePermissions() as $permission)<span class="pill">{{ $permission->slug }}</span>@empty<p class="muted">No permissions assigned.</p>@endforelse
</div>
@endsection
