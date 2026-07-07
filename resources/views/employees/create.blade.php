@extends('layouts.app')
@section('title','Add Employee | ISO Admin')
@section('page_title','Add Employee')
@section('content')
<form method="post" action="{{ route('employees.store') }}">
    @csrf
    @include('employees._form')
</form>
@endsection
