@extends('layouts.app')
@section('title', $customer->company_name . ' | ISO Admin')
@section('page_title','Customer Profile')
@section('content')
@php
    $canManageCustomer = auth()->user()->hasPermission('clients.manage');
    $canManageSites = auth()->user()->hasPermission('customer_sites.manage');
    $canManageContacts = auth()->user()->hasPermission('customer_contacts.manage');
    $canManageInteractions = auth()->user()->hasPermission('customer_interactions.manage');
    $openFollowUps = $customer->interactions->filter(fn ($i) => $i->follow_up_at && $i->follow_up_at->isFuture());
    $dueFollowUps = $customer->interactions->filter(fn ($i) => $i->follow_up_at && !$i->follow_up_at->isFuture());
@endphp

<div class="card customer-hero">
    <div class="customer-avatar">{{ $customer->type_icon }}</div>
    <div>
        <h2>{{ $customer->company_name }}</h2>
        <p class="muted">{{ ucfirst($customer->customer_type ?: 'customer') }} · {{ $customer->industry ?: 'Industry not set' }}</p>
        <div class="actions">
            <span class="pill {{ $customer->status === 'active' ? '' : 'off' }}">{{ ucfirst($customer->status) }}</span>
            <span class="pill off">👤 {{ optional($customer->accountManager)->name ?? 'No account manager' }}</span>
            @if($dueFollowUps->count())<span class="pill" style="background:rgba(220,80,80,.18)">⏰ {{ $dueFollowUps->count() }} follow-up{{ $dueFollowUps->count() === 1 ? '' : 's' }} due</span>@endif
        </div>
    </div>
    <div class="actions right">
        <a class="btn" href="{{ route('customers.index') }}">Back to Customers</a>
        @if($canManageCustomer)<a class="btn primary" href="{{ route('customers.edit', $customer) }}">✏️ Edit</a>@endif
    </div>
</div>

