@extends('layouts.app')
@section('title','Add Customer | ISO Admin')
@section('page_title','Add Customer')
@section('content')
<form method="post" action="{{ route('customers.store') }}">@csrf @include('customers._form')</form>
@endsection
