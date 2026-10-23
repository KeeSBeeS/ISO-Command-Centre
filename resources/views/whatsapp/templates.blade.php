@extends('layouts.app')
@section('title','WhatsApp Templates | ISO Admin')
@section('page_title','WhatsApp Templates')
@section('content')
@include('whatsapp.partials.tabs')
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">📋</div>
        <div>
            <h2>Message Templates</h2>
            <p>Templates are created and approved in Meta WhatsApp Manager, then synced here.</p>
        </div>
    </div>
    @if(auth()->user()->hasPermission('whatsapp.templates.manage'))
        <form method="post" action="{{ route('whatsapp.templates.sync') }}" class="actions">@csrf<button class="btn primary" type="submit">Sync from Meta</button></form>
    @endif
</div>

@if($notificationTemplates)
    <div class="card" style="margin-bottom:16px">
        <h3>Templates used by automatic notifications</h3>
        <div class="actions">
            @foreach($notificationTemplates as $purpose => $name)
                @php($match = $templates->first(fn ($t) => $t->name === $name && $t->language === $language))
                <span class="pill {{ $match && $match->status === 'APPROVED' ? '' : 'warn' }}">{{ $purpose }}: {{ $name }} ({{ $language }}) — {{ $match ? $match->status : 'not found in Meta' }}</span>
            @endforeach
        </div>
    </div>
@endif

<div class="table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Language</th><th>Category</th><th>Status</th><th>Body</th><th>Platform</th></tr></thead>
        <tbody>
        @forelse($templates as $template)
            <tr>
                <td><strong>{{ $template->name }}</strong></td>
                <td>{{ $template->language }}</td>
                <td>{{ ucfirst(strtolower((string) $template->category)) }}</td>
                <td><span class="pill {{ $template->status === 'APPROVED' ? '' : ($template->status === 'REJECTED' ? 'warn' : 'off') }}">{{ $template->status }}</span></td>
                <td class="small" style="white-space:pre-wrap;max-width:420px">{{ \Illuminate\Support\Str::limit($template->body_text, 300) }}</td>
                <td>
                    @if($template->isSendable())
                        <span class="pill">Sendable</span>
                    @elseif($template->status === 'APPROVED')
                        <span class="pill off" title="Media headers and dynamic URL buttons are not supported from the platform.">Meta only</span>
                    @else
                        <span class="pill off">Not approved</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No templates synced yet. Set the WhatsApp Business Account ID in settings and click Sync from Meta.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div style="height:14px"></div>
<div class="card">
    <h2>Suggested notification templates</h2>
    <p class="muted">Create these in WhatsApp Manager (category <strong>Utility</strong>) to use the built-in notifications. Variable order matters.</p>
    <div class="grid cols-2">
        <div class="kv"><span>leave_request_update</span><strong class="small" style="font-weight:600">Hi @{{1}}, your @{{2}} request for @{{3}} has been @{{4}}. Log in to ISO Admin for details.</strong></div>
        <div class="kv"><span>document_expiry_reminder</span><strong class="small" style="font-weight:600">Hi @{{1}}, your @{{2}} expires on @{{3}}. Please submit an updated copy to HR.</strong></div>
        <div class="kv"><span>vehicle_document_reminder</span><strong class="small" style="font-weight:600">Hi @{{1}}, the @{{3}} for @{{2}} expires on @{{4}}. Please arrange renewal.</strong></div>
    </div>
</div>
@endsection
