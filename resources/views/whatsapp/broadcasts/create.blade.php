@extends('layouts.app')
@section('title','New WhatsApp Broadcast | ISO Admin')
@section('page_title','New WhatsApp Broadcast')
@section('content')
@include('whatsapp.partials.tabs')
@unless($ready)
    <div class="alert warning">WhatsApp Business is disabled or not configured. Broadcasts cannot be sent yet.</div>
@endunless
@if($requireOptIn)
    <div class="alert">Only employees with a recorded WhatsApp opt-in are included. Opted-out contacts are always excluded.</div>
@endif
<form method="post" action="{{ route('whatsapp.broadcasts.store') }}" class="wa-template-form" id="waBroadcastForm">
    @csrf
    <div class="grid cols-2">
        <div class="card">
            <h2>Message</h2>
            <div class="form-row"><label>Broadcast name</label><input type="text" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="e.g. Site shutdown notice"></div>
            @include('whatsapp.partials.template_fields', ['templates' => $templates])
            <p class="muted small">Use <code>{name}</code> or <code>{first_name}</code> inside a value to personalise it per recipient.</p>
        </div>
        <div class="card">
            <h2>Audience</h2>
            <div class="form-row">
                <label>Send to</label>
                <select name="audience_type" id="waAudience">
                    <option value="employees" @selected(old('audience_type') === 'employees')>All active employees</option>
                    <option value="department" @selected(old('audience_type') === 'department')>Selected departments</option>
                    <option value="role" @selected(old('audience_type') === 'role')>Selected roles</option>
                </select>
            </div>
            <div class="form-row wa-aud" data-aud="department">
                <label>Departments</label>
                <div class="checkbox-grid">
                    @foreach($departments as $department)
                        <label class="check"><input type="checkbox" name="department_ids[]" value="{{ $department->id }}" @checked(in_array($department->id, old('department_ids', [])))><span>{{ $department->name }}</span></label>
                    @endforeach
                </div>
            </div>
            <div class="form-row wa-aud" data-aud="role">
                <label>Roles</label>
                <div class="checkbox-grid">
                    @foreach($roles as $role)
                        <label class="check"><input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(in_array($role->id, old('role_ids', [])))><span>{{ $role->name }}</span></label>
                    @endforeach
                </div>
            </div>
            <div class="soft-divider"></div>
            <div class="actions">
                <button class="btn" type="button" id="waPreview">Count recipients</button>
                <span class="muted small" id="waPreviewResult"></span>
            </div>
        </div>
    </div>
    <div style="height:16px"></div>
    <div class="actions right">
        <a class="btn" href="{{ route('whatsapp.broadcasts.index') }}">Cancel</a>
        <button class="btn primary" type="submit" @disabled(!$ready || $templates->isEmpty()) onclick="return confirm('Send this broadcast now?')">Send Broadcast</button>
    </div>
</form>
<script>
(function () {
    var OPEN = '\x7b\x7b', CLOSE = '\x7d\x7d';
    var form = document.getElementById('waBroadcastForm');
    var select = form.querySelector('.wa-template-select');
    function renderFields() {
        var box = form.querySelector('.wa-template-params');
        var option = select.options[select.selectedIndex];
        box.innerHTML = '';
        form.querySelector('.wa-template-preview').textContent = option && option.dataset.body ? option.dataset.body : '';
        if (!option || !option.value) return;
        JSON.parse(option.dataset.bodyPlaceholders || '[]').forEach(function (key) {
            var row = document.createElement('div'); row.className = 'form-row';
            var label = document.createElement('label'); label.textContent = 'Body ' + OPEN + key + CLOSE;
            var input = document.createElement('input'); input.type = 'text'; input.name = 'params[]'; input.required = true; input.maxLength = 1024;
            row.appendChild(label); row.appendChild(input); box.appendChild(row);
        });
    }
    select.addEventListener('change', renderFields); renderFields();

    var audience = document.getElementById('waAudience');
    function toggleAudience() {
        document.querySelectorAll('.wa-aud').forEach(function (el) { el.style.display = el.dataset.aud === audience.value ? '' : 'none'; });
    }
    audience.addEventListener('change', toggleAudience); toggleAudience();

    document.getElementById('waPreview').addEventListener('click', function () {
        var data = new FormData(form);
        var out = document.getElementById('waPreviewResult');
        out.textContent = 'Counting…';
        fetch(@json(route('whatsapp.broadcasts.preview')), { method: 'POST', body: data, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
            .then(function (res) {
                if (!res.ok) { out.textContent = res.body.message || 'Select at least one department or role.'; return; }
                out.textContent = res.body.count + ' reachable recipient(s)' + (res.body.names.length ? ': ' + res.body.names.join(', ') + (res.body.count > res.body.names.length ? '…' : '') : '');
            })
            .catch(function () { out.textContent = 'Could not count recipients.'; });
    });
})();
</script>
@endsection
