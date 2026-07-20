@extends('layouts.app')
@section('title',$vehicle->display_name.' | ISO Admin')
@section('page_title','Vehicle Profile')
@section('content')
<div class="grid cols-2">
    <div class="card">
        <h2>{{ $vehicle->display_name }}</h2>
        <p class="muted">Registration: {{ $vehicle->registration_number ?? 'Not set' }} · CSV Name: {{ $vehicle->vehicle_key ?? 'Not set' }}</p>
        @if(\Illuminate\Support\Facades\Schema::hasColumn('vehicles','year_model'))
            <p><strong>Year:</strong> {{ $vehicle->year_model ?? 'Not set' }} · <strong>Colour:</strong> {{ $vehicle->colour ?? 'Not set' }}</p>
        @endif
        <p><strong>Current ODO:</strong> {{ number_format($vehicle->latest_odometer ?? $vehicle->odo ?? 0) }}</p>
        @if(\Illuminate\Support\Facades\Schema::hasColumn('vehicles','tracking_company_name'))
            <hr style="border-color:var(--line);border-width:0 0 1px;margin:14px 0">
            <p><strong>Tracking Company:</strong> {{ $vehicle->tracking_company_name ?? 'Not set' }}</p>
            <p><strong>Tracking Contact:</strong> {{ $vehicle->tracking_company_contact ?? 'Not set' }}</p>
            <p><strong>Tracking Device / Account:</strong> {{ $vehicle->tracking_device_number ?? 'Not set' }}</p>
            @if($vehicle->tracking_notes)<p><strong>Tracking Notes:</strong> {{ $vehicle->tracking_notes }}</p>@endif
        @endif
        @if($vehicleServicesReady)
            @php($serviceSummary = $vehicle->service_summary)
            <p><strong>Service Interval:</strong> {{ $serviceSummary['interval_km'] ? number_format($serviceSummary['interval_km']) . ' km' : 'Not configured' }}</p>
            <p><strong>Service Status:</strong> <span class="pill {{ in_array($serviceSummary['state'], ['overdue','no-baseline','not-configured']) ? 'off' : '' }}">{{ $serviceSummary['label'] }}</span></p>
        @endif
        <p><strong>Status:</strong> <span class="pill {{ $vehicle->status === 'active' ? '' : 'off' }}">{{ ucfirst($vehicle->status) }}</span></p>
        @if($vehicle->notes)<p><strong>Notes:</strong> {{ $vehicle->notes }}</p>@endif
        <div class="actions">
            @if(auth()->user()->hasPermission('vehicle.edit'))<a class="btn primary" href="{{ route('vehicles.edit',$vehicle) }}">Edit Vehicle</a>@endif
            @if(\Illuminate\Support\Facades\Schema::hasColumn('vehicles','cartrack_vehicle_id') && auth()->user()->hasPermission('vehicle_tracking.sync'))<form method="post" action="{{ route('vehicles.tracking.sync',$vehicle) }}">@csrf<button class="btn" type="submit">Sync Tracking</button></form>@endif
            @if($vehicleServicesReady && auth()->user()->hasPermission('vehicle.service.manage'))<a class="btn" href="{{ route('vehicles.services.create',$vehicle) }}">Add Service</a>@endif
            @if(auth()->user()->hasPermission('vehicle.fuel.manage'))<a class="btn" href="{{ route('vehicles.fuel.create',$vehicle) }}">Add Fuel-up</a>@endif
            @if(auth()->user()->hasPermission('vehicle.fuel.import'))<a class="btn" href="{{ route('vehicles.fuel.import',$vehicle) }}">Import Fuel CSV</a>@endif
            @if(auth()->user()->hasPermission('vehicle.documents.upload'))<a class="btn" href="{{ route('vehicles.documents.create',$vehicle) }}">Upload Vehicle Document</a>@endif
            @if(auth()->user()->hasPermission('vehicle.edit') && $vehicle->status === 'active')
                <form method="post" action="{{ route('vehicles.destroy',$vehicle) }}" onsubmit="return confirm('Mark this vehicle inactive?')">@csrf @method('DELETE')<button class="btn danger" type="submit">Inactive</button></form>
            @endif
        </div>
    </div>
    <div class="card">
        <h2>Current Assignment</h2>
        @if($vehicle->currentAssignment)
            <p><strong>{{ $vehicle->currentAssignment->user->name }}</strong></p>
            <p class="muted">Assigned: {{ optional($vehicle->currentAssignment->assigned_at)->format('Y-m-d H:i') }}</p>
            @if($currentUserHasPolicy)
                <span class="pill">Vehicle Policy Valid</span>
            @else
                <div class="alert" style="background:rgba(245,185,76,.12);border-color:rgba(245,185,76,.35)">Outstanding document: this person does not have a valid active Vehicle Policy attached to their employee profile.</div>
            @endif
            @if(auth()->user()->hasPermission('vehicle.assign'))
                <form method="post" action="{{ route('vehicles.unassign',$vehicle) }}" onsubmit="return confirm('Unassign this vehicle?')">@csrf<button class="btn danger" type="submit">Unassign</button></form>
            @endif
        @else
            <p class="muted">Vehicle is currently unassigned.</p>
        @endif
    </div>
