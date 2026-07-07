@extends('layouts.app')
@section('title','Add Client | ISO Admin')
@section('page_title','Add Client')
@section('content_class','content-wide')
@section('content')
<div class="card"><h2>➕ Add Client</h2><p class="muted">Create a client profile. Add operational sites and contacts after saving the client.</p><form method="post" action="{{ route('clients.store') }}">@include('clients._form')</form></div>
@endsection
