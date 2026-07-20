@extends('layouts.app')
@section('title','Core Settings | ISO Admin')
@section('page_title','Core Settings')
@section('content_class','content-wide')
@section('content')
<div class="grid cols-2">
    <div class="card">
        <h2>⚙️ Platform</h2>
        <p><strong>Version:</strong> {{ $version }}</p>
        <p><strong>Holiday records:</strong> {{ $holidayCount }}</p>
        <p class="muted">Core settings are director-only by default. Permission changes are still managed from Roles & Permissions.</p>
        <div class="actions">
            @if(auth()->user()->hasPermission('settings.permissions'))<a class="btn" href="{{ route('roles.index') }}">🔐 Permissions Matrix</a>@endif
            <a class="btn" href="{{ route('calendar.index') }}">📅 Open Calendar</a>
        </div>
    </div>
    <div class="card">
        <h2>🇿🇦 South African Holidays</h2>
        <p class="muted">Imports public holidays used by attendance and calendar. If the server cannot fetch the Google feed, v2.0 seeds a safe 2026/2027 fallback list.</p>
        @if(auth()->user()->hasPermission('settings.core'))
            <form method="post" action="{{ route('settings.sync_holidays') }}">@csrf<button class="btn primary" type="submit">🔄 Sync Holidays Now</button></form>
        @endif
    </div>
</div>
<div style="height:16px"></div>

<div class="card">
    <h2>🏢 Office & Google Maps</h2>
    <p class="muted">The office address is used as the origin for client-site distance calculations. Google Maps distance calculation requires a valid Google Maps API key. Without the key, the system still gives a Google Maps directions link.</p>
    <div class="grid cols-2">
        <div class="mini-card"><span>Office Address</span><strong>{{ $settings['company.office_address'] ?: 'Not configured' }}</strong></div>
        <div class="mini-card"><span>Google Maps API Key</span><strong>{{ $settings['maps.google_api_key'] ? 'Configured' : 'Not configured' }}</strong></div>
    </div>
</div>
<div style="height:16px"></div>

<div class="card">
    <h2>🕒 Attendance Work Policy</h2>
    <p class="muted">Default schedule: 06:00 to 15:00, Monday to Friday, excluding South African public holidays.</p>
    <form method="post" action="{{ route('settings.update') }}">
        @csrf @method('PUT')
        <div class="form-grid">
            <div class="form-row"><label>Work Start</label><input type="time" name="work_start" value="{{ $settings['attendance.work_start'] }}" required></div>
            <div class="form-row"><label>Work End</label><input type="time" name="work_end" value="{{ $settings['attendance.work_end'] }}" required></div>
            <div class="form-row" style="grid-column:1/-1">
                <label>Workdays</label>
                @php $selected = explode(',', $settings['attendance.workdays']); @endphp
                <div class="checkbox-grid">
                    @foreach(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)
                        <label class="check"><input type="checkbox" name="workdays[]" value="{{ $day }}" @checked(in_array($day,$selected,true))> {{ ucfirst($day) }}</label>
                    @endforeach
                </div>
            </div>
            <div class="form-row" style="grid-column:1/-1"><label>SA Holiday iCal URL</label><input name="sa_holidays_ics_url" value="{{ $settings['calendar.sa_holidays_ics_url'] }}" required></div>
            <div class="form-row" style="grid-column:1/-1"><label>🏢 Office Address</label><textarea name="office_address" placeholder="Example: 123 Main Road, Johannesburg, South Africa">{{ $settings['company.office_address'] }}</textarea></div>
            <div class="form-row" style="grid-column:1/-1"><label>🗺️ Google Maps API Key</label><input name="google_maps_api_key" value="{{ $settings['maps.google_api_key'] }}" autocomplete="off" placeholder="Required only for automatic distance calculation"></div>
        </div>
        @if(auth()->user()->hasPermission('settings.core'))<div class="actions"><button class="btn primary" type="submit">💾 Save Core Settings</button></div>@endif
    </form>
</div>
<style>
.mini-card{border:1px solid var(--line);border-radius:18px;background:rgba(15,23,42,.035);padding:14px}.mini-card span{display:block;color:var(--muted);font-size:12px}.mini-card strong{display:block;margin-top:6px;word-break:break-word}@media(max-width:720px){.mini-card{padding:12px}}
</style>
@endsection
