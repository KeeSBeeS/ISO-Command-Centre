@extends('layouts.app')
@section('title','Add Department | ISO Admin')
@section('page_title','Add Department')
@section('content')
<form method="post" action="{{ route('departments.store') }}">@csrf @include('departments._form')</form>
@endsection
