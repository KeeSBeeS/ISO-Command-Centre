@extends('layouts.app')
@section('title','Employee Compliance | ISO Admin')
@section('page_title','Employee Compliance')
@section('content')
<div class="card">
    <h2 style="margin-bottom:6px">Employee Compliance Overview Unavailable</h2>
    <p class="muted">The employee compliance module files are not installed on this system, so the full compliance overview cannot be displayed. The dashboard link has been restored so login keeps working. Re-apply the v2.8.0 Employee Compliance update package to restore the full overview.</p>
    <div class="actions" style="margin-top:12px">
        <a class="btn" href="{{ route('dashboard') }}">Back to Dashboard</a>
        <a class="btn" href="{{ route('employees.index') }}">Employees</a>
        <a class="btn" href="{{ route('employee_documents.reminders') }}">Document Reminders</a>
    </div>
</div>
@endsection
