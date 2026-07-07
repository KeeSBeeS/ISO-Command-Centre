@extends('layouts.app')
@section('title','Edit Department | ISO Admin')
@section('page_title','Edit Department')
@section('content')
<form method="post" action="{{ route('departments.update',$department) }}">@csrf @method('PUT') @include('departments._form')</form>
@endsection
