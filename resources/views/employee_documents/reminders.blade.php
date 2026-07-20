@extends('layouts.app')
@section('title','Document Reminders | ISO Admin')
@section('page_title','Employee Document Reminders')
@section('content')
<div class="card">
    <div class="actions" style="justify-content:space-between">
        <div>
            <h2 style="margin-bottom:6px">Documents Needing Attention</h2>
            <p class="muted">Expiry reminders are calculated from each document expiry date minus the selected advance reminder time. These also appear on the calendar.</p>
        </div>
        <a class="btn" href="{{ route('employees.index') }}">Employees</a>
    </div>
    <div class="grid cols-4" style="margin-top:12px">
        <div class="card metric"><span>Due Now</span><strong>{{ $summary['due'] }}</strong></div>
        <div class="card metric"><span>Expired</span><strong>{{ $summary['expired'] }}</strong></div>
        <div class="card metric"><span>Next 60 Days</span><strong>{{ $summary['next60'] }}</strong></div>
        <div class="card metric"><span>Inactive</span><strong>{{ $summary['inactive'] }}</strong></div>
    </div>
    <div class="actions" style="margin-top:12px">
        <a class="btn {{ $filter === 'due' ? 'primary' : '' }}" href="{{ route('employee_documents.reminders', ['filter' => 'due']) }}">Due Now</a>
        <a class="btn {{ $filter === 'expired' ? 'primary' : '' }}" href="{{ route('employee_documents.reminders', ['filter' => 'expired']) }}">Expired</a>
        <a class="btn {{ $filter === 'next60' ? 'primary' : '' }}" href="{{ route('employee_documents.reminders', ['filter' => 'next60']) }}">Next 60 Days</a>
        <a class="btn {{ $filter === 'inactive' ? 'primary' : '' }}" href="{{ route('employee_documents.reminders', ['filter' => 'inactive']) }}">Inactive</a>
    </div>
</div>
<div style="height:14px"></div>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Document</th>
                    <th>Type</th>
                    <th>Expiry</th>
                    <th>Reminder</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $document)
                    <tr>
                        <td>
                            <strong>{{ optional($document->employee)->name ?? 'Unknown employee' }}</strong><br>
                            <span class="muted small">{{ optional($document->employee)->email }}</span>
                        </td>
                        <td>
                            <strong>{{ $document->title }}</strong><br>
                            <span class="muted small">{{ $document->original_filename }}</span>
                        </td>
                        <td><span class="pill">{{ $document->type_label }}</span></td>
                        <td>
                            {{ optional($document->expires_at)->format('Y-m-d') ?? 'No expiry' }}
                            @if($document->has_expiry)<br><span class="muted small">{{ $document->expiry_summary }}</span>@endif
                        </td>
                        <td>
                            {{ optional($document->reminder_date)->format('Y-m-d') ?? 'None' }}<br>
                            <span class="muted small">{{ $document->remind_days_before }} days before</span>
                        </td>
                        <td>@include('employee_documents._status_pill')</td>
                        <td>
                            <div class="actions">
                                <a class="btn" href="{{ route('employee_documents.download', $document) }}">Download</a>
                                @if($document->employee)
                                    <a class="btn" href="{{ route('employees.show', $document->employee) }}">Profile</a>
                                @endif
                                @if(auth()->user()->hasPermission('employee_documents.manage'))
                                    <a class="btn" href="{{ route('employee_documents.edit', $document) }}">Edit</a>
                                    @if($document->status === 'active')
                                        <form method="post" action="{{ route('employee_documents.inactive', $document) }}" onsubmit="return confirm('Mark this document as inactive?')">
                                            @csrf @method('PATCH')
                                            <button class="btn danger" type="submit">Inactive</button>
                                        </form>
                                    @else
                                        <form method="post" action="{{ route('employee_documents.reactivate', $document) }}">
                                            @csrf @method('PATCH')
                                            <button class="btn" type="submit">Reactivate</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No documents found for this reminder filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $documents->links() }}</div>
</div>
@endsection
