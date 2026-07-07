@extends('layouts.app')
@section('title','Edit Customer | ISO Admin')
@section('page_title','Edit Customer')
@section('content')
<form method="post" action="{{ route('customers.update', $customer) }}">@csrf @method('PUT') @include('customers._form')</form>
@endsection
