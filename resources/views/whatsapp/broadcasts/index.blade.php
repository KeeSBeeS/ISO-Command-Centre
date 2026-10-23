@extends('layouts.app')
@section('title','WhatsApp Broadcasts | ISO Admin')
@section('page_title','WhatsApp Broadcasts')
@section('content')
@include('whatsapp.partials.tabs')
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">📣</div>
        <div>
            <h2>Broadcasts</h2>
            <p>Send an approved template to all, or a department or role, of your opted-in employees.</p>
        </div>
    </div>
    <div class="actions"><a class="btn primary" href="{{ route('whatsapp.broadcasts.create') }}">New Broadcast</a></div>
</div>
<div class="table-wrap">
    <table>
        <thead><tr><th>Broadcast</th><th>Template</th><th>Status</th><th>Recipients</th><th>Sent</th><th>Delivered</th><th>Read</th><th>Failed</th><th>Created</th></tr></thead>
        <tbody>
        @forelse($broadcasts as $broadcast)
            <tr>
                <td><a href="{{ route('whatsapp.broadcasts.show', $broadcast) }}"><strong>{{ $broadcast->name }}</strong></a></td>
                <td>{{ $broadcast->template_name }}</td>
                <td><span class="pill {{ $broadcast->status === 'completed' ? '' : 'warn' }}">{{ ucfirst($broadcast->status) }}@if($broadcast->queued_count) · {{ $broadcast->queued_count }} queued @endif</span></td>
                <td>{{ $broadcast->total_count }}</td>
                <td>{{ $broadcast->sent_count }}</td>
                <td>{{ $broadcast->delivered_count }}</td>
                <td>{{ $broadcast->read_count }}</td>
                <td>{{ $broadcast->failed_count }}</td>
                <td class="small">{{ $broadcast->created_at->format('d M Y H:i') }}<div class="muted">{{ optional($broadcast->creator)->name }}</div></td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted">No broadcasts sent yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $broadcasts->links() }}</div>
@endsection
