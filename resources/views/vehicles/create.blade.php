@extends('layouts.app')
@section('title','Add Vehicle | ISO Admin')
@section('page_title','Add Vehicle')
@section('content')
<form method="post" action="{{ route('vehicles.store') }}">
    @csrf
    @include('vehicles._form')
</form>
@endsection
