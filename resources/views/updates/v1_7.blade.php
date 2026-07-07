@extends('layouts.app')
@section('title','Update v1.7 | ISO Admin')
@section('page_title','Version 1.7 Update')
@section('content')
<div class="card">
    <h2>Vehicle Service Tracking</h2>
    <p class="muted">This update adds kilometre-based service intervals, service records, and upcoming-service reminders based on the latest fuel-up ODO.</p>
    <ul class="muted">
        <li>Service interval KM and reminder KM added to each vehicle.</li>
        <li>Managers/directors can record when a vehicle was serviced, at what ODO, with notes.</li>
        <li>Next service ODO is calculated from last service ODO + interval KM.</li>
        <li>Latest fuel-up ODO drives due-soon and overdue service reminders.</li>
        <li>Permission matrix updated for vehicle service tracking.</li>
    </ul>
    <p><strong>Status:</strong> {{ $serviceInstalled ? 'Installed' : 'Not installed yet' }}</p>
    <p><strong>Service permissions:</strong> {{ $permissionCount }} / 3</p>
    <form method="post" action="{{ route('updates.v1_7.apply') }}">
        @csrf
        <button class="btn primary" type="submit">Apply v1.7 Update</button>
    </form>
</div>
@endsection
