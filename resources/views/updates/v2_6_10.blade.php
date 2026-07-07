@extends('layouts.app')
@section('title','Update v2.6.10 | ISO Admin')
@section('page_title','Version 2.6.10 Update')
@section('content')
<div class="card">
    <h2>Customers Menu Update</h2>
    <p class="muted">Adds the Customers module to the left menu, creates the customers table, and updates the existing Clients permissions to control customer access.</p>
    <div class="grid cols-2" style="margin-top:16px">
        <div class="metric-box"><span>Current Version</span><strong style="font-size:18px">{{ $systemVersion ?? 'Unknown' }}</strong></div>
        <div class="metric-box"><span>Customers Table</span><strong style="font-size:18px">{{ $customersReady ? 'Ready' : 'Pending' }}</strong></div>
    </div>
    <form method="post" action="{{ route('updates.v2_6_10.apply') }}" style="margin-top:18px">
        @csrf
        <button class="btn primary" type="submit">Apply v2.6.10 Update</button>
    </form>
</div>
@endsection
