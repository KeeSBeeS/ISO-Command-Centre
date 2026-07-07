@extends('layouts.app')
@section('title','Attendance Detail | ISO Admin')
@section('page_title','Attendance Detail')
@section('content')
<div class="actions" style="margin-bottom:14px"><a class="btn" href="{{ route('attendance.index') }}">Back to Attendance</a></div>
<div class="grid cols-2">
    <div class="card">
        <h2>{{ $attendanceDay->user->name }}</h2>
        <p class="muted">{{ optional($attendanceDay->attendance_date)->format('Y-m-d') }}</p>
        <p><strong>Start:</strong> {{ optional($attendanceDay->start_time)->format('H:i:s') ?? '-' }} · {{ $attendanceDay->first_status }}</p>
        <p><strong>Checkout:</strong> {{ optional($attendanceDay->end_time)->format('H:i:s') ?? '-' }} · {{ $attendanceDay->last_status }}</p>
        <p><strong>Hours:</strong> {{ $attendanceDay->work_hours }}</p>
        <p><strong>Records:</strong> {{ $attendanceDay->record_count }}</p>
        @if($attendanceDay->is_public_holiday ?? false)
            <p><strong>Public Holiday:</strong> {{ $attendanceDay->public_holiday_name }} · company closed</p>
        @elseif($attendanceDay->is_late ?? false)
            <p><strong>Late Clock-in:</strong> {{ $attendanceDay->late_label }}</p>
        @endif
        @if($attendanceDay->anomalies)<p><strong>Flag:</strong> {{ $attendanceDay->anomalies }}</p>@endif
    </div>
    <div class="card">
        <h2>Import Source</h2>
        <p><strong>Source:</strong> {{ optional($attendanceDay->import)->source ? ucfirst($attendanceDay->import->source) : 'Unknown' }}</p>
        <p><strong>File:</strong> {{ optional($attendanceDay->import)->filename ?? 'Unknown' }}</p>
        <p><strong>Imported:</strong> {{ optional(optional($attendanceDay->import)->created_at)->format('Y-m-d H:i') ?? 'Unknown' }}</p>
    </div>
</div>
<div style="height:16px"></div>
<div class="card">
    <h2>Raw Records Used</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Time</th><th>Name in CSV</th><th>Attendance Status</th><th>Imported</th></tr></thead>
            <tbody>
            @foreach($records as $record)
                <tr>
                    <td>{{ optional($record->recorded_at)->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $record->employee_name }}</td>
                    <td>{{ $record->attendance_status }}</td>
                    <td>{{ optional($record->created_at)->format('Y-m-d H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
