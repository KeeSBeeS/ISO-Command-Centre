@php
    $isEdit = isset($document);
    $action = $isEdit ? route('employee_documents.update', $document) : route('employee_documents.store', $employee);
    $hasExpiry = old('has_expiry', $isEdit ? $document->has_expiry : false);
@endphp
<form method="post" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="form-grid">
        <div class="form-row">
            <label for="document_type">Document Type</label>
            <select id="document_type" name="document_type" required>
                @foreach($types as $value => $label)
                    <option value="{{ $value }}" @selected(old('document_type', $isEdit ? $document->document_type : null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-row">
            <label for="title">Document Title</label>
            <input id="title" name="title" value="{{ old('title', $isEdit ? $document->title : '') }}" placeholder="Example: Annual medical certificate" required>
        </div>
        <div class="form-row">
            <label for="attachment">Attachment</label>
            <input id="attachment" name="attachment" type="file" @if(!$isEdit) required @endif>
            @if($isEdit)
                <p class="muted small">Current file: {{ $document->original_filename }} ({{ $document->file_size_label }}). Leave empty to keep it.</p>
            @else
                <p class="muted small">Allowed: PDF, images, Word, Excel, CSV and text files. Max 10 MB.</p>
            @endif
        </div>
        <div class="form-row">
            <label>Expiry Required?</label>
            <label class="check" style="margin:0">
                <input type="checkbox" id="has_expiry" name="has_expiry" value="1" @checked($hasExpiry) onchange="isoDocExpiryToggle()">
                <span>This document has an expiry date and must appear in reminders and on the calendar.</span>
            </label>
        </div>
    </div>

    <div id="expiry-fields" class="form-grid" style="display:{{ $hasExpiry ? 'grid' : 'none' }}">
        <div class="form-row">
            <label for="expires_at">Expiry Date</label>
            <input id="expires_at" name="expires_at" type="date" value="{{ old('expires_at', $isEdit ? optional($document->expires_at)->toDateString() : '') }}" oninput="isoDocReminderPreview()">
        </div>
        <div class="form-row">
            <label for="remind_days_before">Reminder Advance Time</label>
            <select id="remind_days_before" name="remind_days_before" onchange="isoDocReminderPreview()">
                @foreach([7,14,30,45,60,90,120,180] as $days)
                    <option value="{{ $days }}" @selected((int) old('remind_days_before', $isEdit ? ($document->remind_days_before ?? 30) : 30) === $days)>{{ $days }} days before expiry</option>
                @endforeach
            </select>
            <p class="muted small" id="reminder-preview" style="margin-top:8px"></p>
        </div>
    </div>

    <div class="form-row">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" placeholder="Optional internal note">{{ old('notes', $isEdit ? $document->notes : '') }}</textarea>
    </div>

    <div class="actions">
        <button class="btn primary" type="submit">{{ $isEdit ? 'Save Changes' : 'Upload Document' }}</button>
        <a class="btn" href="{{ route('employees.show', $isEdit ? $document->employee : $employee) }}">Cancel</a>
    </div>
</form>

<script>
    function isoDocExpiryToggle() {
        var checked = document.getElementById('has_expiry').checked;
        document.getElementById('expiry-fields').style.display = checked ? 'grid' : 'none';
        isoDocReminderPreview();
    }

    function isoDocReminderPreview() {
        var preview = document.getElementById('reminder-preview');
        var expiresAt = document.getElementById('expires_at').value;
        var days = parseInt(document.getElementById('remind_days_before').value, 10) || 0;

        if (!preview) return;

        if (!expiresAt) {
            preview.textContent = '';
            return;
        }

        var expiry = new Date(expiresAt + 'T00:00:00');
        expiry.setDate(expiry.getDate() - days);
        var formatted = expiry.toISOString().slice(0, 10);
        preview.textContent = 'A reminder will appear on the calendar and reminders list from ' + formatted + '.';
    }

    document.addEventListener('DOMContentLoaded', isoDocReminderPreview);
</script>
