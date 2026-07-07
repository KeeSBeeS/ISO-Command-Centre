@extends('layouts.app')
@section('title','Edit Client | ISO Admin')
@section('page_title','Edit Client')
@section('content_class','content-wide')
@section('content')
<div class="card"><h2>✏️ Edit Client</h2><p class="muted">Update core client details. Site locations and site distances are managed from the client profile.</p><form method="post" action="{{ route('clients.update',$client) }}">@method('PUT') @include('clients._form')</form></div>
@endsection
