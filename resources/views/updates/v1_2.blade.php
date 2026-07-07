@extends('layouts.app')
@section('title','Apply Update v1.2 | ISO Admin')
@section('page_title','Apply Update v1.2')
@section('content')
<div class="card">
    <h2>Version 1.2 Update</h2>
    <p class="muted">This update adds a dedicated Director-only manual attendance CSV upload workflow.</p>
    <div class="grid cols-2">
        <div class="card metric"><span>Manual Upload Permission</span><strong style="font-size:24px">{{ $permissionExists ? 'Installed' : 'Missing' }}</strong></div>
        <div class="card metric"><span>Director Role Access</span><strong style="font-size:24px">{{ $directorHasPermission ? 'Assigned' : 'Not Assigned' }}</strong></div>
    </div>
    <div style="height:16px"></div>
    <form method="post" action="{{ route('updates.v1_2.apply') }}">
        @csrf
        <button class="btn primary" type="submit">Apply Version 1.2</button>
        <a class="btn" href="{{ route('dashboard') }}">Back to Dashboard</a>
    </form>
</div>
@endsection
