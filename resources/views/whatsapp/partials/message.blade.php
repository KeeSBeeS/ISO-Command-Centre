<div class="wa-msg {{ $message->isInbound() ? 'in' : 'out' }} {{ $message->status === 'failed' ? 'failed' : '' }}" data-id="{{ $message->id }}" id="wa-msg-{{ $message->id }}">
    @if($message->type === 'template')
        <div class="wa-msg-tag">📋 Template · {{ $message->template_name }}@if($message->source === 'broadcast') · Broadcast @elseif($message->source === 'notification') · Notification @endif</div>
    @elseif($message->source === 'auto_reply')
        <div class="wa-msg-tag">🤖 Auto-reply</div>
    @endif

    @if($message->context_wamid)
        <div class="wa-msg-tag">↩ Reply</div>
    @endif

    @if($message->hasMedia())
        @if($message->type === 'image' || $message->type === 'sticker')
            <a href="{{ route('whatsapp.messages.media', $message) }}" target="_blank" rel="noopener"><img class="wa-media" src="{{ route('whatsapp.messages.media', $message) }}" alt="{{ $message->type }}" loading="lazy"></a>
        @elseif($message->type === 'audio')
            <audio class="wa-media" controls preload="none" src="{{ route('whatsapp.messages.media', $message) }}"></audio>
        @elseif($message->type === 'video')
            <video class="wa-media" controls preload="none" src="{{ route('whatsapp.messages.media', $message) }}"></video>
        @else
            <a class="wa-file" href="{{ route('whatsapp.messages.media', $message) }}">📄 {{ $message->media_filename ?: 'Download document' }}</a>
        @endif
    @endif

    @if($message->body !== null && $message->body !== '')
        <div class="wa-msg-body">{!! nl2br(e(($message->type === 'location' ? '📍 ' : '') . $message->body)) !!}</div>
    @elseif(!$message->hasMedia())
        <div class="wa-msg-body muted">{{ $message->preview ?: ucfirst($message->type) . ' message' }}</div>
    @endif

    <div class="wa-msg-meta">
        @if(!$message->isInbound() && $message->sender)<span>{{ $message->sender->name }} ·</span>@endif
        <span title="{{ $message->created_at }}">{{ optional($message->created_at)->format('d M H:i') }}</span>
        @unless($message->isInbound())
            <span class="wa-status" data-status="{{ $message->status }}">{{ $message->status_icon }}</span>
        @endunless
    </div>

    @if($message->status === 'failed' || ($message->status === 'queued' && $message->error_message))
        <div class="wa-msg-error">
            {{ $message->error_message ?: 'Delivery failed.' }}
            @if(auth()->user()->hasPermission('whatsapp.send'))
                <form method="post" action="{{ route('whatsapp.messages.retry', $message) }}">@csrf<button class="btn" type="submit" style="padding:5px 10px;font-size:12px">Retry</button></form>
            @endif
        </div>
    @endif
</div>
