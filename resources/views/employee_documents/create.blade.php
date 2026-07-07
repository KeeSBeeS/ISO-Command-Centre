@extends('layouts.app')
@section('title','Upload Employee Document | ISO Admin')
@section('page_title','Upload Employee Document')
@section('content')
<div class="card">
    <h2>{{ $employee->name }}</h2>
    <p class="muted">Upload medicals, sick notes, warnings, certificates, company policies or other employee profile documents.</p>

    <form method="post" action="{{ route('employee_documents.store', $employee) }}" enctype="multipart/form-data">
        @csrf
        <div class="form-grid">
            <div class="form-row">
                <label for="document_type">Document Type</label>
                <select id="document_type" name="document_type" required>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-row">
                <label for="title">Document Title</label>
                <input id="title" name="title" value="{{ old('title') }}" placeholder="Example: Annual medical certificate" required>
            </div>
            <div class="form-row">
                <label for="attachment">Attachment</label>
                <input id="attachment" name="attachment" type="file" required>
                <p class="muted small">Allowed: PDF, images, Word, Excel, CSV and text files. Max 10 MB.</p>
            </div>
            <div class="form-row">
                <label>Expiry Required?</label>
                <label class="check" style="margin:0">
                    <input type="checkbox" name="has_expiry" value="1" @checked(old('has_expiry')) onchange="document.getElementById('expiry-fields').style.display=this.checked?'grid':'none'">
                    <span>This document has an expiry date and must appear in reminders.</span>
                </label>
            </div>
        </div>

        <div id="expiry-fields" class="form-grid" style="display:{{ old('has_expiry') ? 'grid' : 'none' }}">
            <div class="form-row">
                <label for="expires_at">Expiry Date</label>
                <input id="expires_at" name="expires_at" type="date" value="{{ old('expires_at') }}">
            </div>
            <div class="form-row">
                <label for="remind_days_before">Reminder Advance Time</label>
                <select id="remind_days_before" name="remind_days_before">
                    @foreach([7,14,30,45,60,90,120,180] as $days)
                        <option value="{{ $days }}" @selected((int) old('remind_days_before', 30) === $days)>{{ $days }} days before expiry</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-row">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" placeholder="Optional internal note">{{ old('notes') }}</textarea>
        </div>

        <div class="actions">
            <button class="btn primary" type="submit">Upload Document</button>
            <a class="btn" href="{{ route('employees.show', $employee) }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
