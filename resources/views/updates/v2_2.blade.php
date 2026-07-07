@extends('layouts.app')
@section('title','Version 2.2 Update | ISO Admin')
@section('page_title','Version 2.2 Update')
@section('content')
<div class="card">
    <h2>Version 2.2: CRM Clients & Leave Removal</h2>
    <p class="muted">This update gives directors the ability to remove leave entries and adds a CRM-style client section with clients, sites, contacts and distance from office.</p>

    <div class="grid cols-2" style="margin:16px 0">
        <div class="card metric"><span>CRM tables ready</span><strong>{{ $crmInstalled ? 'Yes' : 'No' }}</strong></div>
        <div class="card metric"><span>New permissions installed</span><strong>{{ $permissionCount }}/7</strong></div>
    </div>

    <form method="post" action="{{ route('updates.v2_2.apply') }}">
        @csrf
        <button class="btn primary" type="submit">Apply Version 2.2 Update</button>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <h2>Included</h2>
    <ul class="muted" style="line-height:1.8">
        <li>Director-only remove leave option on employee profiles.</li>
        <li>Removed leave no longer appears on the active calendar.</li>
        <li>CRM client list and client profile screens.</li>
        <li>Client fields: name, code, type, industry, status, account manager, distance from office, phone, email, website, address and notes.</li>
        <li>Client sites with location and distance from office.</li>
        <li>Client contacts with primary-contact option.</li>
        <li>Updated permission matrix for CRM and leave removal.</li>
    </ul>
</div>
@endsection
