@extends('layouts.app')
@section('title','Edit Employee Document | ISO Admin')
@section('page_title','Edit Employee Document')
@section('content')
<div class="card">
    <h2>{{ $employee->name }}</h2>
    <p class="muted">Update the document details, expiry date or reminder lead time. Upload a new file only if you need to replace the current one.</p>
    @include('employee_documents._form')
</div>
@endsection