<div class="customer-layout" style="margin-top:16px">
    <div class="card">
        <h2>🪪 Customer Details</h2>
        <div class="detail-grid">
            <div><span>Code</span><strong>{{ $customer->customer_code ?: 'Not set' }}</strong></div>
            <div><span>Contact Person</span><strong>{{ $customer->contact_person ?: 'Not set' }}</strong></div>
            <div><span>Phone</span><strong>{{ $customer->phone ?: 'Not set' }}</strong></div>
            <div><span>Email</span><strong>{{ $customer->email ?: 'Not set' }}</strong></div>
            <div><span>Website</span><strong>{{ $customer->website ?: 'Not set' }}</strong></div>
            <div><span>Account Manager</span><strong>{{ optional($customer->accountManager)->name ?? 'Not assigned' }}</strong></div>
            <div style="grid-column:1/-1"><span>Address</span><strong>{!! nl2br(e($customer->address ?: 'No address captured.')) !!}</strong></div>
            <div style="grid-column:1/-1"><span>Notes</span><strong>{!! nl2br(e($customer->notes ?: 'No notes captured.')) !!}</strong></div>
        </div>
    </div>

    <div class="card">
        <h2>📊 Snapshot</h2>
        <div class="snapshot-grid">
            <div><span>Sites</span><strong>{{ $customer->sites->count() }}</strong></div>
            <div><span>Contacts</span><strong>{{ $customer->companyContacts->count() + $customer->sites->sum(fn($site) => $site->contacts->count()) }}</strong></div>
            <div><span>Interactions</span><strong>{{ $customer->interactions->count() }}</strong></div>
            <div><span>Open Follow-ups</span><strong>{{ $openFollowUps->count() }}</strong></div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="actions" style="justify-content:space-between"><h2>📍 Sites</h2></div>
    <div class="mini-grid">
    @forelse($customer->sites as $site)
        <div class="mini-card site-card">
            <div class="site-head"><h3>🏭 {{ $site->name }}</h3><span class="pill {{ $site->status === 'inactive' ? 'off' : '' }}">{{ ucfirst($site->status) }}</span></div>
            <p class="muted small">{{ $site->site_code ?: 'No site code' }}</p>
            <p class="muted">📌 {{ $site->location ?: 'No location' }}</p>
            @if($site->notes)<p class="small">{{ $site->notes }}</p>@endif

            <div class="site-contacts">
                <h4>👥 Site Contacts</h4>
                @forelse($site->contacts->sortByDesc('is_primary') as $contact)
                    <div class="contact-chip">
                        <strong>{{ $contact->is_primary ? '⭐' : $contact->role_icon }} {{ $contact->name }}</strong>
                        <span>{{ $contact->contact_type ?: $contact->position ?: 'Contact' }}</span>
                        <small>{{ $contact->email ?: 'No email' }} · {{ $contact->mobile ?: $contact->phone ?: 'No phone' }}</small>
                        @if($canManageContacts)<form method="post" action="{{ route('customers.contacts.destroy',[$customer,$contact]) }}" onsubmit="return confirm('Remove this contact?')">@csrf @method('DELETE')<button class="btn danger" type="submit">🗑️</button></form>@endif
                    </div>
                @empty
                    <p class="muted small">No site contacts added.</p>
                @endforelse
            </div>

            @if($canManageContacts)
            <details class="compact-form">
                <summary class="btn">➕ Add Site Contact</summary>
                <form method="post" action="{{ route('customers.sites.contacts.store',[$customer,$site]) }}" class="visual-form">@csrf
                    <div class="form-grid">
                        <div class="form-row"><label>Name</label><input name="name" required></div>
                        <div class="form-row"><label>Role</label><select name="contact_type">@foreach($contactTypes as $ctype)<option value="{{ $ctype }}">{{ $ctype }}</option>@endforeach</select></div>
                        <div class="form-row"><label>Position</label><input name="position"></div>
                        <div class="form-row"><label>Email</label><input type="email" name="email"></div>
                        <div class="form-row"><label>Phone</label><input name="phone"></div>
                        <div class="form-row"><label>Mobile</label><input name="mobile"></div>
                        <div class="form-row"><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                        <label class="check"><input type="checkbox" name="is_primary" value="1"><span>⭐ Primary site contact</span></label>
                        <div class="form-row" style="grid-column:1/-1"><label>Notes</label><textarea name="notes"></textarea></div>
                    </div>
                    <button class="btn primary" type="submit">💾 Add Site Contact</button>
                </form>
            </details>
            @endif

            @if($canManageSites)
            <div class="actions" style="margin-top:12px">
                <details>
                    <summary class="btn">✏️ Edit Site</summary>
                    <form method="post" action="{{ route('customers.sites.update',[$customer,$site]) }}" class="visual-form compact-form">@csrf @method('PUT')
                        <div class="form-grid">
                            <div class="form-row"><label>Name</label><input name="name" value="{{ $site->name }}" required></div>
                            <div class="form-row"><label>Site Code</label><input name="site_code" value="{{ $site->site_code }}"></div>
                            <div class="form-row"><label>Status</label><select name="status"><option value="active" @selected($site->status==='active')>Active</option><option value="inactive" @selected($site->status==='inactive')>Inactive</option></select></div>
                            <div class="form-row" style="grid-column:1/-1"><label>Location</label><textarea name="location" required>{{ $site->location }}</textarea></div>
                            <div class="form-row" style="grid-column:1/-1"><label>Notes</label><textarea name="notes">{{ $site->notes }}</textarea></div>
                        </div>
                        <button class="btn primary" type="submit">💾 Save Site</button>
                    </form>
                </details>
                <form method="post" action="{{ route('customers.sites.destroy',[$customer,$site]) }}" onsubmit="return confirm('Remove this site?')">@csrf @method('DELETE')<button class="btn danger" type="submit">🗑️ Remove Site</button></form>
            </div>
            @endif
        </div>
    @empty
        <p class="muted">No sites added.</p>
    @endforelse
    </div>
    @if($canManageSites)
    <details style="margin-top:14px">
        <summary class="btn primary">➕ Add Site</summary>
        <form method="post" action="{{ route('customers.sites.store',$customer) }}" class="visual-form">@csrf
            <div class="form-grid">
                <div class="form-row"><label>Name</label><input name="name" required></div>
                <div class="form-row"><label>Site Code</label><input name="site_code"></div>
                <div class="form-row"><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                <div class="form-row" style="grid-column:1/-1"><label>📍 Site Location</label><textarea name="location" required placeholder="Street address, site name, town, province"></textarea></div>
                <div class="form-row" style="grid-column:1/-1"><label>Notes</label><textarea name="notes"></textarea></div>
            </div>
            <button class="btn primary" type="submit">💾 Add Site</button>
        </form>
    </details>
    @endif
</div>

