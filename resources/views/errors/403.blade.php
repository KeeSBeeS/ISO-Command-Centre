@extends('layouts.app')
@section('title','No Permission | ISO Admin')
@section('page_title','Access Denied')
@section('content')
<div class="card" style="max-width:680px">
    <h2>Access denied</h2>
    <p class="muted">Your current role does not include permission to access this area.</p>
    <a class="btn primary" href="{{ route('dashboard') }}">Back to Dashboard</a>
</div>
@endsection
