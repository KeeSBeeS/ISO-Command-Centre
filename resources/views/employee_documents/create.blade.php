@extends('layouts.app')
@section('title','Upload Employee Document | ISO Admin')
@section('page_title','Upload Employee Document')
@section('content')
<div class="card">
    <h2>{{ $employee->name }}</h2>
    <p class="muted">Upload medicals, sick notes, warnings, certificates, company policies or other employee profile documents.</p>
    @include('employee_documents._form')
</div>
@endsection
