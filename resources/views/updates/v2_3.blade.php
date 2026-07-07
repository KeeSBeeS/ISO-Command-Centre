@extends('layouts.app')
@section('title','Version 2.3 Update | ISO Admin')
@section('page_title','Version 2.3 Update')
@section('content_class','content-wide')
@section('content')
<div class="card">
    <h2>Version 2.3: Clients + Site Contacts + Google Maps Distance</h2>
    <p class="muted">This update renames CRM Clients to Clients, adds contact roles, adds site-specific contacts, adds office address settings, and prepares Google Maps distance calculation from the office to client sites.</p>

    <div class="grid cols-4" style="margin:16px 0">
        <div class="card metric"><span>Client tables</span><strong>{{ $clientsReady ? 'Yes' : 'No' }}</strong></div>
        <div class="card metric"><span>Site contacts</span><strong>{{ $siteContactsReady ? 'Yes' : 'No' }}</strong></div>
        <div class="card metric"><span>Maps columns</span><strong>{{ $mapsReady ? 'Yes' : 'No' }}</strong></div>
        <div class="card metric"><span>Google Maps API</span><strong>{{ $googleMapsConfigured ? 'Set' : 'Missing' }}</strong></div>
    </div>

    <form method="post" action="{{ route('updates.v2_3.apply') }}">
        @csrf
        <button class="btn primary" type="submit">Apply Version 2.3 Update</button>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <h2>After update</h2>
    <ul class="muted" style="line-height:1.8">
        <li>Open <strong>Core Settings</strong> and add the office address.</li>
        <li>Add a Google Maps API key if you want automatic distance calculation.</li>
        <li>Client-level contacts can be Engineer, Foreman, Stock Controller, Accounts, Procurement and more.</li>
        <li>Each client site has its own location and its own contact people.</li>
        <li>If no API key is added, the site page still gives a Google Maps directions link.</li>
    </ul>
</div>
@endsection