<div class="card" style="margin-top:16px">
    <div class="actions" style="justify-content:space-between"><h2>👥 Company Contacts</h2></div>
    <p class="muted small">Use this section for company-level people such as accounts, procurement or head-office contacts. Site-specific people are added under each site.</p>
    <div class="mini-grid">
    @forelse($customer->companyContacts->sortByDesc('is_primary') as $contact)
        <div class="mini-card">
            <h3>{{ $contact->is_primary ? '⭐' : $contact->role_icon }} {{ $contact->name }}</h3>
            <p class="muted">{{ $contact->contact_type ?: 'Contact' }}{{ $contact->position ? ' · '.$contact->position : '' }}</p>
            <div class="contact-lines"><span>✉️ {{ $contact->email ?: 'No email' }}</span><span>☎️ {{ $contact->phone ?: 'No phone' }}</span><span>📱 {{ $contact->mobile ?: 'No mobile' }}</span></div>
            @if($contact->notes)<p class="small">{{ $contact->notes }}</p>@endif
            @if($canManageContacts)<form method="post" action="{{ route('customers.contacts.destroy',[$customer,$contact]) }}" onsubmit="return confirm('Remove this contact?')">@csrf @method('DELETE')<button class="btn danger" type="submit">🗑️ Remove Contact</button></form>@endif
        </div>
    @empty
        <p class="muted">No company-level contacts added.</p>
    @endforelse
    </div>
    @if($canManageContacts)
    <details style="margin-top:14px">
        <summary class="btn primary">➕ Add Company Contact</summary>
        <form method="post" action="{{ route('customers.contacts.store',$customer) }}" class="visual-form">@csrf
            <div class="form-grid">
                <div class="form-row"><label>Name</label><input name="name" required></div>
                <div class="form-row"><label>Role</label><select name="contact_type">@foreach($contactTypes as $ctype)<option value="{{ $ctype }}">{{ $ctype }}</option>@endforeach</select></div>
                <div class="form-row"><label>Position</label><input name="position"></div>
                <div class="form-row"><label>Email</label><input type="email" name="email"></div>
                <div class="form-row"><label>Phone</label><input name="phone"></div>
                <div class="form-row"><label>Mobile</label><input name="mobile"></div>
                <div class="form-row"><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                <label class="check"><input type="checkbox" name="is_primary" value="1"><span>⭐ Primary company contact</span></label>
                <div class="form-row" style="grid-column:1/-1"><label>Notes</label><textarea name="notes"></textarea></div>
            </div>
            <button class="btn primary" type="submit">💾 Add Contact</button>
        </form>
    </details>
    @endif
</div>

<div class="card" style="margin-top:16px">
    <div class="actions" style="justify-content:space-between"><h2>🗒️ Interactions &amp; Follow-ups</h2></div>
    <p class="muted small">Log calls, emails, meetings, site visits and notes. Set a follow-up date to track what's outstanding.</p>

    @if($canManageInteractions)
    <details style="margin-bottom:14px">
        <summary class="btn primary">➕ Log Interaction</summary>
        <form method="post" action="{{ route('customers.interactions.store',$customer) }}" class="visual-form">@csrf
            <div class="form-grid">
                <div class="form-row"><label>Type</label><select name="type">@foreach($interactionTypes as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                <div class="form-row"><label>Subject</label><input name="subject" required></div>
                <div class="form-row"><label>When</label><input type="datetime-local" name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}" required></div>
                <div class="form-row"><label>Follow-up (optional)</label><input type="datetime-local" name="follow_up_at"></div>
                <div class="form-row"><label>Related Site (optional)</label><select name="customer_site_id"><option value="">None</option>@foreach($customer->sites as $site)<option value="{{ $site->id }}">{{ $site->name }}</option>@endforeach</select></div>
                <div class="form-row"><label>Related Contact (optional)</label><select name="customer_contact_id"><option value="">None</option>@foreach($customer->contacts as $contact)<option value="{{ $contact->id }}">{{ $contact->name }}</option>@endforeach</select></div>
                <div class="form-row" style="grid-column:1/-1"><label>Notes</label><textarea name="notes"></textarea></div>
            </div>
            <button class="btn primary" type="submit">💾 Log Interaction</button>
        </form>
    </details>
    @endif

    <div class="timeline">
    @forelse($customer->interactions as $interaction)
        <div class="timeline-item">
            <div class="timeline-icon">{{ $interaction->type_icon }}</div>
            <div class="timeline-body">
                <div class="actions" style="justify-content:space-between">
                    <strong>{{ $interaction->subject }}</strong>
                    <span class="muted small">{{ $interaction->occurred_at->format('Y-m-d H:i') }}</span>
                </div>
                <p class="muted small">
                    {{ ucfirst(str_replace('_',' ', $interaction->type)) }}
                    @if($interaction->site) · 📍 {{ $interaction->site->name }} @endif
                    @if($interaction->contact) · 👤 {{ $interaction->contact->name }} @endif
                    @if($interaction->creator) · logged by {{ $interaction->creator->name }} @endif
                </p>
                @if($interaction->notes)<p class="small">{{ $interaction->notes }}</p>@endif
                @if($interaction->follow_up_at)
                    <span class="pill {{ $interaction->follow_up_at->isFuture() ? '' : 'off' }}" style="{{ $interaction->follow_up_at->isFuture() ? '' : 'background:rgba(220,80,80,.18)' }}">⏰ Follow up {{ $interaction->follow_up_at->format('Y-m-d H:i') }}{{ $interaction->follow_up_at->isFuture() ? '' : ' (due)' }}</span>
                @endif
                @if($canManageInteractions)
                    <form method="post" action="{{ route('customers.interactions.destroy',[$customer,$interaction]) }}" onsubmit="return confirm('Remove this interaction?')" style="margin-top:8px">@csrf @method('DELETE')<button class="btn danger" type="submit">🗑️ Remove</button></form>
                @endif
            </div>
        </div>
    @empty
        <p class="muted">No interactions logged yet.</p>
    @endforelse
    </div>