</div>
<div style="height:14px"></div>
@if(\Illuminate\Support\Facades\Schema::hasColumn('vehicles','cartrack_vehicle_id') && auth()->user()->hasPermission('vehicle_tracking.view'))
<div class="card">
    <div class="actions" style="justify-content:space-between">
        <div>
            <h2 style="margin-bottom:6px">Cartrack Tracking</h2>
            <p class="muted">Latest API sync data linked to this vehicle.</p>
        </div>
        <div class="actions">
            @if(auth()->user()->hasPermission('vehicle_tracking.sync'))
                <form method="post" action="{{ route('vehicles.tracking.sync',$vehicle) }}">@csrf<button class="btn primary" type="submit">Sync This Vehicle</button></form>
            @endif
            <a class="btn" href="{{ route('vehicle_tracking.index') }}">Tracking Dashboard</a>
        </div>
    </div>

    <div class="grid cols-4" style="margin-top:12px">
        <div class="card metric"><span>Tracking Status</span><strong style="font-size:24px">{{ $vehicle->tracking_last_status ?? 'No data' }}</strong></div>
        <div class="card metric"><span>Tracking ODO</span><strong>{{ $vehicle->tracking_last_odometer ? number_format($vehicle->tracking_last_odometer) : '-' }}</strong></div>
        <div class="card metric"><span>Speed</span><strong>{{ $vehicle->tracking_last_speed !== null ? $vehicle->tracking_last_speed : '-' }}</strong></div>
        <div class="card metric"><span>Last Sync</span><strong style="font-size:22px">{{ optional($vehicle->tracking_last_sync_at)->format('Y-m-d H:i') ?? '-' }}</strong></div>
    </div>

    <div class="kv-grid" style="margin-top:12px">
        <div class="kv"><span>Provider</span><strong>{{ $vehicle->tracking_provider ?? 'Not linked' }}</strong></div>
        <div class="kv"><span>Cartrack Vehicle ID</span><strong>{{ $vehicle->cartrack_vehicle_id ?? '-' }}</strong></div>
        <div class="kv"><span>Cartrack Registration</span><strong>{{ $vehicle->cartrack_registration ?? '-' }}</strong></div>
        <div class="kv"><span>Location</span><strong>{{ $vehicle->tracking_last_latitude && $vehicle->tracking_last_longitude ? $vehicle->tracking_last_latitude . ', ' . $vehicle->tracking_last_longitude : '-' }}</strong></div>
    </div>
    @if($vehicle->tracking_last_address)<p class="muted" style="margin-top:10px"><strong>Address:</strong> {{ $vehicle->tracking_last_address }}</p>@endif

    @if(($googleMaps['enabled'] ?? false) && $trackingHistory->count())
        <div class="soft-divider"></div>
        <div class="actions" style="justify-content:space-between">
            <div>
                <h3 style="margin-bottom:6px">Route History Map</h3>
                <p class="muted small">Shows stored tracking snapshots for this vehicle, joined as a route line in chronological order.</p>
            </div>
            <span class="pill">{{ $trackingHistory->count() }} GPS point(s)</span>
        </div>
        <div id="isoVehicleRouteMap" class="tracking-map route-map"></div>
    @elseif(($googleMaps['enabled'] ?? false) && !$trackingHistory->count())
        <div class="alert warning" style="margin-top:12px">Google Maps is configured, but no tracking GPS history exists for this vehicle yet. Run a tracking sync after linking the Cartrack vehicle.</div>
    @elseif(!($googleMaps['enabled'] ?? false))
        <div class="alert warning" style="margin-top:12px">Google Maps is not configured. The System Administrator can restore it under System Settings → Google API.</div>
    @endif

    @if(auth()->user()->hasPermission('vehicle_tracking.link'))
        <div class="soft-divider"></div>
        <h3>Manual Cartrack Link</h3>
        <form method="post" action="{{ route('vehicles.tracking.link',$vehicle) }}" class="form-grid">
            @csrf
            @method('PUT')
            <div><label>Cartrack Vehicle ID</label><input type="text" name="cartrack_vehicle_id" value="{{ old('cartrack_vehicle_id',$vehicle->cartrack_vehicle_id) }}"></div>
            <div><label>Cartrack Registration</label><input type="text" name="cartrack_registration" value="{{ old('cartrack_registration',$vehicle->cartrack_registration) }}"></div>
            <div><label>External Key / Note</label><input type="text" name="cartrack_external_key" value="{{ old('cartrack_external_key',$vehicle->cartrack_external_key) }}"></div>
            <div class="actions" style="align-self:end"><button class="btn" type="submit">Save Link</button></div>
        </form>
    @endif
