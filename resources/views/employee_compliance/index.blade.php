@extends('layouts.app')
@section('title','Employee Compliance Overview | ISO Admin')
@section('page_title','Employee Compliance Overview')
@section('content')
<div class="card">
    <div class="actions" style="justify-content:space-between">
        <div>
            <h2 style="margin-bottom:6px">Employee Compliance Overview</h2>
            <p class="muted">Compliance is based on each active employee's uploaded documents: an employee is compliant when they have at least one document on file and none of their active documents have expired.</p>
        </div>
        <a class="btn" href="{{ route('employee_documents.reminders') }}">Document Reminders</a>
    </div>
    <div class="grid cols-4" style="margin-top:12px">
        <div class="card metric"><span>Active Employees</span><strong>{{ $summary['total_active_employees'] }}</strong></div>
        <div class="card metric"><span>Compliant</span><strong>{{ $summary['compliant_employees'] }}</strong></div>
        <div class="card metric"><span>Missing Documents</span><strong>{{ $summary['missing_documents_employees'] }}</strong></div>
        <div class="card metric"><span>Documents Needing Attention</span><strong>{{ $summary['documents_needing_attention'] }}</strong></div>
    </div>
</div>
<div style="height:14px"></div>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Departments</th>
                    <th>Documents on File</th>
                    <th>Expired</th>
                    <th>Reminder Due</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                    <tr>
                        <td><strong>{{ $employee->name }}</strong><br><span class="muted small">{{ $employee->email }}</span></td>
                        <td>@forelse($employee->departments as $department)<span class="pill">{{ $department->name }}</span>@empty<span class="muted small">None</span>@endforelse</td>
                        <td>{{ $employee->compliance['total_documents'] }}</td>
                        <td>{{ $employee->compliance['expired_count'] }}</td>
                        <td>{{ $employee->compliance['reminder_due_count'] }}</td>
                        <td>
                            @if($employee->compliance['has_no_documents'])
                                <span class="pill off">No Documents</span>
                            @elseif($employee->compliance['is_compliant'])
                                <span class="pill">Compliant</span>
                            @else
                                <span class="pill danger">Non-Compliant</span>
                            @endif
                        </td>
                        <td><a class="btn" href="{{ route('employees.show', $employee) }}">Open Profile</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No active employees found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
