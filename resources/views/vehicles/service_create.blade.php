@extends('layouts.app')
@section('title','Add Vehicle Service | ISO Admin')
@section('page_title','Add Vehicle Service')
@section('content')
<div class="card">
    <h2>{{ $vehicle->display_name }}</h2>
    <p class="muted">Current ODO from latest fuel-up: <strong>{{ $latestOdometer !== null ? number_format($latestOdometer) : 'Not available' }}</strong></p>
    <p class="muted">Interval: {{ $serviceSummary['interval_km'] ? number_format($serviceSummary['interval_km']) . ' km' : 'Not configured' }} · Reminder: {{ number_format((int)$serviceSummary['reminder_km']) }} km before due</p>
</div>
<div style="height:14px"></div>
<form method="post" action="{{ route('vehicles.services.store',$vehicle) }}">
    @csrf
    <div class="grid cols-2">
        <div class="card">
            <h2>Service Record</h2>
            <div class="form-row"><label>Service Date</label><input type="date" name="service_date" value="{{ old('service_date', now()->toDateString()) }}" required></div>
            <div class="form-row"><label>Service ODO</label><input type="number" name="service_odo" min="0" value="{{ old('service_odo', $latestOdometer ?? $vehicle->odo ?? 0) }}" required></div>
        </div>
        <div class="card">
            <h2>Notes</h2>
            <div class="form-row"><label>Service Notes</label><textarea name="notes" placeholder="Oil, filters, inspection notes, defects, supplier, invoice reference...">{{ old('notes') }}</textarea></div>
        </div>
    </div>
    <div style="height:14px"></div>
    <div class="actions"><button class="btn primary" type="submit">Save Service Record</button><a class="btn" href="{{ route('vehicles.show',$vehicle) }}">Cancel</a></div>
</form>
@endsection
