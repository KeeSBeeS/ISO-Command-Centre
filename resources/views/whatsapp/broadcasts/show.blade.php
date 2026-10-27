@extends('layouts.app')
@section('title','WhatsApp Broadcast | ISO Admin')
@section('page_title','WhatsApp Broadcast')
@section('content')
@include('whatsapp.partials.tabs')
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">📣</div>
        <div>
            <h2>{{ $broadcast->name }}</h2>
            <p>Template <strong>{{ $broadcast->template_name }}</strong> ({{ $broadcast->template_language }}) · {{ ucfirst($broadcast->audience_type) }} · started {{ optional($broadcast->started_at)->format('d M Y H:i') }}</p>
        </div>
    </div>
    <div class="actions"><span class="pill {{ $broadcast->status === 'completed' ? '' : 'warn' }}">{{ ucfirst($broadcast->status) }}</span></div>
</div>
<div class="grid cols-4">
    <div class="card metric"><span>Recipients</span><strong>{{ $broadcast->total_count }}</strong></div>
    <div class="card metric"><span>Queued</span><strong>{{ ($counts['queued'] ?? 0) + ($counts['sending'] ?? 0) }}</strong></div>
    <div class="card metric"><span>Delivered / Read</span><strong>{{ ($counts['delivered'] ?? 0) + ($counts['read'] ?? 0) }}</strong></div>
    <div class="card metric"><span>Failed</span><strong>{{ $counts['failed'] ?? 0 }}</strong></div>
</div>
@if(($counts['queued'] ?? 0) > 0)
    <div class="alert warning" style="margin-top:16px">Queued messages are sent in batches by the WhatsApp cron URL. Make sure it is configured in DirectAdmin.</div>
@endif
<div style="height:16px"></div>
<div class="table-wrap">
    <table>
        <thead><tr><th>Recipient</th><th>Number</th><th>Status</th><th>Detail</th></tr></thead>
        <tbody>
        @foreach($messages as $message)
            <tr>
                <td><a href="{{ route('whatsapp.inbox.show', $message->contact) }}">{{ $message->contact->name_label }}</a></td>
                <td>{{ $message->contact->phone_label }}</td>
                <td><span class="pill {{ in_array($message->status, ['sent', 'delivered', 'read']) ? '' : ($message->status === 'failed' ? 'warn' : 'off') }}">{{ ucfirst($message->status) }}</span></td>
                <td class="small muted">{{ $message->error_message ?: optional($message->read_at ?? $message->delivered_at ?? $message->sent_at)->format('d M H:i') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="pagination">{{ $messages->links() }}</div>
@endsection
