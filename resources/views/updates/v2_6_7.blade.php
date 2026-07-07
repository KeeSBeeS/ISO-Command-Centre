@extends('layouts.app')

@section('title','Update v2.6.7')
@section('page_title','Update v2.6.7')
@section('page_icon','🛠️')

@section('content')
<div class="card">
    <h2>ISO Admin Command Framework v2.6.7</h2>
    <p class="muted">This repair update fixes the specific vehicle page Blade parse error introduced by the Google Maps route/history script.</p>

    <div class="grid two" style="margin-top:16px">
        <div class="mini-card">
            <strong>Current Version</strong>
            <span>{{ $systemVersion ? 'v'.$systemVersion : 'Unknown' }}</span>
        </div>
        <div class="mini-card">
            <strong>Fix</strong>
            <span>Vehicle profile map JSON output repaired</span>
        </div>
    </div>

    <div class="alert warning" style="margin-top:16px">
        This update has no database schema changes. It updates the platform version and re-syncs System Administrator permissions.
    </div>

    <form method="post" action="{{ route('updates.v2_6_7.apply') }}" style="margin-top:18px">
        @csrf
        <button class="btn primary" type="submit">Apply v2.6.7 Repair</button>
        <a class="btn" href="{{ route('vehicles.index') }}">Back to Vehicles</a>
    </form>
</div>
@endsection
