@extends('layouts.app')
@section('title','Core Settings | ISO Admin')
@section('page_title','Core Settings')
@section('content')
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">🔧</div>
        <div>
            <h2>Core System Settings</h2>
            <p>Only important core settings are shown here. Technical/API settings stay out of this page to keep it clean.</p>
        </div>
    </div>
</div>

<div class="alert warning">
    These settings are restricted to the <strong>System Administrator</strong>. Groups are collapsed to reduce scrolling.
</div>

<form method="post" action="{{ route('core_settings.update') }}">
    @csrf
    @method('PUT')

    <div class="settings-accordion">
        @foreach($groups as $groupName => $settings)
            <details class="card settings-panel" {{ $loop->first ? 'open' : '' }}>
                <summary class="settings-summary">
                    <span class="settings-summary-main">
                        <span class="section-icon">{{ match($groupName) { 'Identity' => '🏢', 'Notifications' => '✉️', 'Reminders' => '⏰', 'Attendance' => '⏱️', 'Documents' => '📄', 'Vehicles' => '🚗', 'Security' => '🔐', default => '⚙️' } }}</span>
                        <span>
                            <strong>{{ $groupName }}</strong>
                            <small class="muted">{{ $settings->count() }} setting{{ $settings->count() === 1 ? '' : 's' }}</small>
                        </span>
                    </span>
                    <span class="muted small">Click to expand</span>
                </summary>

                <div class="settings-panel-body">
                    <div class="form-grid">
                        @foreach($settings as $setting)
                            <div class="form-row">
                                @if($setting->type === 'boolean')
                                    <label class="check" style="margin:0">
                                        <input type="checkbox" name="settings[{{ $setting->key }}]" value="1" @checked(old('settings.'.$setting->key, $setting->value) == '1')>
                                        <span>
                                            {{ $setting->label }}
                                            @if($setting->description)<br><small class="muted">{{ $setting->description }}</small>@endif
                                        </span>
                                    </label>
                                @else
                                    <label>{{ $setting->label }}</label>
                                    @if($setting->type === 'textarea')
                                        <textarea name="settings[{{ $setting->key }}]">{{ old('settings.'.$setting->key, $setting->value) }}</textarea>
                                    @else
                                        <input
                                            type="{{ in_array($setting->type, ['email','url','time']) ? $setting->type : ($setting->type === 'integer' ? 'number' : 'text') }}"
                                            name="settings[{{ $setting->key }}]"
                                            value="{{ old('settings.'.$setting->key, $setting->value) }}">
                                    @endif
                                    @if($setting->description)<p class="muted small" style="margin:7px 0 0">{{ $setting->description }}</p>@endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </details>
        @endforeach
    </div>

    <div style="height:16px"></div>
    <div class="actions right settings-save-bar">
        <button class="btn primary" type="submit">Save Core Settings</button>
    </div>
</form>

<style>
    .settings-accordion{display:grid;gap:12px}
    .settings-panel{padding:0;overflow:hidden}
    .settings-summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 18px;border-bottom:1px solid rgba(148,163,184,.18)}
    .settings-summary::-webkit-details-marker{display:none}
    .settings-summary-main{display:flex;align-items:center;gap:12px}
    .settings-summary strong{display:block;font-size:17px}
    .settings-summary small{display:block;margin-top:3px}
    .settings-panel:not([open]) .settings-summary{border-bottom:none}
    .settings-panel-body{padding:18px}
    .settings-save-bar{position:sticky;bottom:0;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);padding:12px 0;z-index:5}
    @media(max-width:760px){.settings-summary{align-items:flex-start}.settings-summary>span:last-child{display:none}}
</style>
@endsection
