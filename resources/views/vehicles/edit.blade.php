@extends('layouts.app')
@section('title','Edit Vehicle | ISO Admin')
@section('page_title','Edit Vehicle')
@section('content')
<form method="post" action="{{ route('vehicles.update',$vehicle) }}">
    @csrf @method('PUT')
    @include('vehicles._form')
</form>
@endsection
