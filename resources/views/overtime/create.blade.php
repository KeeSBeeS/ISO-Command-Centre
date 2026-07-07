@extends('layouts.app')
@section('title','Add Overtime | ISO Admin')
@section('page_title','Add Overtime')
@section('content_class','content-wide')
@section('content')
<div class="card">
    <h2>⏱️ Add Overtime</h2>
    <p class="muted">Select the employee and the client site. Use the checkboxes to mark whether the overtime was for an installation, a service, or both.</p>
    <form method="post" action="{{ route('overtime.store') }}" class="form-grid overtime-form" style="margin-top:14px">
        @csrf
        <div class="form-row"><label>Employee</label><select name="user_id" required><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(old('user_id') == $employee->id)>{{ $employee->name }}{{ $employee->employee_code ? ' · '.$employee->employee_code : '' }}</option>@endforeach</select></div>
        <div class="form-row"><label>Client Site</label><select name="crm_client_site_id" required><option value="">Select client site</option>@foreach($sites as $site)<option value="{{ $site->id }}" @selected(old('crm_client_site_id') == $site->id)>{{ optional($site->client)->name }} · {{ $site->name }}{{ $site->location ? ' · '.$site->location : '' }}</option>@endforeach</select></div>
        <div class="form-row"><label>Date</label><input type="date" name="overtime_date" value="{{ old('overtime_date', now()->toDateString()) }}" required></div>
        <div class="form-row"><label>Hours</label><input type="number" step="0.25" min="0.1" max="48" name="hours" value="{{ old('hours') }}" placeholder="Example: 2.5"></div>
        <div class="form-row"><label>Start Time</label><input type="time" name="start_time" value="{{ old('start_time') }}"></div>
        <div class="form-row"><label>End Time</label><input type="time" name="end_time" value="{{ old('end_time') }}"></div>
        <div class="form-row" style="grid-column:1/-1">
            <label>Overtime Type</label>
            <div class="checkbox-grid">
                <label class="check"><input type="checkbox" name="is_installation" value="1" @checked(old('is_installation'))><span>🔧 Installation</span></label>
                <label class="check"><input type="checkbox" name="is_service" value="1" @checked(old('is_service'))><span>🧰 Service</span></label>
            </div>
        </div>
        <div class="form-row" style="grid-column:1/-1"><label>Note / Reason</label><textarea name="note" placeholder="Why was overtime required?">{{ old('note') }}</textarea></div>
        <div class="form-row" style="grid-column:1/-1"><button class="btn primary" type="submit">💾 Save Overtime</button><a class="btn" href="{{ route('overtime.index') }}">Cancel</a></div>
    </form>
</div>
<style>@media(min-width:1400px){.overtime-form{grid-template-columns:repeat(3,1fr)}}@media(max-width:760px){.overtime-form{grid-template-columns:1fr}}</style>
@endsection
