@extends('layouts.app')
@section('title','Import Fuel CSV | ISO Admin')
@section('page_title','Import Fuel CSV')
@section('content')
<div class="card">
    <h2>{{ $vehicle->display_name }}</h2>
    <p class="muted">Upload the fuel export CSV for this vehicle. Duplicate rows are ignored using vehicle, date, odometer, litres and price.</p>
    <p class="muted small">Expected CSV columns include: car_name, model, km/l, odometer, km, litres, price, fuelup_date, date_added, notes, missed_fuelup, partial_fuelup, latitude, longitude and brand.</p>
</div>
<div style="height:14px"></div>
<form method="post" action="{{ route('vehicles.fuel.import.store',$vehicle) }}" enctype="multipart/form-data">
    @csrf
    <div class="card">
        <div class="form-row"><label>Fuel CSV File</label><input type="file" name="fuel_csv" accept=".csv,text/csv,text/plain" required></div>
        <div class="actions"><button class="btn primary" type="submit">Import CSV</button><a class="btn" href="{{ route('vehicles.show',$vehicle) }}">Cancel</a></div>
    </div>
</form>
@endsection
