@extends('layouts.app')
@section('title','Vehicle Dashboard | ISO Admin')
@section('page_title','Vehicle Dashboard')
@section('content')
<style>
    .vehicle-kpi-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px}.vehicle-kpi{border:1px solid var(--line);border-radius:18px;padding:16px;background:rgba(255,255,255,.045);box-shadow:var(--shadow-soft)}.vehicle-kpi span{display:block;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.08em}.vehicle-kpi strong{display:block;font-size:28px;margin-top:8px;letter-spacing:-.04em}.vehicle-dash-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:14px}.mini-table table{min-width:620px}.tracking-map{height:520px;min-height:60vh;border-radius:20px;border:1px solid var(--line);overflow:hidden;background:#0a1720;box-shadow:var(--shadow-soft)}@media(max-width:1100px){.vehicle-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.vehicle-dash-grid{grid-template-columns:1fr}}@media(max-width:720px){.vehicle-kpi-grid{grid-template-columns:1fr}.tracking-map{height:430px;min-height:55vh}}
</style>

<div class="card">
    <div class="actions" style="justify-content:space-between">
        <div>
            <h2 style="margin-bottom:6px">Vehicles, Fuel & Tracking</h2>
            <p class="muted">Fleet dashboard with fuel tracking, service status, employee assignment, vehicle documents and live tracking position.</p>
        </div>
        <div class="actions">
            @if(auth()->user()->hasPermission('vehicle.fuel.manage'))<a class="btn primary" href="{{ route('vehicles.fuel.select') }}">⛽ Add Fuel</a>@endif
            @if(auth()->user()->hasPermission('vehicle.create'))<a class="btn" href="{{ route('vehicles.create') }}">Add Vehicle</a>@endif
            @if(auth()->user()->hasPermission('vehicle.service.reminders.view') && \Illuminate\Support\Facades\Schema::hasTable('vehicle_service_records'))<a class="btn" href="{{ route('vehicles.service_reminders') }}">Service Reminders</a>@endif
            @if(auth()->user()->hasPermission('vehicle.reminders.view'))<a class="btn" href="{{ route('vehicles.reminders') }}">Vehicle Document Reminders</a>@endif
        </div>
    </div>
    <form method="get" class="form-grid" style="margin-top:14px">
        <div><label>Search</label><input type="text" name="search" value="{{ request('search') }}" placeholder="Make, model, registration, CSV name"></div>
        <div><label>Status</label><select name="status"><option value="">All</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option></select></div>
        <div class="actions"><button class="btn" type="submit">Filter</button><a class="btn" href="{{ route('vehicles.index') }}">Reset</a></div>
    </form>
</div>
<div style="height:14px"></div>

<div class="vehicle-kpi-grid">
    <div class="vehicle-kpi"><span>Total Vehicles</span><strong>{{ number_format($vehicles->total()) }}</strong></div>
    <div class="vehicle-kpi"><span>Fuel-ups This Month</span><strong>{{ number_format($fuelDashboard['fuel_up_count'] ?? 0) }}</strong></div>
    <div class="vehicle-kpi"><span>Litres This Month</span><strong>{{ number_format((float)($fuelDashboard['litres'] ?? 0), 1) }}</strong></div>
    <div class="vehicle-kpi"><span>Fuel Cost This Month</span><strong>R {{ number_format((float)($fuelDashboard['cost'] ?? 0), 2) }}</strong></div>
    <div class="vehicle-kpi"><span>Avg KM/L</span><strong>{{ ($fuelDashboard['average_km_per_litre'] ?? null) !== null ? number_format((float)$fuelDashboard['average_km_per_litre'], 2) : '-' }}</strong></div>
</div>
<div style="height:14px"></div>

<div class="vehicle-dash-grid">
    <div class="card mini-table">
        <div class="actions" style="justify-content:space-between">
            <div><h2 style="margin-bottom:6px">Fuel Tracking - This Month</h2><p class="muted">Top vehicles by fuel spend from {{ \Illuminate\Support\Carbon::parse($fuelDashboard['month_start'])->format('d M Y') }}.</p></div>
            @if(auth()->user()->hasPermission('vehicle.fuel.manage'))<a class="btn primary" href="{{ route('vehicles.fuel.select') }}">Add Fuel</a>@endif
        </div>
        <div class="table-wrap" style="margin-top:12px">
            <table>
                <thead><tr><th>Vehicle</th><th>Fuel-ups</th><th>Litres</th><th>Cost</th><th>Avg KM/L</th><th>Last Fuel</th></tr></thead>
                <tbody>
                @forelse(($fuelDashboard['top_vehicle_fuel'] ?? collect()) as $row)
                    <tr>
                        <td><strong>{{ optional($row->vehicle)->display_name ?? 'Vehicle #' . $row->vehicle_id }}</strong><br><span class="muted small">{{ optional(optional(optional($row->vehicle)->currentAssignment)->user)->name ?? 'Unassigned' }}</span></td>
                        <td>{{ number_format((int)$row->fuel_up_count) }}</td>
                        <td>{{ number_format((float)$row->litres, 1) }}</td>
                        <td>R {{ number_format((float)$row->cost, 2) }}</td>
                        <td>{{ $row->average_km_per_litre !== null ? number_format((float)$row->average_km_per_litre, 2) : '-' }}</td>
                        <td>{{ $row->last_fuelup_date ? \Illuminate\Support\Carbon::parse($row->last_fuelup_date)->format('Y-m-d') : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No fuel captured for this month yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mini-table">
        <h2 style="margin-bottom:6px">Recent Fuel-ups</h2>
        <p class="muted">Latest captured fuel records across all vehicles.</p>
        <div class="table-wrap" style="margin-top:12px">
            <table>
                <thead><tr><th>Date</th><th>Vehicle</th><th>ODO</th><th>Litres</th><th>Cost</th></tr></thead>
                <tbody>
                @forelse(($fuelDashboard['recent_fuel_ups'] ?? collect()) as $fuel)
                    <tr>
                        <td>{{ optional($fuel->fuelup_date)->format('Y-m-d') }}</td>
                        <td><a href="{{ $fuel->vehicle ? route('vehicles.show', $fuel->vehicle) : '#' }}">{{ optional($fuel->vehicle)->display_name ?? $fuel->model_name ?? 'Vehicle' }}</a></td>
                        <td>{{ $fuel->odometer !== null ? number_format((int)$fuel->odometer) : '-' }}</td>
                        <td>{{ number_format((float)$fuel->litres, 1) }}</td>
                        <td>R {{ number_format((float)$fuel->total_cost, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No fuel-ups captured yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<div style="height:14px"></div>

@if(($googleMaps['enabled'] ?? false) && auth()->user()->hasPermission('vehicle_tracking.view'))
<div class="card">
    <div class="actions" style="justify-content:space-between">
        <div>
            <h2 style="margin-bottom:6px">Fleet Tracking Map</h2>
            <p class="muted">Plots all active vehicles with latest latitude/longitude received from the tracking API.</p>
        </div>
        <div class="actions">
            @if(auth()->user()->hasPermission('vehicle_tracking.sync'))
                <form method="post" action="{{ route('vehicle_tracking.sync') }}">@csrf<button class="btn primary" type="submit">Sync Tracking</button></form>
            @endif
            <a class="btn" href="{{ route('vehicle_tracking.index') }}">Tracking Dashboard</a>
        </div>
    </div>

    @if($fleetMapVehicles->count())
        <div id="isoFleetMap" class="tracking-map"></div>
        <p class="muted small" style="margin-top:10px">Showing {{ $fleetMapVehicles->count() }} active vehicle(s) with GPS data.</p>
    @else
        <div class="alert warning" style="margin-top:12px">No vehicle GPS positions are available yet. Run the Cartrack sync and confirm each local vehicle is linked to the correct Cartrack vehicle ID or registration.</div>
    @endif
</div>
<div style="height:14px"></div>
@endif

<div class="table-wrap">
    <table>
        <thead><tr><th>Vehicle</th><th>ODO</th><th>Latest Fuel</th><th>Tracking</th><th>Service</th><th>Assigned To</th><th>Fuel Records</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        @forelse($vehicles as $vehicle)
            <tr>
                <td><strong>{{ $vehicle->display_name }}</strong><br><span class="muted small">Reg: {{ $vehicle->registration_number ?? $vehicle->cartrack_registration ?? 'Not set' }} · CSV: {{ $vehicle->vehicle_key ?? 'Not set' }}</span></td>
                <td>{{ number_format($vehicle->latest_odometer ?? $vehicle->odo ?? 0) }}</td>
                <td>@if($vehicle->latestFuelUp)<strong>{{ optional($vehicle->latestFuelUp->fuelup_date)->format('Y-m-d') }}</strong><br><span class="muted small">{{ number_format((float)$vehicle->latestFuelUp->litres, 1) }} L · R {{ number_format((float)$vehicle->latestFuelUp->total_cost, 2) }}</span>@else<span class="muted">No fuel</span>@endif</td>
                <td>
                    @if(\Illuminate\Support\Facades\Schema::hasColumn('vehicles','tracking_last_latitude') && $vehicle->tracking_last_latitude && $vehicle->tracking_last_longitude)
                        <span class="pill">GPS</span><br><span class="muted small">{{ optional($vehicle->tracking_last_sync_at)->format('Y-m-d H:i') ?? 'No sync time' }}</span>
                    @else
                        <span class="pill off">No GPS</span>
                    @endif
                </td>
                <td>@if(\Illuminate\Support\Facades\Schema::hasTable('vehicle_service_records'))@php($summary = $vehicle->service_summary)<span class="pill {{ in_array($summary['state'], ['overdue','no-baseline','not-configured']) ? 'off' : '' }}">{{ $summary['label'] }}</span><br><span class="muted small">Next: {{ $summary['next_service_odo'] !== null ? number_format($summary['next_service_odo']) : '-' }}</span>@else<span class="muted">Run v1.7</span>@endif</td>
                <td>{{ optional(optional($vehicle->currentAssignment)->user)->name ?? 'Unassigned' }}</td>
                <td>{{ $vehicle->fuel_ups_count ?? 0 }}</td>
                <td><span class="pill {{ $vehicle->status === 'active' ? '' : 'off' }}">{{ ucfirst($vehicle->status) }}</span></td>
                <td><div class="actions"><a class="btn" href="{{ route('vehicles.show',$vehicle) }}">Open</a>@if(auth()->user()->hasPermission('vehicle.fuel.manage'))<a class="btn" href="{{ route('vehicles.fuel.create',$vehicle) }}">Fuel</a>@endif</div></td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted">No vehicles added yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $vehicles->links() }}</div>

@if(($googleMaps['enabled'] ?? false) && auth()->user()->hasPermission('vehicle_tracking.view') && $fleetMapVehicles->count())
<script>
    window.isoFleetVehicles = {!! json_encode($fleetMapVehicles ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    window.isoFleetMapConfig = {!! json_encode($isoFleetMapConfig ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

    window.isoFleetEscapeHtml = function(value) {
        return String(value ?? '').replace(/[&<>'"]/g, function (char) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char];
        });
    };

    window.isoVehicleInfoHtml = function(vehicle) {
        return '<div style="color:#071118;max-width:260px">'
            + '<strong>' + window.isoFleetEscapeHtml(vehicle.name || 'Vehicle') + '</strong><br>'
            + '<span>Reg: ' + window.isoFleetEscapeHtml(vehicle.registration || '-') + '</span><br>'
            + '<span>Driver: ' + window.isoFleetEscapeHtml(vehicle.driver || 'Unassigned') + '</span><br>'
            + '<span>Speed: ' + (vehicle.speed ?? '-') + '</span><br>'
            + '<span>ODO: ' + (vehicle.odometer ?? '-') + '</span><br>'
            + '<span>Last sync: ' + window.isoFleetEscapeHtml(vehicle.last_sync || '-') + '</span><br><br>'
            + '<a href="' + vehicle.url + '">Open vehicle</a>'
            + '</div>';
    };

    window.initIsoFleetMap = function() {
        var element = document.getElementById('isoFleetMap');
        if (!element || !window.google || !window.google.maps) return;

        var vehicles = window.isoFleetVehicles || [];
        var config = window.isoFleetMapConfig || {};
        var defaultCenter = {lat: Number(config.default_latitude || -26.204103), lng: Number(config.default_longitude || 28.047305)};
        var options = {center: defaultCenter, zoom: Number(config.default_zoom || 7), mapTypeControl: false, streetViewControl: false};
        if (config.map_id) options.mapId = config.map_id;

        var map = new google.maps.Map(element, options);
        var infoWindow = new google.maps.InfoWindow();
        var bounds = new google.maps.LatLngBounds();
        var useAdvancedMarkers = !!(config.map_id && google.maps.marker && google.maps.marker.AdvancedMarkerElement);

        vehicles.forEach(function (vehicle) {
            var position = {lat: Number(vehicle.latitude), lng: Number(vehicle.longitude)};
            if (!isFinite(position.lat) || !isFinite(position.lng)) return;

            var marker = useAdvancedMarkers
                ? new google.maps.marker.AdvancedMarkerElement({map: map, position: position, title: vehicle.name || 'Vehicle'})
                : new google.maps.Marker({map: map, position: position, title: vehicle.name || 'Vehicle'});

            marker.addListener('click', function () {
                infoWindow.setContent(window.isoVehicleInfoHtml(vehicle));
                infoWindow.open({map: map, anchor: marker});
            });

            bounds.extend(position);
        });

        if (!bounds.isEmpty()) map.fitBounds(bounds, 64);
    };
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ rawurlencode($googleMaps['api_key'] ?? '') }}&libraries=marker&callback=initIsoFleetMap"></script>
@endif

@endsection
