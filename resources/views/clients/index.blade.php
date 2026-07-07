@extends('layouts.app')
@section('title','Clients | ISO Admin')
@section('page_title','Clients')
@section('content_class','content-wide')
@section('content')
<div class="card crm-hero">
    <div>
        <h2>🏢 Clients</h2>
        <p class="muted">Manage clients, client-level contacts, operational sites, site contacts and distance from office.</p>
    </div>
    <div class="actions right">@if(auth()->user()->hasPermission('clients.create'))<a class="btn primary" href="{{ route('clients.create') }}">➕ Add Client</a>@endif</div>
</div>

<div class="card" style="margin-top:16px">
    <form method="get" class="crm-filter">
        <div class="form-row"><label>🔎 Search</label><input name="search" value="{{ $search }}" placeholder="Client, industry, code or email"></div>
        <div class="form-row"><label>✅ Status</label><select name="status"><option value="">All</option>@foreach(['active'=>'Active','prospect'=>'Prospect','inactive'=>'Inactive'] as $value=>$label)<option value="{{ $value }}" @selected($status===$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="form-row"><label>&nbsp;</label><button class="btn" type="submit">🔎 Filter</button></div>
    </form>
</div>

<div class="crm-grid" style="margin-top:16px">
@forelse($clients as $client)
    <a class="card crm-card" href="{{ route('clients.show',$client) }}">
        <div class="crm-card-head"><div class="crm-icon">{{ $client->status_icon }}</div><div><h3>{{ $client->name }}</h3><p class="muted">{{ ucfirst($client->client_type) }} · {{ $client->industry ?: 'No industry' }}</p></div></div>
        <div class="crm-metrics">
            <div><span>Nearest Distance</span><strong>{{ $client->display_distance }}</strong></div>
            <div><span>Sites</span><strong>{{ $client->sites_count }}</strong></div>
            <div><span>Contacts</span><strong>{{ $client->contacts_count }}</strong></div>
        </div>
        <div class="actions"><span class="pill {{ $client->status === 'inactive' ? 'off' : '' }}">{{ $client->status_icon }} {{ ucfirst($client->status) }}</span><span class="pill off">👤 {{ optional($client->accountManager)->name ?? 'No manager' }}</span></div>
    </a>
@empty
    <div class="card"><p class="muted">No clients found.</p></div>
@endforelse
</div>
<div class="pagination">{{ $clients->links() }}</div>
<style>
.crm-hero{display:grid;grid-template-columns:1fr auto;gap:16px;align-items:center}.crm-filter{display:grid;grid-template-columns:1fr 220px auto;gap:14px;align-items:end}.crm-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.crm-card{display:grid;gap:16px;transition:transform .15s ease,border-color .15s ease}.crm-card:hover{transform:translateY(-2px);border-color:rgba(139,220,101,.35)}.crm-card-head{display:grid;grid-template-columns:auto 1fr;gap:12px;align-items:center}.crm-icon{width:50px;height:50px;border-radius:17px;background:rgba(139,220,101,.12);display:grid;place-items:center;font-size:25px}.crm-card h3{margin:0 0 4px}.crm-metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.crm-metrics div{border:1px solid var(--line);border-radius:15px;padding:10px;background:rgba(255,255,255,.035)}.crm-metrics span{display:block;color:var(--muted);font-size:12px}.crm-metrics strong{display:block;margin-top:4px}@media(min-width:1500px){.crm-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}@media(max-width:1100px){.crm-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.crm-filter{grid-template-columns:1fr 1fr}}@media(max-width:720px){.crm-hero,.crm-filter,.crm-grid{grid-template-columns:1fr}.crm-hero .actions.right{justify-content:flex-start}.crm-metrics{grid-template-columns:1fr}.crm-filter .btn{width:100%}}
</style>
@endsection
