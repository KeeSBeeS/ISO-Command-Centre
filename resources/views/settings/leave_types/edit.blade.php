@extends('layouts.app')
@section('title','Edit Leave Type | ISO Admin')
@section('page_title','Edit Leave Type')
@section('content')
<form method="post" action="{{ route('leave_types.update',$leaveType) }}">@csrf @method('PUT') @include('settings.leave_types._form')</form>
@endsection
