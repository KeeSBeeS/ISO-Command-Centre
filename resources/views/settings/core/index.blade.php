@extends('layouts.app')
@section('title','Core Settings | ISO Admin')
@section('page_title','Core Settings')
@section('content')
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">🔧</div>
        <div>
            <h2>Core System Settings</h2>
            <p>System Administrator-only settings for platform identity, notification defaults, reminders and operational limits.</p>
        </div>
    </div>
</div>

<div class="alert warning">
    These settings are restricted to the <strong>System Administrator</strong>. Directors and managers should not receive access to this page.
</div>

<form method="post" action="{{ route('core_settings.update') }}">
    @csrf
    @method('PUT')

    <div class="grid cols-2">
        @foreach($groups as $groupName => $settings)
            <div class="card">
                <div class="page-head-main" style="margin-bottom:12px">
                    <div class="section-icon">{{ match($groupName) { 'Identity' => '🏢', 'Notifications' => '✉️', 'Reminders' => '⏰', 'Attendance' => '⏱️', 'Documents' => '📄', 'Vehicles' => '🚗', 'Security' => '🔐', default => '⚙️' } }}</div>
                    <div>
                        <h2 style="margin:0">{{ $groupName }}</h2>
                        <p class="muted small" style="margin:4px 0 0">Core configuration values.</p>
                    </div>
                </div>

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
        @endforeach
    </div>

    <div style="height:16px"></div>
    <div class="actions right">
        <button class="btn primary" type="submit">Save Core Settings</button>
    </div>
</form>
@endsection
