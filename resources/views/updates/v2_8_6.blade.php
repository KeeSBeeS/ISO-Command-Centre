@extends('layouts.app')
@section('title','Update v2.8.6 | ISO Admin')
@section('page_title','Version 2.8.6 Update')
@section('content')
<div class="card">
    <h2>Customer CRM Update</h2>
    <p class="muted">Upgrades the Customers module into a full CRM: sites/locations per customer, company and site-level contacts, and an interaction/activity log with follow-up dates. Adds customer type, industry, website and account manager fields. Adds the <code>customer_sites.manage</code>, <code>customer_contacts.manage</code> and <code>customer_interactions.manage</code> permissions.</p>
    <div class="grid cols-2" style="margin-top:16px">
        <div class="metric-box"><span>Current Version</span><strong style="font-size:18px">{{ $systemVersion ?? 'Unknown' }}</strong></div>
        <div class="metric-box"><span>Customer CRM Tables</span><strong style="font-size:18px">{{ $customerCrmReady ? 'Ready' : 'Pending' }}</strong></div>
    </div>
    <form method="post" action="{{ route('updates.v2_8_6.apply') }}" style="margin-top:18px">
        @csrf
        <button class="btn primary" type="submit">Apply v2.8.6 Update</button>
    </form>
</div>
@endsection
