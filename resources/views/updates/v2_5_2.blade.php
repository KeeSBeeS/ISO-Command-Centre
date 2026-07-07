@extends('layouts.app')
@section('title','Update v2.5.2 | ISO Admin')
@section('page_title','Update v2.5.2')
@section('content')
<div class="grid cols-2">
    <div class="card">
        <div class="page-head" style="margin-bottom:0">
            <div class="page-head-main">
                <div class="page-head-icon">✨</div>
                <div>
                    <h2>Visual Refresh</h2>
                    <p>Restores platform icons and improves the shared interface across the admin system.</p>
                </div>
            </div>
        </div>
        <div class="soft-divider"></div>
        <p class="muted">This update changes interface files only. No database schema changes are required.</p>
        <form method="post" action="{{ route('updates.v2_5_2.apply') }}">
            @csrf
            <button class="btn primary" type="submit">Apply v2.5.2</button>
        </form>
    </div>
    <div class="card">
        <h2>Included</h2>
        <p><span class="pill">Navigation icons</span> <span class="pill">Dashboard icons</span> <span class="pill">Mobile polish</span></p>
        <p class="muted">The update refreshes the side navigation, top bar, cards, tables, buttons, alerts, status pills and editable dashboard screens while keeping existing routes and permissions unchanged.</p>
    </div>
</div>
@endsection
