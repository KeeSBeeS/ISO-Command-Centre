@extends('layouts.app')
@section('title','Upload Attendance CSV | ISO Admin')
@section('page_title','Upload Attendance CSV')
@section('content')
<div class="grid cols-2">
    <div class="card">
        <h2>Upload CSV Export</h2>
        <p class="muted">Upload the exported attendance CSV. The importer supports both the old event-log CSV with <strong>Name</strong>, <strong>Time</strong> and <strong>Attendance Status</strong>, and the daily attendance export with <strong>Person ID</strong>, <strong>Name</strong>, <strong>Date</strong>, <strong>Check-In</strong> and <strong>Check-out</strong>.</p>
        <form method="post" action="{{ route('attendance.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-row"><label>CSV File</label><input type="file" name="csv_file" accept=".csv,text/csv,text/plain" required></div>
            <div class="actions"><button class="btn primary" type="submit">Import CSV</button><a class="btn" href="{{ route('attendance.index') }}">Cancel</a></div>
        </form>
    </div>
    <div class="card">
        <h2>Import Rule</h2>
        <p class="muted">For each employee and date, clock-in is accepted until 09:00, the earliest valid clock-in is kept, and the latest different timestamp is used as checkout.</p>
        <p class="muted">If the CSV employee name does not match an active system employee, the row is skipped. Use the employee field <strong>Attendance CSV Name</strong> when the CSV name differs from the employee profile name.</p>
    </div>
</div>
@endsection
