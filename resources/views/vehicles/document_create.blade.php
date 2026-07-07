@extends('layouts.app')
@section('title','Upload Vehicle Document | ISO Admin')
@section('page_title','Upload Vehicle Document')
@section('content')
<div class="card">
    <h2>{{ $vehicle->display_name }}</h2>
    <p class="muted">Attach NATIS documents, license disk files and other expiry-controlled vehicle documents.</p>
</div>
<div style="height:14px"></div>
<form method="post" action="{{ route('vehicles.documents.store',$vehicle) }}" enctype="multipart/form-data">
    @csrf
    <div class="grid cols-2">
        <div class="card">
            <h2>Document</h2>
            <div class="form-row"><label>Document Type</label><select name="document_type" required>@foreach($types as $key => $label)<option value="{{ $key }}" @selected(old('document_type')===$key)>{{ $label }}</option>@endforeach</select></div>
            <div class="form-row"><label>Title</label><input type="text" name="title" value="{{ old('title') }}" required></div>
            <div class="form-row"><label>File</label><input type="file" name="attachment" required></div>
            <div class="form-row"><label>Notes</label><textarea name="notes">{{ old('notes') }}</textarea></div>
        </div>
        <div class="card">
            <h2>Expiry Reminder</h2>
            <label class="check"><input type="checkbox" name="has_expiry" value="1" @checked(old('has_expiry'))><span>This document has an expiry date</span></label>
            <div class="form-row" style="margin-top:14px"><label>Expiry Date</label><input type="date" name="expires_at" value="{{ old('expires_at') }}"></div>
            <div class="form-row"><label>Remind Before Expiry</label><select name="remind_days_before">
                @foreach([7,14,30,45,60,90,120,180,365] as $days)<option value="{{ $days }}" @selected((int)old('remind_days_before',30)===$days)>{{ $days }} days before</option>@endforeach
            </select></div>
            <p class="muted small">For license disks, set the disk expiry date and choose how many days in advance managers/directors must be reminded.</p>
        </div>
    </div>
    <div style="height:14px"></div>
    <div class="actions"><button class="btn primary" type="submit">Upload Document</button><a class="btn" href="{{ route('vehicles.show',$vehicle) }}">Cancel</a></div>
</form>
@endsection
