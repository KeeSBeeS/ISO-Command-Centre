@extends('layouts.app')
@section('title','Edit Role | ISO Admin')
@section('page_title','Edit Role')
@section('content')
<form method="post" action="{{ route('roles.update',$role) }}">@csrf @method('PUT') @include('roles._form')</form>
@endsection
