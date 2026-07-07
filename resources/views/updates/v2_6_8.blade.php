@extends('layouts.app')
@section('title','Update v2.6.8 | ISO Admin')
@section('page_title','Update v2.6.8')
@section('content')
<div class="card">
    <h2>Version 2.6.8 Vehicle Map Blade Repair</h2>
    <p class="muted">This repair fixes the vehicle index page parse error and the specific vehicle page undefined map config variable.</p>

    <div class="alert success">
        Current installed version: <strong>{{ $systemVersion ?? 'Unknown' }}</strong><br>
        Package version available: <strong>2.6.8</strong>
    </div>

    <ul class="muted">
        <li>Moves Google map config generation into the VehicleController.</li>
        <li>Removes fragile Blade-side PHP array generation from vehicle map scripts.</li>
        <li>Escapes CSS media directives correctly inside Blade templates.</li>
        <li>Ensures System Administrator retains all permissions.</li>
    </ul>

    <form method="post" action="{{ route('updates.v2_6_8.apply') }}" onsubmit="return confirm('Apply update v2.6.8?')">
        @csrf
        <button class="btn primary" type="submit">Apply v2.6.8</button>
        <a class="btn" href="{{ route('vehicles.index') }}">Back to Vehicles</a>
    </form>
</div>
@endsection
