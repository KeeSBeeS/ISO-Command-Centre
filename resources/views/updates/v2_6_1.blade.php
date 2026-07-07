@extends('layouts.app')
@section('title','Update v2.6.1 | ISO Admin')
@section('page_title','Update v2.6.1')
@section('content')
<div class="card">
    <div class="page-head">
        <div class="page-head-main">
            <div class="page-head-icon">⬆️</div>
            <div>
                <h2>Version 2.6.1 Recovery Update</h2>
                <p>Restores Calendar and Leave, adds the footer version number, and makes System Administrator a full-access override role.</p>
            </div>
        </div>
    </div>
    <div class="grid cols-4">
        <div class="card metric"><span>Leave Types</span><strong style="font-size:18px">{{ $leaveTypesInstalled ? 'Ready' : 'Missing' }}</strong></div>
        <div class="card metric"><span>Leave Requests</span><strong style="font-size:18px">{{ $leaveInstalled ? 'Ready' : 'Install' }}</strong></div>
        <div class="card metric"><span>Permissions</span><strong>{{ $permissionCount }}/4</strong></div>
        <div class="card metric"><span>System Admin</span><strong style="font-size:18px">{{ $systemAdministratorHasAllPermissions ? 'All Access' : 'Sync Needed' }}</strong></div>
    </div>
    <form method="post" action="{{ route('updates.v2_6_1.apply') }}" style="margin-top:18px">
        @csrf
        <button class="btn primary" type="submit">Run v2.6.1 Update</button>
    </form>
</div>
@endsection
