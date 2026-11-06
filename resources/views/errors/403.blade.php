@extends('layouts.app')
@section('title','No Permission | ISO Admin')
@section('page_title','Access Denied')
@section('content')
<div class="card" style="max-width:680px">
    <div class="page-head-main" style="margin-bottom:12px">
        <span class="page-head-icon"><x-icon name="permissions" /></span>
        <h2 style="margin:0">You don't have access to this page</h2>
    </div>
    <p class="muted">Your role doesn't include permission for this area. Ask a System Administrator if you need it.</p>
    @auth<a class="btn primary" href="{{ route('dashboard') }}">Back to the dashboard</a>@else<a class="btn primary" href="{{ route('login') }}">Log in</a>@endauth
</div>
@endsection
