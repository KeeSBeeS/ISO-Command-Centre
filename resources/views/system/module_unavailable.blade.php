@extends('layouts.app')
@section('title', $moduleTitle . ' | ISO Admin')
@section('page_title', $moduleTitle)
@section('content')
<div class="card">
    <h2 style="margin-bottom:6px">{{ $moduleTitle }} Page Unavailable</h2>
    <p class="muted">The files for this page are not installed on this system, so it cannot be displayed. Login and the rest of the system keep working. Re-apply the update package that contains this module to restore the page.</p>
    @if(!empty($missingRoute))
    <p class="muted">Missing link name: <code>{{ $missingRoute }}</code></p>
    @endif
    <div class="actions" style="margin-top:12px">
        <a class="btn" href="{{ route('dashboard') }}">Back to Dashboard</a>
    </div>
</div>
@endsection
