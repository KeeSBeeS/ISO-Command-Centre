@extends('layouts.app')
@section('title','Add Leave Type | ISO Admin')
@section('page_title','Add Leave Type')
@section('content')
<form method="post" action="{{ route('leave_types.store') }}">@csrf @include('settings.leave_types._form')</form>
@endsection
