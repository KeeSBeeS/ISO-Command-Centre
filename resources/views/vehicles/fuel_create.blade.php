@extends('layouts.app')
@section('title','Add Fuel-up | ISO Admin')
@section('page_title','Add Fuel-up')
@section('content')
<div class="card">
    <h2>{{ $vehicle->display_name }}</h2>
    <p class="muted">Manual fuel-up entry. Enter the latest odometer only; the system calculates KM travelled from the previous lower odometer reading.</p>
    <p class="muted small">Previous ODO used for calculation: <strong>{{ $previousOdometer !== null ? number_format($previousOdometer) : 'Not available yet' }}</strong></p>
</div>
<div style="height:14px"></div>
<form method="post" action="{{ route('vehicles.fuel.store',$vehicle) }}">
    @csrf
    <div class="grid cols-2">
        <div class="card">
            <h2>Fuel Details</h2>
            <div class="form-row"><label>Fuel-up Date</label><input type="date" name="fuelup_date" value="{{ old('fuelup_date',now()->toDateString()) }}" required></div>
            <div class="form-row"><label>Latest Odometer</label><input id="fuel-odo" type="number" name="odometer" min="0" value="{{ old('odometer',$vehicle->latest_odometer ?? $vehicle->odo) }}" data-previous="{{ $previousOdometer }}" required></div>
            <div class="form-row"><label>Auto KM Travelled</label><input id="fuel-km-preview" type="text" value="Auto-calculated after ODO entry" readonly></div>
            <div class="form-row"><label>Litres</label><input id="fuel-litres" type="number" step="0.01" name="litres" value="{{ old('litres') }}" required></div>
        </div>
        <div class="card">
            <h2>Cost & Notes</h2>
            <div class="form-row"><label>Price Per Litre</label><input id="fuel-price" type="number" step="0.01" name="price_per_litre" value="{{ old('price_per_litre') }}"></div>
            <div class="form-row"><label>Total Cost</label><input id="fuel-total" type="number" step="0.01" name="total_cost" value="{{ old('total_cost') }}" placeholder="Auto-calculated if price/litre is entered"></div>
            <div class="form-row"><label>Auto KM/L Preview</label><input id="fuel-kml-preview" type="text" value="Auto-calculated after save" readonly></div>
            <div class="form-row"><label>Fuel Brand</label><input type="text" name="brand" value="{{ old('brand') }}"></div>
            <label class="check"><input type="checkbox" name="partial_fuelup" value="1" @checked(old('partial_fuelup'))><span>Partial fuel-up</span></label>
            <label class="check" style="margin-top:8px"><input type="checkbox" name="missed_fuelup" value="1" @checked(old('missed_fuelup'))><span>Missed previous fuel-up</span></label>
        </div>
    </div>
    <div style="height:14px"></div>
    <div class="card"><label>Notes</label><textarea name="notes">{{ old('notes') }}</textarea></div>
    <div style="height:14px"></div>
    <div class="actions"><button class="btn primary" type="submit">Save Fuel-up</button><a class="btn" href="{{ route('vehicles.show',$vehicle) }}">Cancel</a></div>
</form>
<script>
(function(){
    var odo = document.getElementById('fuel-odo');
    var litres = document.getElementById('fuel-litres');
    var price = document.getElementById('fuel-price');
    var total = document.getElementById('fuel-total');
    var kmPreview = document.getElementById('fuel-km-preview');
    var kmlPreview = document.getElementById('fuel-kml-preview');
    if (!odo) return;

    function money(n){ return Math.round(n * 100) / 100; }
    function recalc(){
        var previous = Number(odo.dataset.previous || 0);
        var current = Number(odo.value || 0);
        var fuelLitres = Number(litres.value || 0);
        var fuelPrice = Number(price.value || 0);
        var km = previous > 0 && current > previous ? current - previous : null;

        kmPreview.value = km === null ? 'Will calculate after a previous lower ODO exists' : km.toLocaleString() + ' km';
        kmlPreview.value = km && fuelLitres ? (Math.round((km / fuelLitres) * 100) / 100) + ' km/l' : 'Auto-calculated after save';

        if (fuelLitres && fuelPrice && !total.dataset.userEdited) {
            total.value = money(fuelLitres * fuelPrice);
        }
    }

    [odo, litres, price].forEach(function(el){ el && el.addEventListener('input', recalc); });
    total && total.addEventListener('input', function(){ total.dataset.userEdited = '1'; });
    recalc();
})();
</script>
@endsection
