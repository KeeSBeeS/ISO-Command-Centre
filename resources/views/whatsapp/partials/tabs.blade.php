@php($waUser = auth()->user())
<style>
    .wa-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
    .wa-tabs a{border:1px solid var(--line);background:rgba(255,255,255,.05);padding:9px 13px;border-radius:13px;font-weight:850;font-size:13px;color:var(--muted)}
    .wa-tabs a.active,.wa-tabs a:hover{color:#fff;border-color:var(--line-strong);background:linear-gradient(135deg,rgba(18,163,116,.22),rgba(139,220,101,.08))}
    .wa-badge{display:inline-grid;place-items:center;min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:var(--brand);color:#061017;font-size:11px;font-weight:900;margin-left:4px}
</style>
<nav class="wa-tabs" aria-label="WhatsApp sections">
    <a href="{{ route('whatsapp.inbox') }}" class="{{ request()->routeIs('whatsapp.inbox*') ? 'active' : '' }}">💬 Inbox @if(($unreadTotal ?? 0) > 0)<span class="wa-badge">{{ $unreadTotal }}</span>@endif</a>
    @if($waUser->hasPermission('whatsapp.contacts.manage'))
        <a href="{{ route('whatsapp.contacts') }}" class="{{ request()->routeIs('whatsapp.contacts*') ? 'active' : '' }}">👥 Employee Opt-ins</a>
    @endif
    @if($waUser->hasPermission('whatsapp.broadcast'))
        <a href="{{ route('whatsapp.broadcasts.index') }}" class="{{ request()->routeIs('whatsapp.broadcasts*') ? 'active' : '' }}">📣 Broadcasts</a>
    @endif
    <a href="{{ route('whatsapp.templates') }}" class="{{ request()->routeIs('whatsapp.templates*') ? 'active' : '' }}">📋 Templates</a>
    @if($waUser->hasPermission('whatsapp.settings.view'))
        <a href="{{ route('whatsapp.settings') }}" class="{{ request()->routeIs('whatsapp.settings*') ? 'active' : '' }}">🔧 Settings</a>
    @endif
</nav>
