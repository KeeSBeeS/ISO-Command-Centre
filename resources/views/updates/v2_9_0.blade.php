@extends('layouts.app')
@section('title','Update v2.9.0 | ISO Admin')
@section('page_title','Version 2.9.0 Update')
@section('content')
<div class="card">
    <h2>Update Manager</h2>
    <p class="muted">Adds the Update Manager under the Admin menu for System Administrators. The platform can now be updated from the web interface either by uploading a deployment ZIP or by downloading the configured GitHub repository branch, with an automatic code backup before every apply.</p>
    <div class="kv-grid" style="margin-top:16px">
        <div class="kv"><span>Current Version</span><strong>{{ $systemVersion ?? 'Unknown' }}</strong></div>
        <div class="kv"><span>Update Manager Settings</span><strong>{{ $settingsSeeded ? 'Seeded' : 'Pending' }}</strong></div>
        <div class="kv"><span>Update Permissions</span><strong>{{ $permissionCount }} of 2</strong></div>
        <div class="kv"><span>PHP ZIP Extension</span><strong>{{ $zipAvailable ? 'Available' : 'Missing' }}</strong></div>
    </div>
    @unless($zipAvailable)
        <div class="alert warning" style="margin-top:14px">The PHP ZIP extension is missing on this server. The Update Manager pages will load, but packages cannot be applied until the extension is enabled.</div>
    @endunless
    <form method="post" action="{{ route('updates.v2_9_0.apply') }}" style="margin-top:18px">
        @csrf
        <button class="btn primary" type="submit">Apply v2.9.0 Update</button>
    </form>
</div>
@endsection
