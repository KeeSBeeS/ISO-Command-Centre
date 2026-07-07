@extends('layouts.app')
@section('title','Vehicle Tracking | ISO Admin')
@section('page_title','Vehicle Tracking')
@section('content')
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">🛰️</div>
        <div>
            <h2>Vehicle Tracking</h2>
            <p>Latest tracking data synced from Cartrack and linked to local vehicles.</p>
        </div>
    </div>
    <div class="actions">
        @if(auth()->user()->hasPermission('vehicle_tracking.sync'))
            <form method="post" action="{{ route('vehicle_tracking.sync') }}">@csrf<button class="btn primary" type="submit">Sync Cartrack</button></form>
        @endif
        @if(auth()->user()->hasPermission('vehicle_tracking.settings.view'))
            <a class="btn" href="{{ route('vehicle_tracking.settings') }}">API Settings</a>
        @endif
    </div>
</div>

@if(!$configured)
    <div class="alert warning">Cartrack integration is not fully configured yet. A System Administrator must configure the API settings first.</div>
@endif

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th>Driver</th>
                    <th>Cartrack ID</th>
                    <th>Status</th>
                    <th>ODO</th>
                    <th>Location</th>
                    <th>Last Sync</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($vehicles as $vehicle)
                <tr>
                    <td><strong>{{ $vehicle->display_name }}</strong><br><span class="muted small">{{ $vehicle->registration_number ?? 'No registration' }}</span></td>
                    <td>{{ optional(optional($vehicle->currentAssignment)->user)->name ?? '-' }}</td>
                    <td>{{ $vehicle->cartrack_vehicle_id ?? '-' }}</td>
                    <td><span class="pill {{ $vehicle->tracking_last_status ? '' : 'off' }}">{{ $vehicle->tracking_last_status ?? 'No data' }}</span></td>
                    <td>{{ $vehicle->tracking_last_odometer ? number_format($vehicle->tracking_last_odometer) : number_format($vehicle->latest_odometer ?? 0) }}</td>
                    <td>
                        @if($vehicle->tracking_last_latitude && $vehicle->tracking_last_longitude)
                            {{ $vehicle->tracking_last_latitude }}, {{ $vehicle->tracking_last_longitude }}<br>
                            <span class="muted small">{{ $vehicle->tracking_last_address ?? 'Address not supplied' }}</span>
                        @else
                            <span class="muted">No location</span>
                        @endif
                    </td>
                    <td>{{ optional($vehicle->tracking_last_sync_at)->format('Y-m-d H:i') ?? '-' }}</td>
                    <td><a class="btn" href="{{ route('vehicles.show',$vehicle) }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No vehicles found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:12px">{{ $vehicles->links() }}</div>
</div>
@endsection
