@extends('layouts.app')
@section('title','Director Manual Attendance Upload | ISO Admin')
@section('page_title','Director Manual Attendance Upload')
@section('content')
<div class="grid cols-2">
    <div class="card">
        <h2>Manual CSV Upload</h2>
        <p class="muted">Director-only upload for attendance exports. This is useful when the email import did not run or when a historical CSV must be imported manually.</p>
        <form method="post" action="{{ route('attendance.manual_import') }}" enctype="multipart/form-data" onsubmit="return confirm('Import this attendance CSV now? Existing matching rows will not be duplicated.')">
            @csrf
            <div class="form-row"><label>Attendance CSV File</label><input type="file" name="csv_file" accept=".csv,text/csv,text/plain" required></div>
            <div class="form-row"><label>Import Note <span class="muted small">Optional</span></label><input type="text" name="import_note" maxlength="500" placeholder="Example: Manual upload for June attendance"></div>
            <div class="actions"><button class="btn primary" type="submit">Upload & Import CSV</button><a class="btn" href="{{ route('attendance.index') }}">Cancel</a></div>
        </form>
    </div>
    <div class="card">
        <h2>Processing Rules</h2>
        <p class="muted">The importer supports the daily attendance export format used for the morning import: <strong>Person ID</strong>, <strong>Name</strong>, <strong>Date</strong>, <strong>Check-In</strong> and <strong>Check-out</strong>. It still supports the older event-log format with <strong>Name</strong> and <strong>Time</strong>.</p>
        <p class="muted">For each employee and date, the earliest clock-in before 09:00 is kept as <strong>start time</strong>. If no clock-in exists before 09:00, the first available clock-in is marked late. The latest different time becomes <strong>checkout time</strong>.</p>
        <p class="muted">If the CSV employee does not exist in ISO Admin, that row is skipped. Use <strong>Attendance CSV Name</strong> on the employee profile when the device export name differs from the employee's normal system name.</p>
        <p class="muted">Duplicate rows are ignored by the importer hash, so re-uploading the same CSV will not create duplicate raw punches.</p>
    </div>
</div>
@endsection
