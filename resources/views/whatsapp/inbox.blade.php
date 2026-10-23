@extends('layouts.app')
@section('title','WhatsApp | ISO Admin')
@section('page_title','WhatsApp Business')
@section('content')
<style>
    .wa-layout{display:grid;grid-template-columns:340px 1fr;gap:16px;align-items:start}
    .wa-list{padding:0;display:flex;flex-direction:column;max-height:calc(100vh - 210px);min-height:420px}
    .wa-list-head{padding:14px;border-bottom:1px solid var(--line)}
    .wa-list-items{overflow:auto;flex:1}
    .wa-conv{display:flex;gap:11px;padding:12px 14px;border-bottom:1px solid var(--line);align-items:center}
    .wa-conv:hover,.wa-conv.active{background:rgba(255,255,255,.06)}
    .wa-conv.active{box-shadow:inset 3px 0 0 var(--brand2)}
    .wa-avatar{flex:0 0 40px;width:40px;height:40px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,var(--panel3),var(--brand3));font-weight:900}
    .wa-conv-main{min-width:0;flex:1}.wa-conv-main strong{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:14px}
    .wa-conv-main span{display:block;color:var(--muted);font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
    .wa-conv-side{text-align:right;font-size:11px;color:var(--muted2)}
    .wa-filters{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}.wa-filters a{font-size:12px;padding:5px 9px;border-radius:999px;border:1px solid var(--line);color:var(--muted)}.wa-filters a.active{color:#fff;border-color:var(--line-strong);background:rgba(18,163,116,.18)}
    .wa-thread{padding:0;display:flex;flex-direction:column;min-height:520px}
    .wa-thread-head{padding:14px 16px;border-bottom:1px solid var(--line);display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap}
    .wa-messages{flex:1;overflow:auto;padding:16px;display:flex;flex-direction:column;gap:8px;max-height:calc(100vh - 420px);min-height:300px;background:rgba(0,0,0,.12)}
    .wa-msg{max-width:min(560px,82%);padding:9px 12px;border-radius:16px;border:1px solid var(--line);background:rgba(255,255,255,.07);align-self:flex-start;word-wrap:break-word}
    .wa-msg.out{align-self:flex-end;background:linear-gradient(135deg,rgba(18,163,116,.32),rgba(14,127,99,.22));border-color:rgba(139,220,101,.25)}
    .wa-msg.failed{border-color:rgba(229,72,77,.5)}
    .wa-msg-tag{font-size:11px;color:var(--muted);margin-bottom:4px}
    .wa-msg-body{font-size:14px;line-height:1.45}
    .wa-msg-meta{font-size:11px;color:var(--muted2);margin-top:5px;text-align:right;display:flex;gap:4px;justify-content:flex-end}
    .wa-msg-error{margin-top:6px;font-size:12px;color:#ffd7d9;display:flex;gap:8px;align-items:center;justify-content:space-between}
    .wa-media{display:block;max-width:100%;max-height:300px;border-radius:12px;margin-bottom:6px}
    .wa-file{display:inline-block;padding:8px 10px;border-radius:12px;background:rgba(255,255,255,.08);margin-bottom:6px}
    .wa-composer{border-top:1px solid var(--line);padding:14px 16px}
    .wa-composer-tabs{display:flex;gap:6px;margin-bottom:10px}.wa-composer-tabs button{border:1px solid var(--line);background:transparent;color:var(--muted);padding:7px 11px;border-radius:12px;font-weight:850;cursor:pointer}.wa-composer-tabs button.active{color:#fff;background:rgba(18,163,116,.2);border-color:var(--line-strong)}
    .wa-pane{display:none}.wa-pane.active{display:block}
    .wa-composer textarea{min-height:70px}
    .wa-empty{display:grid;place-items:center;text-align:center;min-height:420px;color:var(--muted)}
    .wa-start summary{list-style:none;cursor:pointer}.wa-start summary::-webkit-details-marker{display:none}
    details.wa-contact-panel{border-top:1px solid var(--line);padding:12px 16px}details.wa-contact-panel summary{cursor:pointer;font-weight:850;color:var(--muted)}
    @media(max-width:960px){.wa-layout{grid-template-columns:1fr}.wa-list{max-height:none;min-height:0}.wa-layout.has-contact .wa-list{display:none}.wa-messages{max-height:60vh}}
</style>

@include('whatsapp.partials.tabs')

@unless($ready)
    <div class="alert warning">WhatsApp Business is not live yet. @if(auth()->user()->hasPermission('whatsapp.settings.view'))<a href="{{ route('whatsapp.settings') }}"><strong>Open WhatsApp Settings</strong></a> to connect the Cloud API.@else Ask the System Administrator to configure WhatsApp Settings.@endif</div>
@endunless

<div class="wa-layout {{ $contact ? 'has-contact' : '' }}">
    <aside class="card wa-list">
        <div class="wa-list-head">
            <form method="get" action="{{ route('whatsapp.inbox') }}">
                <input type="search" name="q" value="{{ $search }}" placeholder="Search name or number">
                <input type="hidden" name="filter" value="{{ $filter }}">
            </form>
            <div class="wa-filters">
                @foreach(['all' => 'All', 'unread' => 'Unread', 'open' => 'Open 24h', 'employees' => 'Employees'] as $key => $label)
                    <a href="{{ route('whatsapp.inbox', ['filter' => $key, 'q' => $search ?: null]) }}" class="{{ $filter === $key ? 'active' : '' }}">{{ $label }}</a>
                @endforeach
            </div>
            @if(auth()->user()->hasPermission('whatsapp.send'))
                <details class="wa-start" style="margin-top:12px" @if($errors->has('phone')) open @endif>
                    <summary class="btn primary full">＋ New conversation</summary>
                    <form method="post" action="{{ route('whatsapp.inbox.start') }}" class="wa-template-form" style="margin-top:12px">
                        @csrf
                        <div class="form-row">
                            <label>Employee</label>
                            <select name="user_id">
                                <option value="">— or enter a number below —</option>
                                @foreach($startEmployees as $employee)
                                    <option value="{{ $employee->id }}" @selected((string) old('user_id') === (string) $employee->id)>{{ $employee->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-grid">
                            <div class="form-row"><label>Phone</label><input type="tel" name="phone" value="{{ old('phone') }}" placeholder="082 123 4567"></div>
                            <div class="form-row"><label>Name</label><input type="text" name="name" value="{{ old('name') }}" placeholder="Optional"></div>
                        </div>
                        @include('whatsapp.partials.template_fields', ['templates' => $startTemplates, 'required' => false])
                        <p class="muted small">A business-initiated conversation must start with an approved template. Free-form replies unlock once the contact replies.</p>
                        <button class="btn primary full" type="submit">Start</button>
                    </form>
                </details>
            @endif
        </div>
        <div class="wa-list-items">
            @forelse($contacts as $item)
                <a href="{{ route('whatsapp.inbox.show', array_filter(['contact' => $item, 'filter' => $filter !== 'all' ? $filter : null, 'q' => $search ?: null])) }}" class="wa-conv {{ $contact && $contact->id === $item->id ? 'active' : '' }}">
                    <div class="wa-avatar">{{ mb_strtoupper(mb_substr($item->name_label, 0, 1)) }}</div>
                    <div class="wa-conv-main">
                        <strong>{{ $item->name_label }}</strong>
                        <span>{{ $item->last_message_preview }}</span>
                    </div>
                    <div class="wa-conv-side">
                        {{ optional($item->last_message_at)->isToday() ? $item->last_message_at->format('H:i') : optional($item->last_message_at)->format('d M') }}
                        @if($item->unread_count > 0)<div><span class="wa-badge">{{ $item->unread_count }}</span></div>@endif
                    </div>
                </a>
            @empty
                <p class="muted small" style="padding:14px">No conversations yet. Messages sent to the ISO WhatsApp number will appear here.</p>
            @endforelse
            @if($contacts->hasPages())<div style="padding:10px 14px">{{ $contacts->links() }}</div>@endif
        </div>
    </aside>

    <section class="card wa-thread">
        @if(!$contact)
            <div class="wa-empty">
                <div>
                    <div style="font-size:44px">💬</div>
                    <h2>Shared WhatsApp inbox</h2>
                    <p>Select a conversation, or start a new one with an approved template.</p>
                </div>
            </div>
        @else
            @php($windowOpen = $contact->isServiceWindowOpen())
            <div class="wa-thread-head">
                <div style="display:flex;gap:12px;align-items:center;min-width:0">
                    <a class="btn" href="{{ route('whatsapp.inbox') }}" style="padding:8px 10px;width:auto;flex:0 0 auto" aria-label="Back to conversations">←</a>
                    <div class="wa-avatar">{{ mb_strtoupper(mb_substr($contact->name_label, 0, 1)) }}</div>
                    <div style="min-width:0">
                        <strong>{{ $contact->name_label }}</strong>
                        <div class="muted small">{{ $contact->phone_label }}@if($contact->user) · <a href="{{ Route::has('employees.show') ? route('employees.show', $contact->user) : '#' }}">Employee profile</a>@endif</div>
                    </div>
                </div>
                <div class="actions">
                    @if($contact->isOptedOut())
                        <span class="pill warn">Opted out</span>
                    @elseif($contact->isOptedIn())
                        <span class="pill" title="{{ $contact->opt_in_source }}">Opted in</span>
                    @else
                        <span class="pill off">No opt-in</span>
                    @endif
                    <span class="pill {{ $windowOpen ? '' : 'off' }}" id="waWindowPill" title="WhatsApp allows free-form messages for 24 hours after the contact's last message.">
                        {{ $windowOpen ? 'Window open until ' . $contact->serviceWindowClosesAt()->format('d M H:i') : '24h window closed' }}
                    </span>
                </div>
            </div>

            <div class="wa-messages" id="waMessages" data-poll="{{ route('whatsapp.inbox.poll', $contact) }}" data-last="{{ $messages->max('id') ?? 0 }}">
                @forelse($messages as $message)
                    @include('whatsapp.partials.message', ['message' => $message])
                @empty
                    <p class="muted small" style="text-align:center">No messages yet.</p>
                @endforelse
            </div>

            @if(auth()->user()->hasPermission('whatsapp.send'))
                <div class="wa-composer">
                    <div class="wa-composer-tabs" role="tablist">
                        <button type="button" data-pane="text" class="{{ $windowOpen ? 'active' : '' }}">Message</button>
                        <button type="button" data-pane="template" class="{{ $windowOpen ? '' : 'active' }}">Template</button>
                        <button type="button" data-pane="media">Attachment</button>
                    </div>

                    <div class="wa-pane {{ $windowOpen ? 'active' : '' }}" data-pane="text">
                        @if($windowOpen)
                            <form method="post" action="{{ route('whatsapp.inbox.text', $contact) }}" id="waTextForm">
                                @csrf
                                <textarea name="body" maxlength="4096" placeholder="Type a message… (Ctrl+Enter to send)" required>{{ old('body') }}</textarea>
                                <div class="actions right" style="margin-top:8px"><button class="btn primary" type="submit" @disabled(!$ready)>Send</button></div>
                            </form>
                        @else
                            <p class="muted">The 24-hour service window is closed. Use an approved template to re-open the conversation; free-form messages are available again once {{ $contact->name_label }} replies.</p>
                        @endif
                    </div>

                    <div class="wa-pane {{ $windowOpen ? '' : 'active' }}" data-pane="template">
                        @if($contact->isOptedOut())
                            <p class="muted">This contact has opted out. Templates cannot be sent until they reply START.</p>
                        @else
                            <form method="post" action="{{ route('whatsapp.inbox.template', $contact) }}" class="wa-template-form">
                                @csrf
                                @include('whatsapp.partials.template_fields', ['templates' => $templates])
                                <div class="actions right"><button class="btn primary" type="submit" @disabled(!$ready || $templates->isEmpty())>Send Template</button></div>
                            </form>
                        @endif
                    </div>

                    <div class="wa-pane" data-pane="media">
                        @if($windowOpen)
                            <form method="post" action="{{ route('whatsapp.inbox.media', $contact) }}" enctype="multipart/form-data">
                                @csrf
                                <div class="form-row"><input type="file" name="file" required accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.mp4,.3gp,.mp3,.ogg,.aac,.amr,.m4a"></div>
                                <div class="form-row"><input type="text" name="caption" maxlength="1024" placeholder="Caption (optional)"></div>
                                <p class="muted small">Images up to 5 MB, video and audio up to 16 MB, documents up to 16 MB here.</p>
                                <div class="actions right"><button class="btn primary" type="submit" @disabled(!$ready)>Send Attachment</button></div>
                            </form>
                        @else
                            <p class="muted">Attachments can only be sent while the 24-hour service window is open.</p>
                        @endif
                    </div>
                </div>
            @endif

            @if(auth()->user()->hasPermission('whatsapp.contacts.manage'))
                <details class="wa-contact-panel">
                    <summary>Contact details &amp; consent</summary>
                    <form method="post" action="{{ route('whatsapp.inbox.contact', $contact) }}" style="margin-top:12px">
                        @csrf
                        @method('PUT')
                        <div class="form-grid">
                            <div class="form-row"><label>Display name</label><input type="text" name="display_name" value="{{ $contact->display_name }}" placeholder="{{ $contact->profile_name }}"></div>
                            <div class="form-row">
                                <label>Linked employee</label>
                                <select name="user_id">
                                    <option value="">Not an employee</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" @selected($contact->user_id === $employee->id)>{{ $employee->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <label>Consent</label>
                            <select name="consent">
                                <option value="keep">Keep current ({{ $contact->isOptedOut() ? 'opted out ' . $contact->opted_out_at->format('d M Y') : ($contact->isOptedIn() ? 'opted in ' . $contact->opted_in_at->format('d M Y') . ' · ' . $contact->opt_in_source : 'no opt-in recorded') }})</option>
                                <option value="opt_in">Record opt-in now</option>
                                <option value="opt_out">Record opt-out now</option>
                            </select>
                        </div>
                        <div class="form-row"><label>Notes</label><textarea name="notes" maxlength="3000">{{ $contact->notes }}</textarea></div>
                        <div class="actions right"><button class="btn" type="submit">Save Contact</button></div>
                    </form>
                </details>
            @endif
        @endif
    </section>
</div>

<script>
(function () {
    // Template parameter inputs
    var OPEN = '\x7b\x7b', CLOSE = '\x7d\x7d';
    function renderTemplateFields(form) {
        var select = form.querySelector('.wa-template-select');
        if (!select) return;
        var box = form.querySelector('.wa-template-params');
        var preview = form.querySelector('.wa-template-preview');
        var option = select.options[select.selectedIndex];
        box.innerHTML = '';
        preview.textContent = option && option.dataset.body ? option.dataset.body : '';
        if (!option || !option.value) return;
        var add = function (name, key, label, max) {
            var row = document.createElement('div');
            row.className = 'form-row';
            var lab = document.createElement('label');
            lab.textContent = label;
            var input = document.createElement('input');
            input.type = 'text';
            input.name = name;
            input.required = true;
            input.maxLength = max;
            input.placeholder = 'Value for ' + OPEN + key + CLOSE;
            row.appendChild(lab);
            row.appendChild(input);
            box.appendChild(row);
        };
        JSON.parse(option.dataset.headerPlaceholders || '[]').forEach(function (key) { add('header_params[]', key, 'Header ' + OPEN + key + CLOSE, 60); });
        JSON.parse(option.dataset.bodyPlaceholders || '[]').forEach(function (key) { add('params[]', key, 'Body ' + OPEN + key + CLOSE, 1024); });
    }
    document.querySelectorAll('.wa-template-form').forEach(function (form) {
        var select = form.querySelector('.wa-template-select');
        if (select) { select.addEventListener('change', function () { renderTemplateFields(form); }); renderTemplateFields(form); }
    });

    // Composer tabs
    document.querySelectorAll('.wa-composer-tabs button').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('.wa-composer-tabs button').forEach(function (b) { b.classList.toggle('active', b === button); });
            document.querySelectorAll('.wa-pane').forEach(function (pane) { pane.classList.toggle('active', pane.dataset.pane === button.dataset.pane); });
        });
    });

    var textForm = document.getElementById('waTextForm');
    if (textForm) {
        textForm.querySelector('textarea').addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) { event.preventDefault(); textForm.requestSubmit(); }
        });
    }

    // Live conversation polling
    var box = document.getElementById('waMessages');
    if (!box) return;
    box.scrollTop = box.scrollHeight;
    var busy = false;
    function poll() {
        if (busy || document.hidden) return;
        busy = true;
        fetch(box.dataset.poll + '?after=' + encodeURIComponent(box.dataset.last), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (!data) return;
                var nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 120;
                if (data.html) {
                    var holder = document.createElement('div');
                    holder.innerHTML = data.html;
                    Array.prototype.slice.call(holder.children).forEach(function (node) {
                        if (!document.getElementById(node.id)) box.appendChild(node);
                    });
                    if (nearBottom) box.scrollTop = box.scrollHeight;
                }
                box.dataset.last = data.last_id;
                Object.keys(data.statuses || {}).forEach(function (id) {
                    var el = document.querySelector('#wa-msg-' + id + ' .wa-status');
                    if (el && el.dataset.status !== data.statuses[id].status) {
                        el.dataset.status = data.statuses[id].status;
                        el.textContent = data.statuses[id].icon;
                        if (data.statuses[id].status === 'failed') el.closest('.wa-msg').classList.add('failed');
                    }
                });
            })
            .catch(function () {})
            .finally(function () { busy = false; });
    }
    setInterval(poll, 6000);
    document.addEventListener('visibilitychange', poll);
})();
</script>
@endsection
