@extends('layouts.app')
@section('title','Customers | ISO Admin')
@section('page_title','Customers')
@section('content')
<div class="card customer-index-hero">
    <div>
        <h2>🤝 Customers</h2>
        <p class="muted">Manage customers, sites/locations, contacts and interactions.</p>
    </div>
    @if(auth()->user()->hasPermission('clients.manage'))
        <div class="actions right"><a class="btn primary" href="{{ route('customers.create') }}">➕ Add Customer</a></div>
    @endif
</div>

<div class="grid cols-4" style="margin-top:16px">
    <div class="card metric"><span>Total Customers</span><strong>{{ $stats['total'] }}</strong></div>
    <div class="card metric"><span>Active</span><strong>{{ $stats['active'] }}</strong></div>
    <div class="card metric"><span>Prospects</span><strong>{{ $stats['prospects'] }}</strong></div>
    <div class="card metric"><span>Inactive</span><strong>{{ $stats['inactive'] }}</strong></div>
</div>

<div class="card" style="margin-top:16px">
    <form method="get" class="customer-filter">
        <div class="form-row"><label>🔎 Search</label><input type="text" name="search" value="{{ $search }}" placeholder="Company, contact, industry, email or phone"></div>
        <div class="form-row"><label>📌 Type</label><select name="type">
            <option value="">All types</option>
            @foreach(['customer'=>'Customer','prospect'=>'Prospect','supplier'=>'Supplier','partner'=>'Partner','other'=>'Other'] as $value=>$label)
                <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
            @endforeach
        </select></div>
        <div class="form-row"><label>✅ Status</label><select name="status">
            <option value="">All statuses</option>
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
        </select></div>
        <div class="form-row"><label>&nbsp;</label><button class="btn" type="submit">🔎 Filter</button></div>
    </form>
    @if($search || $status || $type)<a class="btn" href="{{ route('customers.index') }}" style="margin-top:10px">Clear filters</a>@endif
</div>

<div class="customer-grid" style="margin-top:16px">
@forelse($customers as $customer)
    <a class="card customer-card" href="{{ route('customers.show', $customer) }}">
        <div class="customer-card-head">
            <div class="customer-icon">{{ $customer->type_icon }}</div>
            <div>
                <h3>{{ $customer->company_name }}</h3>
                <p class="muted">{{ ucfirst($customer->customer_type ?: 'customer') }} · {{ $customer->industry ?: 'No industry' }}</p>
            </div>
        </div>
        <div class="customer-metrics">
            <div><span>Sites</span><strong>{{ $customer->sites_count }}</strong></div>
            <div><span>Contacts</span><strong>{{ $customer->contacts_count }}</strong></div>
            <div><span>Code</span><strong>{{ $customer->customer_code ?: '—' }}</strong></div>
        </div>
        <div class="actions">
            <span class="pill {{ $customer->status === 'active' ? '' : 'off' }}">{{ ucfirst($customer->status) }}</span>
            <span class="pill off">👤 {{ optional($customer->accountManager)->name ?? 'No manager' }}</span>
        </div>
    </a>
@empty
    <div class="card"><p class="muted">No customers found.</p></div>
@endforelse
</div>
<div class="pagination">{{ $customers->links() }}</div>

<style>
.customer-index-hero{display:grid;grid-template-columns:1fr auto;gap:16px;align-items:center}
.customer-filter{display:grid;grid-template-columns:1fr 200px 200px auto;gap:14px;align-items:end}
.customer-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
.customer-card{display:grid;gap:16px;transition:transform .15s ease,border-color .15s ease}
.customer-card:hover{transform:translateY(-2px);border-color:rgba(56,193,114,.35)}
.customer-card-head{display:grid;grid-template-columns:auto 1fr;gap:12px;align-items:center}
.customer-icon{width:50px;height:50px;border-radius:17px;background:rgba(14,157,104,.12);display:grid;place-items:center;font-size:25px}
.customer-card h3{margin:0 0 4px}
.customer-metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.customer-metrics div{border:1px solid var(--line);border-radius:15px;padding:10px;background:rgba(15,23,42,.03)}
.customer-metrics span{display:block;color:var(--muted);font-size:12px}
.customer-metrics strong{display:block;margin-top:4px;word-break:break-word}
@media(min-width:1500px){.customer-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:1100px){.customer-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.customer-filter{grid-template-columns:1fr 1fr}}
@media(max-width:720px){.customer-index-hero,.customer-filter,.customer-grid{grid-template-columns:1fr}.customer-index-hero .actions.right{justify-content:flex-start}.customer-metrics{grid-template-columns:1fr}.customer-filter .btn{width:100%}}
</style>
@endsection
