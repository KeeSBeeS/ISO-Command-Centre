@extends('layouts.app')
@section('title','Add Role | ISO Admin')
@section('page_title','Add Role')
@section('content')
<form method="post" action="{{ route('roles.store') }}">@csrf @include('roles._form')</form>
@endsection
