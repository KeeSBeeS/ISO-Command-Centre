@extends('layouts.app')
@section('title','Vehicle Document Reminders | ISO Admin')
@section('page_title','Vehicle Document Reminders')
@section('content')
<div class="card">
    <div class="actions" style="justify-content:space-between">
        <div><h2 style="margin-bottom:6px">Vehicle Reminders</h2><p class="muted">NATIS, license disk and other vehicle documents that need attention.</p></div>
        <div class="actions">
            <a class="btn {{ $filter === 'due' ? 'primary' : '' }}" href="{{ route('vehicles.reminders',['filter'=>'due']) }}">Due Now</a>
            <a class="btn {{ $filter === 'expired' ? 'primary' : '' }}" href="{{ route('vehicles.reminders',['filter'=>'expired']) }}">Expired</a>
            <a class="btn {{ $filter === 'next60' ? 'primary' : '' }}" href="{{ route('vehicles.reminders',['filter'=>'next60']) }}">Next 60 Days</a>
            <a class="btn {{ $filter === 'inactive' ? 'primary' : '' }}" href="{{ route('vehicles.reminders',['filter'=>'inactive']) }}">Inactive</a>
        </div>
    </div>
</div>
<div style="height:14px"></div>
<div class="table-wrap">
    <table>
        <thead><tr><th>Vehicle</th><th>Document</th><th>Expiry</th><th>Reminder</th><th>Assigned To</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        @forelse($documents as $document)
            <tr>
                <td><strong>{{ optional($document->vehicle)->display_name ?? 'Unknown vehicle' }}</strong><br><span class="muted small">{{ optional($document->vehicle)->registration_number ?? 'No registration' }}</span></td>
                <td><strong>{{ $document->title }}</strong><br><span class="muted small">{{ $document->type_label }}</span></td>
                <td>{{ optional($document->expires_at)->format('Y-m-d') ?? '-' }}</td>
                <td>{{ optional($document->reminder_date)->format('Y-m-d') ?? '-' }}<br><span class="muted small">{{ $document->remind_days_before }} days before</span></td>
                <td>{{ optional(optional(optional($document->vehicle)->currentAssignment)->user)->name ?? 'Unassigned' }}</td>
                <td><span class="pill {{ in_array($document->expiry_state, ['expired','inactive']) ? 'off' : '' }}">{{ str_replace('-', ' ', ucfirst($document->expiry_state)) }}</span></td>
                <td><div class="actions"><a class="btn" href="{{ route('vehicles.show',$document->vehicle) }}">Vehicle</a><a class="btn" href="{{ route('vehicles.documents.download',$document) }}">Download</a>@if($document->status === 'active' && auth()->user()->hasPermission('vehicle.documents.manage'))<form method="post" action="{{ route('vehicles.documents.inactive',$document) }}" onsubmit="return confirm('Mark this document inactive?')">@csrf @method('PATCH')<button class="btn danger" type="submit">Inactive</button></form>@endif</div></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No vehicle document reminders found for this filter.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $documents->links() }}</div>
@endsection