</div>
<div style="height:14px"></div>
@endif
@if(auth()->user()->hasPermission('vehicle.assign'))
<div class="card">
    <h2>Assign Vehicle</h2>
    <p class="muted small">When a vehicle is assigned, the system checks whether the selected person has a valid active <strong>Vehicle Policy</strong> document. If not, a warning is shown to managers/directors.</p>
    <form method="post" action="{{ route('vehicles.assign',$vehicle) }}" class="form-grid">
        @csrf
        <div><label>Person</label><select name="user_id" required><option value="">Select employee/director</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->email }}</option>@endforeach</select></div>
        <div><label>Assigned Date</label><input type="datetime-local" name="assigned_at" value="{{ now()->format('Y-m-d\TH:i') }}"></div>
        <div style="grid-column:1/-1"><label>Notes</label><textarea name="notes"></textarea></div>
        <div class="actions"><button class="btn primary" type="submit">Assign</button></div>
    </form>
</div>
<div style="height:14px"></div>
@endif

@if($vehicleServicesReady && auth()->user()->hasPermission('vehicle.service.view'))
<div class="card">
    @php($serviceSummary = $vehicle->service_summary)
    <div class="actions" style="justify-content:space-between">
        <div>
            <h2 style="margin-bottom:6px">Service Tracking</h2>
            <p class="muted">Services are tracked from the latest fuel-up ODO. The next service is calculated from the last recorded service ODO plus the vehicle service interval.</p>
        </div>
        <div class="actions">
            @if(auth()->user()->hasPermission('vehicle.service.manage'))<a class="btn primary" href="{{ route('vehicles.services.create',$vehicle) }}">Add Service Record</a>@endif
            @if(auth()->user()->hasPermission('vehicle.service.reminders.view'))<a class="btn" href="{{ route('vehicles.service_reminders') }}">Service Reminders</a>@endif
        </div>
    </div>
    <div class="grid cols-4" style="margin-top:12px">
        <div class="card metric"><span>Last Service ODO</span><strong>{{ $serviceSummary['last_service_odo'] !== null ? number_format($serviceSummary['last_service_odo']) : '-' }}</strong></div>
        <div class="card metric"><span>Current ODO</span><strong>{{ $serviceSummary['current_odo'] !== null ? number_format($serviceSummary['current_odo']) : '-' }}</strong></div>
        <div class="card metric"><span>Next Service ODO</span><strong>{{ $serviceSummary['next_service_odo'] !== null ? number_format($serviceSummary['next_service_odo']) : '-' }}</strong></div>
        <div class="card metric"><span>KM Remaining</span><strong>{{ $serviceSummary['km_remaining'] !== null ? number_format($serviceSummary['km_remaining']) : '-' }}</strong></div>
    </div>
    <div class="table-wrap" style="margin-top:12px">
        <table>
            <thead><tr><th>Date</th><th>ODO</th><th>Next Service Snapshot</th><th>Recorded By</th><th>Notes</th></tr></thead>
            <tbody>
            @forelse($vehicle->serviceRecords as $service)
                <tr>
                    <td>{{ optional($service->service_date)->format('Y-m-d') }}</td>
                    <td>{{ number_format($service->service_odo) }}</td>
                    <td>{{ $service->next_service_odo_snapshot ? number_format($service->next_service_odo_snapshot) : '-' }}</td>
                    <td>{{ optional($service->recordedBy)->name ?? '-' }}</td>
                    <td><span class="muted small">{{ $service->notes }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No service records yet. Add the latest known service to create the service baseline.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div style="height:14px"></div>
@endif
@if(auth()->user()->hasPermission('vehicle.fuel.view'))
<div class="card">
    <div class="actions" style="justify-content:space-between">
        <div><h2 style="margin-bottom:6px">Fuel & Usage Records</h2><p class="muted">CSV and manual fuel-ups recorded for this vehicle.</p></div>
        <div class="actions">
            @if($vehicleServicesReady && auth()->user()->hasPermission('vehicle.service.manage'))<a class="btn" href="{{ route('vehicles.services.create',$vehicle) }}">Add Service</a>@endif
            @if(auth()->user()->hasPermission('vehicle.fuel.manage'))<a class="btn" href="{{ route('vehicles.fuel.create',$vehicle) }}">Add Fuel-up</a>@endif
            @if(auth()->user()->hasPermission('vehicle.fuel.import'))<a class="btn" href="{{ route('vehicles.fuel.import',$vehicle) }}">Import CSV</a>@endif
        </div>
    </div>
    <div class="table-wrap" style="margin-top:12px">
        <table>
            <thead><tr><th>Date</th><th>ODO</th><th>KM</th><th>Litres</th><th>KM/L</th><th>Price/L</th><th>Total</th><th>Notes</th></tr></thead>
            <tbody>
            @forelse($vehicle->fuelUps->take(40) as $fuel)
                <tr>
                    <td>{{ optional($fuel->fuelup_date)->format('Y-m-d') }}</td>
                    <td>{{ $fuel->odometer ? number_format($fuel->odometer) : '-' }}</td>
                    <td>{{ $fuel->km ?? '-' }}</td>
                    <td>{{ $fuel->litres ?? '-' }}</td>
                    <td>{{ $fuel->km_per_litre ?? '-' }}</td>
                    <td>{{ $fuel->price_per_litre ?? '-' }}</td>
                    <td>{{ $fuel->total_cost ?? '-' }}</td>
                    <td><span class="muted small">{{ $fuel->notes }}</span>@if($fuel->partial_fuelup)<br><span class="pill off">Partial</span>@endif @if($fuel->missed_fuelup)<span class="pill off">Missed</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No fuel records yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div style="height:14px"></div>
@endif
@if(auth()->user()->hasPermission('vehicle.documents.view'))
<div class="card">
    <div class="actions" style="justify-content:space-between">
        <div><h2 style="margin-bottom:6px">Vehicle Documents</h2><p class="muted">NATIS, license disk, insurance and expiry-controlled documents.</p></div>
        <div class="actions">@if(auth()->user()->hasPermission('vehicle.documents.upload'))<a class="btn primary" href="{{ route('vehicles.documents.create',$vehicle) }}">Upload</a>@endif <a class="btn" href="{{ route('vehicles.reminders') }}">Reminders</a></div>
    </div>
    <div class="table-wrap" style="margin-top:12px">
        <table>
            <thead><tr><th>Document</th><th>Type</th><th>Expiry</th><th>Reminder</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($vehicle->documents as $document)
                <tr>
                    <td><strong>{{ $document->title }}</strong><br><span class="muted small">{{ $document->original_filename }} · {{ $document->file_size_label }}</span>@if($document->notes)<br><span class="muted small">{{ $document->notes }}</span>@endif</td>
                    <td><span class="pill">{{ $document->type_label }}</span></td>
                    <td>{{ $document->has_expiry ? optional($document->expires_at)->format('Y-m-d') : 'No expiry' }}</td>
                    <td>@if($document->has_expiry){{ optional($document->reminder_date)->format('Y-m-d') }}<br><span class="muted small">{{ $document->remind_days_before }} days before</span>@else<span class="muted">Not required</span>@endif</td>
                    <td><span class="pill {{ in_array($document->expiry_state, ['expired','inactive']) ? 'off' : '' }}">{{ str_replace('-', ' ', ucfirst($document->expiry_state)) }}</span></td>
                    <td><div class="actions"><a class="btn" href="{{ route('vehicles.documents.download',$document) }}">Download</a>@if($document->status === 'active' && auth()->user()->hasPermission('vehicle.documents.manage'))<form method="post" action="{{ route('vehicles.documents.inactive',$document) }}" onsubmit="return confirm('Mark this vehicle document inactive?')">@csrf @method('PATCH')<button class="btn danger" type="submit">Inactive</button></form>@endif</div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No vehicle documents uploaded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div style="height:14px"></div>
@endif
<div class="card">
    <h2>Assignment History</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Person</th><th>Assigned</th><th>Unassigned</th><th>Policy Warning</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($vehicle->assignments as $assignment)
                <tr><td>{{ optional($assignment->user)->name ?? 'Unknown' }}</td><td>{{ optional($assignment->assigned_at)->format('Y-m-d H:i') }}</td><td>{{ optional($assignment->unassigned_at)->format('Y-m-d H:i') ?? '-' }}</td><td>{{ $assignment->policy_warning ? 'Yes' : 'No' }}</td><td><span class="pill {{ $assignment->status === 'active' ? '' : 'off' }}">{{ ucfirst($assignment->status) }}</span></td></tr>
            @empty
                <tr><td colspan="5" class="muted">No assignment history yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if(($googleMaps['enabled'] ?? false) && auth()->user()->hasPermission('vehicle_tracking.view') && $trackingHistory->count())
<style>
    .tracking-map{height:520px;min-height:55vh;border-radius:20px;border:1px solid var(--line);overflow:hidden;background:#e9edf1;box-shadow:var(--shadow-soft);margin-top:12px}
    @@media(max-width:720px){.tracking-map{height:430px;min-height:50vh}}
</style>
<script>
    window.isoVehicleRoutePoints = {!! json_encode($trackingHistory ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    window.isoVehicleMapPoint = {!! json_encode($vehicleMapPoint ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    window.isoVehicleMapConfig = {!! json_encode($isoVehicleMapConfig ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

    window.isoVehicleEscapeHtml = function(value) {
        return String(value ?? '').replace(/[&<>'"]/g, function (char) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char];
        });
    };

    window.isoRoutePointInfo = function(point, fallbackName) {
        return '<div style="color:#071118;max-width:280px">'
            + '<strong>' + window.isoVehicleEscapeHtml(fallbackName || 'Vehicle') + '</strong><br>'
            + '<span>Recorded: ' + window.isoVehicleEscapeHtml(point.recorded_at || '-') + '</span><br>'
            + '<span>Speed: ' + (point.speed ?? '-') + '</span><br>'
            + '<span>ODO: ' + (point.odometer ?? '-') + '</span><br>'
            + '<span>Status: ' + window.isoVehicleEscapeHtml(point.status || '-') + '</span><br>'
            + (point.address ? '<br><span>' + window.isoVehicleEscapeHtml(point.address) + '</span>' : '')
            + '</div>';
    };

    window.initIsoVehicleRouteMap = function() {
        var element = document.getElementById('isoVehicleRouteMap');
        if (!element || !window.google || !window.google.maps) return;

        var points = (window.isoVehicleRoutePoints || []).filter(function (point) {
            return isFinite(Number(point.latitude)) && isFinite(Number(point.longitude));
        });
        var vehicle = window.isoVehicleMapPoint || {};
        var config = window.isoVehicleMapConfig || {};
        var defaultCenter = points.length ? {lat: Number(points[points.length - 1].latitude), lng: Number(points[points.length - 1].longitude)} : {lat: Number(config.default_latitude || -26.204103), lng: Number(config.default_longitude || 28.047305)};
        var options = {center: defaultCenter, zoom: Number(config.default_zoom || 12), mapTypeControl: false, streetViewControl: false};
        if (config.map_id) options.mapId = config.map_id;

        var map = new google.maps.Map(element, options);
        var infoWindow = new google.maps.InfoWindow();
        var bounds = new google.maps.LatLngBounds();
        var path = [];
        var useAdvancedMarkers = !!(config.map_id && google.maps.marker && google.maps.marker.AdvancedMarkerElement);

        points.forEach(function (point, index) {
            var position = {lat: Number(point.latitude), lng: Number(point.longitude)};
            path.push(position);
            bounds.extend(position);

            var isLast = index === points.length - 1;
            if (index === 0 || isLast || points.length <= 10) {
                var title = (isLast ? 'Latest position' : (index === 0 ? 'First position' : 'Route point'));
                var marker = useAdvancedMarkers
                    ? new google.maps.marker.AdvancedMarkerElement({map: map, position: position, title: title})
                    : new google.maps.Marker({map: map, position: position, title: title});
                marker.addListener('click', function () {
                    infoWindow.setContent(window.isoRoutePointInfo(point, vehicle.name));
                    infoWindow.open({map: map, anchor: marker});
                });
            }
        });

        if (path.length > 1) {
            new google.maps.Polyline({path: path, geodesic: true, strokeOpacity: 0.9, strokeWeight: 4, map: map});
        }

        if (!bounds.isEmpty()) {
            map.fitBounds(bounds, 64);
        }
    };
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ rawurlencode($googleMaps['api_key'] ?? '') }}&libraries=marker&callback=initIsoVehicleRouteMap"></script>
@endif

@endsection
