@extends('layouts.app')
@section('title','Update v2.8.8 | ISO Admin')
@section('page_title','Version 2.8.8 Update')
@section('content')
<div class="card">
    <h2>Employee Compliance Overview</h2>
    <p class="muted">Adds the Employee Compliance Overview page, sidebar link and dashboard widget, backed by real employee document data (no new tables required). Seeds the "View Employee Compliance" permission for Director and Manager roles.</p>
    <div class="grid cols-2" style="margin-top:16px">
        <div class="metric-box"><span>Current Version</span><strong style="font-size:18px">{{ $systemVersion ?? 'Unknown' }}</strong></div>
        <div class="metric-box"><span>Permission Seeded</span><strong style="font-size:18px">{{ $permissionExists ? 'Yes' : 'Pending' }}</strong></div>
    </div>
    <form method="post" action="{{ route('updates.v2_8_8.apply') }}" style="margin-top:18px">
        @csrf
        <button class="btn primary" type="submit">Apply v2.8.8 Update</button>
    </form>
</div>
@endsection
