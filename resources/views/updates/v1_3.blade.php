@extends('layouts.app')
@section('title','Update v1.3 | ISO Admin')
@section('page_title','Version Update v1.3')
@section('content')
<div class="card">
    <h2>Employee Profile Documents & Expiry Reminders</h2>
    <p class="muted">This update adds employee document uploads for medicals, sick notes, warnings, certificates, company policies and expiry-controlled reminders.</p>

    <div class="grid cols-2">
        <div class="card" style="box-shadow:none">
            <h3>Database</h3>
            <p><span class="pill {{ $documentsInstalled ? '' : 'off' }}">{{ $documentsInstalled ? 'employee_documents table installed' : 'employee_documents table missing' }}</span></p>
        </div>
        <div class="card" style="box-shadow:none">
            <h3>Permissions</h3>
            <p><span class="pill {{ $permissionCount >= 3 ? '' : 'off' }}">{{ $permissionCount }}/3 document permissions installed</span></p>
        </div>
    </div>

    <form method="post" action="{{ route('updates.v1_3.apply') }}" style="margin-top:16px">
        @csrf
        <button class="btn primary" type="submit">Apply v1.3 Update</button>
    </form>
</div>
@endsection
