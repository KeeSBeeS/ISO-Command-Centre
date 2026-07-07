@extends('layouts.app')
@section('title','Attendance Import History | ISO Admin')
@section('page_title','Attendance Import History')
@section('content')
<div class="actions right" style="margin-bottom:14px">
    <a class="btn" href="{{ route('attendance.index') }}">Attendance</a>
    @if(auth()->user()->hasPermission('attendance.manual_upload'))<a class="btn primary" href="{{ route('attendance.manual_upload') }}">Director CSV Upload</a>@endif
    @if(auth()->user()->hasPermission('attendance.import'))<a class="btn" href="{{ route('attendance.upload') }}">Standard Upload</a>@endif
</div>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Source</th><th>File</th><th>Rows</th><th>Skipped</th><th>Daily Records</th><th>Status</th><th>Notes</th></tr></thead>
            <tbody>
            @forelse($imports as $import)
                <tr>
                    <td>{{ optional($import->created_at)->format('Y-m-d H:i') }}</td>
                    <td><span class="pill">{{ ucfirst($import->source) }}</span><br><span class="muted small">{{ $import->received_from }}</span></td>
                    <td><strong>{{ $import->filename }}</strong><br><span class="muted small">{{ $import->received_subject }}</span></td>
                    <td>{{ $import->matched_rows }} / {{ $import->raw_rows }}</td>
                    <td>{{ $import->skipped_rows }}</td>
                    <td>{{ $import->day_rows }}</td>
                    <td><span class="pill {{ $import->status === 'completed' ? '' : 'off' }}">{{ ucfirst($import->status) }}</span></td>
                    <td class="muted small">{{ $import->notes }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No imports yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $imports->links() }}</div>
</div>
@endsection
