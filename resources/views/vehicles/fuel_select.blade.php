@extends('layouts.app')
@section('title','Select Vehicle for Fuel-up | ISO Admin')
@section('page_title','Add Fuel-up')
@section('content')
<style>
    .fuel-select-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.fuel-card{border:1px solid var(--line);border-radius:18px;padding:16px;background:rgba(255,255,255,.045);box-shadow:var(--shadow-soft)}.fuel-card h3{margin:0 0 6px}.fuel-meta{display:grid;gap:6px;margin:12px 0}.fuel-meta span{display:flex;justify-content:space-between;gap:10px;color:var(--muted);font-size:13px}.fuel-meta strong{color:var(--text);font-weight:800}@media(max-width:980px){.fuel-select-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:640px){.fuel-select-grid{grid-template-columns:1fr}}
</style>

<div class="card">
    <div class="actions" style="justify-content:space-between">
        <div>
            <h2 style="margin-bottom:6px">Select Vehicle</h2>
            <p class="muted">Choose the vehicle first, then capture the fuel-up against that vehicle.</p>
        </div>
        <div class="actions"><a class="btn" href="{{ route('vehicles.index') }}">Back to Vehicle Dashboard</a></div>
    </div>
    <form method="get" class="form-grid" style="margin-top:14px">
        <div><label>Search</label><input type="text" name="search" value="{{ request('search') }}" placeholder="Make, model, registration or vehicle key"></div>
        <div class="actions"><button class="btn" type="submit">Search</button><a class="btn" href="{{ route('vehicles.fuel.select') }}">Reset</a></div>
    </form>
</div>
<div style="height:14px"></div>

<div class="fuel-select-grid">
    @forelse($vehicles as $vehicle)
        <div class="fuel-card">
            <h3>{{ $vehicle->display_name }}</h3>
            <p class="muted small">{{ $vehicle->registration_number ?: $vehicle->cartrack_registration ?: 'No registration captured' }}</p>
            <div class="fuel-meta">
                <span>Assigned to <strong>{{ optional(optional($vehicle->currentAssignment)->user)->name ?? 'Unassigned' }}</strong></span>
                <span>Current ODO <strong>{{ number_format($vehicle->latest_odometer ?? $vehicle->odo ?? 0) }}</strong></span>
                <span>Fuel records <strong>{{ $vehicle->fuel_ups_count ?? 0 }}</strong></span>
                <span>Last fuel-up <strong>{{ optional(optional($vehicle->latestFuelUp)->fuelup_date)->format('Y-m-d') ?? 'None' }}</strong></span>
            </div>
            <div class="actions">
                <a class="btn primary" href="{{ route('vehicles.fuel.create', $vehicle) }}">⛽ Add Fuel-up</a>
                <a class="btn" href="{{ route('vehicles.show', $vehicle) }}">Open Vehicle</a>
            </div>
        </div>
    @empty
        <div class="card"><p class="muted">No active vehicles found.</p></div>
    @endforelse
</div>
<div class="pagination">{{ $vehicles->links() }}</div>
@endsection