</div>

@if($canManageCustomer)
    <div class="card" style="margin-top:16px">
        <h3>Danger Zone</h3>
        <form method="post" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Delete this customer?')">
            @csrf @method('DELETE')
            <button class="btn danger" type="submit">Delete Customer</button>
        </form>
    </div>
@endif

<style>
.customer-hero{display:grid;grid-template-columns:auto 1fr auto;gap:16px;align-items:center}
.customer-avatar{width:68px;height:68px;border-radius:22px;background:linear-gradient(135deg,var(--brand),var(--brand2));display:grid;place-items:center;font-size:30px}
.customer-hero h2{margin:0 0 4px}
.customer-layout{display:grid;grid-template-columns:1.4fr .6fr;gap:16px}
.detail-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:10px}
.detail-grid div{border:1px solid var(--line);border-radius:14px;background:rgba(15,23,42,.03);padding:10px 12px}
.detail-grid span{display:block;color:var(--muted);font-size:12px}
.detail-grid strong{display:block;margin-top:4px;font-weight:600;word-break:break-word}
.snapshot-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.snapshot-grid div,.mini-card{border:1px solid var(--line);border-radius:18px;background:rgba(15,23,42,.03);padding:14px}
.snapshot-grid span{display:block;color:var(--muted);font-size:12px}
.snapshot-grid strong{display:block;margin-top:4px;font-size:22px;word-break:break-word}
.mini-grid{display:grid;gap:12px;margin-top:12px}
.mini-card h3{margin:0 0 6px}
.site-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
.contact-lines{display:grid;gap:5px;color:var(--muted);font-size:13px;margin:10px 0}
.visual-form{border:1px solid var(--line);border-radius:18px;background:rgba(15,23,42,.03);padding:14px;margin-top:8px}
.compact-form{margin-top:12px}
.site-contacts{margin-top:14px;border-top:1px solid var(--line);padding-top:12px}
.site-contacts h4{margin:0 0 10px}
.contact-chip{display:grid;grid-template-columns:1fr auto;gap:4px 8px;border:1px solid var(--line);border-radius:14px;padding:10px;margin-bottom:8px;background:rgba(15,23,42,.03)}
.contact-chip strong{grid-column:1/2}
.contact-chip span,.contact-chip small{grid-column:1/2;color:var(--muted)}
.contact-chip form{grid-row:1/4;grid-column:2/3}
.contact-chip .btn{padding:8px 10px}
.timeline{display:grid;gap:14px;margin-top:8px}
.timeline-item{display:grid;grid-template-columns:auto 1fr;gap:12px}
.timeline-icon{width:38px;height:38px;border-radius:14px;background:rgba(15,23,42,.06);display:grid;place-items:center;font-size:18px}
.timeline-body{border:1px solid var(--line);border-radius:16px;padding:12px;background:rgba(15,23,42,.025)}
@media(max-width:1200px){.customer-hero,.customer-layout{grid-template-columns:1fr}}
@media(max-width:720px){.snapshot-grid,.detail-grid{grid-template-columns:1fr}.form-grid{grid-template-columns:1fr}}
</style>
@endsection
