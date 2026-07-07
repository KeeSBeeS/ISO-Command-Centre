@extends('layouts.app')
@section('title','Version 1.8.2 Responsive Vehicle Layout | ISO Admin')
@section('page_title','Version 1.8.2 Responsive Vehicle Layout')
@section('content')
<div class="card">
    <h2>Vehicle View Responsive + Wide Screen Repair</h2>
    <p class="muted">This update fixes the vehicle profile so it uses the available desktop width properly, while still stacking cleanly on mobile.</p>
    <div class="grid cols-2">
        <div class="card metric"><span>Wide Vehicle Page Shell</span><strong>{{ $wideLayoutReady ? 'Ready' : 'Check' }}</strong></div>
        <div class="card metric"><span>Mobile-first Stacking</span><strong>Ready</strong></div>
    </div>
    <form method="post" action="{{ route('updates.v1_8_2.apply') }}" style="margin-top:16px">
        @csrf
        <button class="btn primary" type="submit">Apply Version 1.8.2 Repair</button>
    </form>
</div>
<div style="height:14px"></div>
<div class="card">
    <h2>What This Fix Changes</h2>
    <ul class="muted">
        <li>Vehicle profile now uses a wider content shell on desktop screens.</li>
        <li>Summary, assignment and analytics can sit in a 3-column layout on wide screens.</li>
        <li>Graphs and stat cards use responsive grids instead of fixed narrow columns.</li>
        <li>Mobile view remains single-column with full-width action buttons.</li>
        <li>Laravel route/view/cache files are cleared where shared hosting allows it.</li>
    </ul>
</div>
@endsection
