@extends('layouts.app')
@section('title','Update v2.9.0 | ISO Admin')
@section('page_title','Version 2.9.0 Update')
@section('content')
@php($installed = !in_array(false, $status, true))
<div class="card">
    <h2>WhatsApp Business Integration</h2>
    <p class="muted">Installs the WhatsApp Business (Meta Cloud API) module: shared inbox, templates, broadcasts, employee opt-ins and automatic notifications. Existing modules, tables and settings are not changed.</p>
    <div class="table-wrap" style="margin-top:14px">
        <table style="min-width:0">
            <thead><tr><th>Component</th><th>Status</th></tr></thead>
            <tbody>
            @foreach($status as $component => $ok)
                <tr>
                    <td><code>{{ $component }}</code></td>
                    <td><span class="pill {{ $ok ? '' : 'off' }}">{{ $ok ? 'Installed' : 'Pending' }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @unless($status['system_settings'])
        <div class="alert error" style="margin-top:14px">The <code>system_settings</code> table is missing. Run the v2.5.3 update first.</div>
    @endunless
    <form method="post" action="{{ route('updates.v2_9_0.apply') }}" style="margin-top:18px" class="actions">
        @csrf
        <button class="btn primary" type="submit" @disabled(!$status['system_settings'])>{{ $installed ? 'Re-run v2.9.0 Update' : 'Apply v2.9.0 Update' }}</button>
        @if($installed && Route::has('whatsapp.settings'))
            <a class="btn" href="{{ route('whatsapp.settings') }}">Open WhatsApp Settings</a>
        @endif
    </form>
</div>

<div style="height:14px"></div>
<div class="card">
    <h2>Included</h2>
    <ul class="muted">
        <li>Tables: <code>whatsapp_contacts</code>, <code>whatsapp_messages</code>, <code>whatsapp_templates</code>, <code>whatsapp_broadcasts</code>, <code>whatsapp_webhook_events</code>.</li>
        <li>WhatsApp settings under System Settings, with the access token and app secret stored encrypted.</li>
        <li>Permissions under the <strong>WhatsApp</strong> module. System Administrator receives all of them; Directors receive inbox, contacts, broadcasts and templates; Managers receive inbox and replies. Adjust under Permissions.</li>
        <li>Re-running the update is safe: existing settings and role changes are kept.</li>
    </ul>
</div>
@endsection
