@extends('layouts.app')
@section('title','WhatsApp Settings | ISO Admin')
@section('page_title','WhatsApp Business Settings')
@section('content')
@include('whatsapp.partials.tabs')
@php($v = fn ($key, $default = null) => old($key, $settings->get($key, $default)))
@php($checked = fn ($key) => (string) old($key, $settings->get($key, '0')) === '1')
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">💬</div>
        <div>
            <h2>WhatsApp Cloud API</h2>
            <p>Connect the ISO WhatsApp Business number through Meta's WhatsApp Cloud API.</p>
        </div>
    </div>
    <div class="actions">
        <span class="pill {{ $settings->ready() ? '' : 'off' }}">{{ $settings->ready() ? 'Live' : ($settings->configured() ? 'Configured · disabled' : 'Not configured') }}</span>
    </div>
</div>

<form method="post" action="{{ route('whatsapp.settings.update') }}">
    @csrf
    @method('PUT')
    <fieldset style="border:0;padding:0;margin:0" @disabled(!$canManage)>
    <div class="grid cols-2">
        <div class="card">
            <h2>Connection</h2>
            <label class="check"><input type="checkbox" name="whatsapp_enabled" value="1" @checked($checked('whatsapp_enabled'))><span>Enable WhatsApp Business messaging</span></label>
            <div style="height:12px"></div>
            <div class="form-row">
                <label>Phone Number ID</label>
                <input type="text" name="whatsapp_phone_number_id" inputmode="numeric" value="{{ $v('whatsapp_phone_number_id') }}" autocomplete="off">
                <p class="muted small">Meta App Dashboard &gt; WhatsApp &gt; API Setup. This is the ID, not the phone number itself.</p>
            </div>
            <div class="form-row">
                <label>WhatsApp Business Account ID</label>
                <input type="text" name="whatsapp_business_account_id" inputmode="numeric" value="{{ $v('whatsapp_business_account_id') }}" autocomplete="off">
            </div>
            <div class="form-row">
                <label>Access Token {!! $settings->hasValue('whatsapp_access_token') ? '<span class="pill">Stored</span>' : '<span class="pill off">Not set</span>' !!}</label>
                <input type="password" name="whatsapp_access_token" value="" placeholder="{{ $settings->hasValue('whatsapp_access_token') ? 'Leave blank to keep the stored token' : 'Paste the permanent System User token' }}" autocomplete="new-password">
                <p class="muted small">Use a System User token from Meta Business Settings with <code>whatsapp_business_messaging</code> and <code>whatsapp_business_management</code>. Temporary tokens expire after 24 hours. Stored encrypted.</p>
            </div>
            <div class="form-row">
                <label>App Secret {!! $settings->hasValue('whatsapp_app_secret') ? '<span class="pill">Stored</span>' : '<span class="pill off">Not set</span>' !!}</label>
                <input type="password" name="whatsapp_app_secret" value="" placeholder="{{ $settings->hasValue('whatsapp_app_secret') ? 'Leave blank to keep the stored secret' : 'Meta App > Settings > Basic > App Secret' }}" autocomplete="new-password">
                <p class="muted small">Required. Incoming webhooks without a valid signature are rejected. Stored encrypted.</p>
            </div>
            <div class="form-grid">
                <div class="form-row">
                    <label>Graph API Version</label>
                    <input type="text" name="whatsapp_api_version" value="{{ $v('whatsapp_api_version', 'v23.0') }}" required>
                </div>
                <div class="form-row">
                    <label>Default Country Code</label>
                    <input type="text" name="whatsapp_default_country_code" inputmode="numeric" value="{{ $v('whatsapp_default_country_code', '27') }}" required>
                    <p class="muted small">27 converts 082 123 4567 to +27 82 123 4567.</p>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Webhook &amp; Cron</h2>
            <p class="muted small">In Meta App Dashboard &gt; WhatsApp &gt; Configuration, set the callback URL and verify token below, then subscribe to the <strong>messages</strong> field.</p>
            <div class="form-row">
                <label>Callback URL</label>
                <input type="text" readonly value="{{ $webhookUrl }}" onclick="this.select()">
            </div>
            <div class="form-row">
                <label>Verify Token</label>
                <input type="text" name="whatsapp_webhook_verify_token" value="{{ $v('whatsapp_webhook_verify_token') }}" required autocomplete="off">
            </div>
            <div class="kv-grid" style="margin-bottom:14px">
                <div class="kv"><span>Last webhook received</span><strong>{{ $settings->get('whatsapp_last_webhook_at', 'Never') }}</strong></div>
                <div class="kv"><span>Last cron run</span><strong>{{ $settings->get('whatsapp_last_cron_at', 'Never') }}</strong></div>
            </div>
            @if($settings->get('whatsapp_last_cron_message'))
                <p class="muted small">{{ $settings->get('whatsapp_last_cron_message') }}</p>
            @endif
            <div class="soft-divider"></div>
            <div class="form-row">
                <label>WhatsApp Cron Key</label>
                <input type="text" name="whatsapp_cron_key" value="{{ $v('whatsapp_cron_key') }}" required autocomplete="off">
            </div>
            <label>Cron URL</label>
            <input type="text" readonly value="{{ $cronUrl }}" onclick="this.select()">
            <p class="muted small">Add this URL as a DirectAdmin cron every 5–15 minutes. It sends queued broadcast messages, retries temporary failures and sends document expiry reminders.</p>
        </div>

        <div class="card">
            <h2>Consent &amp; Auto-reply</h2>
            <label class="check"><input type="checkbox" name="whatsapp_require_opt_in" value="1" @checked($checked('whatsapp_require_opt_in'))><span>Only send notifications and broadcasts to contacts with a recorded opt-in (recommended by Meta policy).</span></label>
            <p class="muted small">Contacts can opt in by replying <strong>START</strong> and opt out by replying <strong>STOP</strong>. Opt-ins can also be recorded under Employee Opt-ins.</p>
            <div style="height:8px"></div>
            <label class="check"><input type="checkbox" name="whatsapp_auto_reply_enabled" value="1" @checked($checked('whatsapp_auto_reply_enabled'))><span>Send an automatic acknowledgement when a new conversation starts (first message after 24 hours of silence).</span></label>
            <div class="form-row" style="margin-top:12px">
                <label>Auto-reply Message</label>
                <textarea name="whatsapp_auto_reply_message" maxlength="1000">{{ $v('whatsapp_auto_reply_message') }}</textarea>
            </div>
        </div>

        <div class="card">
            <h2>Automatic Notifications</h2>
            <p class="muted small">Notifications are business-initiated, so each uses an approved template. Create the templates in Meta WhatsApp Manager, then sync them under Templates.</p>
            <div class="form-row">
                <label>Template Language Code</label>
                <input type="text" name="whatsapp_template_language" value="{{ $v('whatsapp_template_language', 'en') }}" required>
            </div>
            <label class="check"><input type="checkbox" name="whatsapp_notify_leave_enabled" value="1" @checked($checked('whatsapp_notify_leave_enabled'))><span>Leave approved / declined</span></label>
            <div class="form-row" style="margin-top:8px">
                <input type="text" name="whatsapp_leave_template" value="{{ $v('whatsapp_leave_template') }}" placeholder="leave_request_update">
                <p class="muted small">@{{1}} employee · @{{2}} leave type · @{{3}} dates · @{{4}} decision</p>
            </div>
            <label class="check"><input type="checkbox" name="whatsapp_notify_documents_enabled" value="1" @checked($checked('whatsapp_notify_documents_enabled'))><span>Employee document reaching its reminder date</span></label>
            <div class="form-row" style="margin-top:8px">
                <input type="text" name="whatsapp_document_template" value="{{ $v('whatsapp_document_template') }}" placeholder="document_expiry_reminder">
                <p class="muted small">@{{1}} employee · @{{2}} document · @{{3}} expiry date</p>
            </div>
            <label class="check"><input type="checkbox" name="whatsapp_notify_vehicle_documents_enabled" value="1" @checked($checked('whatsapp_notify_vehicle_documents_enabled'))><span>Vehicle document (licence disk, insurance…) reaching its reminder date — sent to the assigned driver</span></label>
            <div class="form-row" style="margin-top:8px">
                <input type="text" name="whatsapp_vehicle_document_template" value="{{ $v('whatsapp_vehicle_document_template') }}" placeholder="vehicle_document_reminder">
                <p class="muted small">@{{1}} driver · @{{2}} vehicle · @{{3}} document · @{{4}} expiry date</p>
            </div>
        </div>
    </div>

    @if($canManage)
        <div style="height:16px"></div>
        <div class="actions right">
            <button class="btn primary" type="submit">Save WhatsApp Settings</button>
        </div>
    @endif
    </fieldset>
</form>

@if($canManage)
<div style="height:14px"></div>
<div class="grid cols-2">
    <div class="card">
        <h2>Connection Test</h2>
        <p class="muted">Reads the phone number's verified name and quality rating from Meta using the stored credentials.</p>
        <form method="post" action="{{ route('whatsapp.settings.test') }}">@csrf<button class="btn" type="submit">Test Connection</button></form>
    </div>
    <div class="card">
        <h2>Send Test Message</h2>
        <p class="muted">Sends Meta's pre-approved <code>hello_world</code> template.</p>
        <form method="post" action="{{ route('whatsapp.settings.test_message') }}" class="actions">
            @csrf
            <input type="tel" name="test_number" placeholder="082 123 4567" required style="flex:1 1 200px">
            <button class="btn primary" type="submit">Send Test</button>
        </form>
    </div>
</div>
@endif
@endsection
