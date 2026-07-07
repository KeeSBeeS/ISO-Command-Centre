@extends('layouts.app')
@section('title','Vehicle Service Reminders | ISO Admin')
@section('page_title','Vehicle Service Reminders')
@section('content')
<div class="card">
    <div class="actions" style="justify-content:space-between">
        <div>
            <h2 style="margin-bottom:6px">Service Reminder Centre</h2>
            <p class="muted">Upcoming services are calculated from latest fuel-up ODO against each vehicle's last service ODO and configured service interval.</p>
        </div>
        <div class="actions"><a class="btn" href="{{ route('vehicles.index') }}">Vehicles</a><a class="btn" href="{{ route('vehicles.reminders') }}">Document Reminders</a></div>
    </div>
    <div class="actions" style="margin-top:12px">
        <a class="btn {{ $filter === 'due' ? 'primary' : '' }}" href="{{ route('vehicles.service_reminders',['filter'=>'due']) }}">Due Soon + Overdue</a>
        <a class="btn {{ $filter === 'overdue' ? 'primary' : '' }}" href="{{ route('vehicles.service_reminders',['filter'=>'overdue']) }}">Overdue</a>
        <a class="btn {{ $filter === 'missing' ? 'primary' : '' }}" href="{{ route('vehicles.service_reminders',['filter'=>'missing']) }}">Missing Baseline</a>
        <a class="btn {{ $filter === 'all' ? 'primary' : '' }}" href="{{ route('vehicles.service_reminders',['filter'=>'all']) }}">All Active</a>
    </div>
</div>
<div style="height:14px"></div>
<div class="table-wrap">
    <table>
        <thead><tr><th>Vehicle</th><th>Assigned To</th><th>Current ODO</th><th>Last Service</th><th>Next Service</th><th>Remaining</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        @forelse($vehicles as $vehicle)
            @php($summary = $vehicle->service_summary)
            <tr>
                <td><strong>{{ $vehicle->display_name }}</strong><br><span class="muted small">Reg: {{ $vehicle->registration_number ?? 'Not set' }} · Interval: {{ $summary['interval_km'] ? number_format($summary['interval_km']) . ' km' : 'Not set' }}</span></td>
                <td>{{ optional(optional($vehicle->currentAssignment)->user)->name ?? 'Unassigned' }}</td>
                <td>{{ $summary['current_odo'] !== null ? number_format($summary['current_odo']) : '-' }}</td>
                <td>{{ $summary['last_service_odo'] !== null ? number_format($summary['last_service_odo']) : '-' }}</td>
                <td>{{ $summary['next_service_odo'] !== null ? number_format($summary['next_service_odo']) : '-' }}</td>
                <td>{{ $summary['km_remaining'] !== null ? number_format($summary['km_remaining']) : '-' }}</td>
                <td><span class="pill {{ in_array($summary['state'], ['overdue','no-baseline','not-configured']) ? 'off' : '' }}">{{ $summary['label'] }}</span></td>
                <td><div class="actions"><a class="btn" href="{{ route('vehicles.show',$vehicle) }}">Open</a>@if(auth()->user()->hasPermission('vehicle.service.manage'))<a class="btn primary" href="{{ route('vehicles.services.create',$vehicle) }}">Add Service</a>@endif</div></td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">No vehicles match this filter.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
