@extends('layouts.app')
@section('title','Add Calendar Event | ISO Admin')
@section('page_title','Add Calendar Event')
@section('content')
<div class="card">
    <form method="post" action="{{ route('calendar.store') }}">
        @csrf
        <div class="form-grid">
            <div class="form-row"><label>Title</label><input name="title" value="{{ old('title') }}" required></div>
            <div class="form-row"><label>Type</label><select name="event_type">@foreach($types as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
            <div class="form-row"><label>Starts</label><input type="datetime-local" name="starts_at" value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}" required></div>
            <div class="form-row"><label>Ends</label><input type="datetime-local" name="ends_at" value="{{ old('ends_at', now()->format('Y-m-d\TH:i')) }}"></div>
            <div class="form-row" style="grid-column:1/-1"><label>Notes</label><textarea name="notes">{{ old('notes') }}</textarea></div>
            <div class="form-row"><label class="check"><input type="checkbox" name="all_day" value="1" checked> All day / reminder event</label></div>
        </div>
        <div class="actions"><button class="btn primary" type="submit">Save Event</button><a class="btn" href="{{ route('calendar.index') }}">Cancel</a></div>
    </form>
</div>
@endsection
