@extends('layouts.app')
@section('title','Edit Employee | ISO Admin')
@section('page_title','Edit Employee')
@section('content')
<form method="post" action="{{ route('employees.update',$employee) }}">
    @csrf @method('PUT')
    @include('employees._form')
</form>
@endsection
