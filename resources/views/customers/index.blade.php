@extends('layouts.app')
@section('title','Customers | ISO Admin')
@section('page_title','Customers')
@section('content')
<div class="grid cols-4" style="margin-bottom:16px">
    <div class="card metric"><span>Total Customers</span><strong>{{ $stats['total'] }}</strong></div>
    <div class="card metric"><span>Active</span><strong>{{ $stats['active'] }}</strong></div>
    <div class="card metric"><span>Prospects</span><strong>{{ $stats['prospects'] }}</strong></div>
    <div class="card metric"><span>Inactive</span><strong>{{ $stats['inactive'] }}</strong></div>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="actions" style="justify-content:space-between;gap:14px">
        <form method="get" class="actions" style="flex:1;flex-wrap:wrap">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search company, contact, industry, email or phone" style="min-width:260px;flex:1">
            <select name="type" style="max-width:180px">
                <option value="">All types</option>
                @foreach(['customer'=>'Customer','prospect'=>'Prospect','supplier'=>'Supplier','partner'=>'Partner','other'=>'Other'] as $value=>$label)
                    <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" style="max-width:180px">
                <option value="">All statuses</option>
                <option value="active" @selected($status === 'active')>Active</option>
                <option value="inactive" @selected($status === 'inactive')>Inactive</option>
            </select>
            <button class="btn" type="submit">Filter</button>
            @if($search || $status || $type)<a class="btn" href="{{ route('customers.index') }}">Clear</a>@endif
        </form>
        @if(auth()->user()->hasPermission('clients.manage'))
            <a class="btn primary" href="{{ route('customers.create') }}">Add Customer</a>
        @endif
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Customer</th><th>Type</th><th>Industry</th><th>Sites</th><th>Contacts</th><th>Account Manager</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td><strong>{{ $customer->type_icon }} {{ $customer->company_name }}</strong><br><span class="muted small">{{ $customer->customer_code ?: 'No customer code' }}</span></td>
                    <td>{{ ucfirst($customer->customer_type ?: 'customer') }}</td>
                    <td>{{ $customer->industry ?: '—' }}</td>
                    <td>{{ $customer->sites_count }}</td>
                    <td>{{ $customer->contacts_count }}</td>
                    <td>{{ optional($customer->accountManager)->name ?? '—' }}</td>
                    <td><span class="pill {{ $customer->status === 'active' ? '' : 'off' }}">{{ ucfirst($customer->status) }}</span></td>
                    <td class="actions right">
                        <a class="btn" href="{{ route('customers.show', $customer) }}">Open</a>
                        @if(auth()->user()->hasPermission('clients.manage'))
                            <a class="btn" href="{{ route('customers.edit', $customer) }}">Edit</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No customers created yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $customers->links() }}</div>
</div>
@endsection
