@extends('layouts.app')
@section('title','Update v1.6 | ISO Admin')
@section('page_title','Version 1.6 Update')
@section('content')
<div class="card">
    <h2>User Login Email + First Login Password Change</h2>
    <p class="muted">This update adds secure onboarding fields to the users table and enables new employee credential emails.</p>
    <ul class="muted">
        <li>Generated password option on employee create/edit.</li>
        <li>New user login details emailed automatically.</li>
        <li>New users must change their temporary password before dashboard access.</li>
        <li>If a password is reset by a manager/director, the user is again forced to change it on next login.</li>
    </ul>
    <p><strong>Status:</strong> {{ $columnsInstalled ? 'Installed' : 'Not installed yet' }}</p>
    <form method="post" action="{{ route('updates.v1_6.apply') }}">
        @csrf
        <button class="btn primary" type="submit">Apply v1.6 Update</button>
    </form>
</div>
@endsection
