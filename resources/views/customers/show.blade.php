@extends('layouts.app')
@section('title', $customer->company_name . ' | ISO Admin')
@section('page_title','Customer Profile')
@section('content')
<div class="actions right" style="margin-bottom:14px">
    <a class="btn" href="{{ route('customers.index') }}">Back to Customers</a>
    @if(auth()->user()->hasPermission('clients.manage'))
        <a class="btn primary" href="{{ route('customers.edit', $customer) }}">Edit Customer</a>
    @endif
</div>

<div class="grid cols-2">
    <div class="card">
        <h2>{{ $customer->company_name }}</h2>
        <p><span class="pill {{ $customer->status === 'active' ? '' : 'off' }}">{{ ucfirst($customer->status) }}</span></p>
        <div class="grid cols-2">
            <div><span class="muted small">Customer Code</span><br><strong>{{ $customer->customer_code ?: '—' }}</strong></div>
            <div><span class="muted small">Contact Person</span><br><strong>{{ $customer->contact_person ?: '—' }}</strong></div>
            <div><span class="muted small">Phone</span><br><strong>{{ $customer->phone ?: '—' }}</strong></div>
            <div><span class="muted small">Email</span><br><strong>{{ $customer->email ?: '—' }}</strong></div>
        </div>
    </div>
    <div class="card">
        <h3>Address</h3>
        <p class="muted">{!! nl2br(e($customer->address ?: 'No address captured.')) !!}</p>
        <h3>Notes</h3>
        <p class="muted">{!! nl2br(e($customer->notes ?: 'No notes captured.')) !!}</p>
    </div>
</div>

@if(auth()->user()->hasPermission('clients.manage'))
    <div class="card" style="margin-top:16px">
        <h3>Danger Zone</h3>
        <form method="post" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Delete this customer?')">
            @csrf @method('DELETE')
            <button class="btn danger" type="submit">Delete Customer</button>
        </form>
    </div>
@endif
@endsection
