@extends('layouts.app')
@section('title',$client->name.' | ISO Admin')
@section('page_title','Client Profile')
@section('content_class','content-wide')
@section('content')
<div class="client-hero card">
    <div class="client-avatar">🏢</div>
    <div>
        <h2>{{ $client->name }}</h2>
        <p class="muted">{{ ucfirst($client->client_type) }} · {{ $client->industry ?: 'Industry not set' }}</p>
        <div class="actions"><span class="pill {{ $client->status === 'inactive' ? 'off' : '' }}">{{ $client->status_icon }} {{ ucfirst($client->status) }}</span><span class="pill">📍 {{ $client->display_distance }}</span><span class="pill off">👤 {{ optional($client->accountManager)->name ?? 'No account manager' }}</span></div>
    </div>
    <div class="actions right client-actions">@if(auth()->user()->hasPermission('clients.edit'))<a class="btn primary" href="{{ route('clients.edit',$client) }}">✏️ Edit</a>@endif @if(auth()->user()->hasPermission('clients.delete'))<form method="post" action="{{ route('clients.destroy',$client) }}" onsubmit="return confirm('Mark this client inactive?')">@csrf @method('DELETE')<button class="btn danger" type="submit">⛔ Inactive</button></form>@endif</div>
</div>

<div class="client-layout" style="margin-top:16px">
    <div class="card">
        <h2>🪪 Client Details</h2>
        <div class="detail-grid">
            <div><span>Code</span><strong>{{ $client->client_code ?: 'Not set' }}</strong></div>
            <div><span>Nearest Distance</span><strong>{{ $client->display_distance }}</strong></div>
            <div><span>Phone</span><strong>{{ $client->phone ?: 'Not set' }}</strong></div>
            <div><span>Email</span><strong>{{ $client->email ?: 'Not set' }}</strong></div>
            <div><span>Website</span><strong>{{ $client->website ?: 'Not set' }}</strong></div>
            <div><span>Account Manager</span><strong>{{ optional($client->accountManager)->name ?? 'Not assigned' }}</strong></div>
            <div style="grid-column:1/-1"><span>Notes</span><strong>{{ $client->notes ?: 'No notes' }}</strong></div>
        </div>
    </div>

    <div class="card">
        <h2>📊 Client Snapshot</h2>
        <div class="snapshot-grid">
            <div><span>Sites</span><strong>{{ $client->sites->count() }}</strong></div>
            <div><span>All Contacts</span><strong>{{ $client->contacts->count() + $client->sites->sum(fn($site) => $site->contacts->count()) }}</strong></div>
            <div><span>Office Address</span><strong>{{ $officeAddress ?: 'Not set' }}</strong></div>
            <div><span>Maps</span><strong>{{ $mapsConfigured ? 'Enabled' : 'API key missing' }}</strong></div>
        </div>
    </div>
</div>

<div class="client-section-grid" style="margin-top:16px">
    <div class="card">
        <div class="actions" style="justify-content:space-between"><h2>📍 Sites</h2></div>
        <div class="mini-grid">
        @forelse($client->sites as $site)
            <div class="mini-card site-card">
                <div class="site-head"><h3>🏭 {{ $site->name }}</h3><span class="pill {{ $site->status === 'inactive' ? 'off' : '' }}">{{ ucfirst($site->status) }}</span></div>
                <p class="muted">📌 {{ $site->location ?: 'No location' }}</p>
                <div class="actions"><span class="pill">🚗 {{ $site->display_distance }}</span>@if($site->maps_distance_last_checked_at)<span class="pill off">Checked {{ $site->maps_distance_last_checked_at->format('Y-m-d') }}</span>@endif</div>
                <div class="actions map-actions">
                    @if($site->location)<a class="btn" target="_blank" rel="noopener" href="{{ $maps->directionsUrl($site->location) }}">🗺️ Open Maps</a>@endif
                    @if(auth()->user()->hasPermission('clients.sites.manage'))<form method="post" action="{{ route('clients.sites.calculate_distance',[$client,$site]) }}">@csrf<button class="btn primary" type="submit">📏 Calculate Distance</button></form>@endif
                </div>
                @if($site->notes)<p class="small">{{ $site->notes }}</p>@endif

                <div class="site-contacts">
                    <h4>👥 Site Contacts</h4>
                    @forelse($site->contacts->sortByDesc('is_primary') as $contact)
                        <div class="contact-chip"><strong>{{ $contact->is_primary ? '⭐' : $contact->role_icon }} {{ $contact->name }}</strong><span>{{ $contact->contact_type ?: $contact->position ?: 'Contact' }}</span><small>{{ $contact->email ?: 'No email' }} · {{ $contact->mobile ?: $contact->phone ?: 'No phone' }}</small>@if(auth()->user()->hasPermission('clients.contacts.manage'))<form method="post" action="{{ route('clients.contacts.destroy',[$client,$contact]) }}" onsubmit="return confirm('Remove this contact?')">@csrf @method('DELETE')<button class="btn danger" type="submit">🗑️</button></form>@endif</div>
                    @empty
                        <p class="muted small">No site contacts added.</p>
                    @endforelse
                </div>

                @if(auth()->user()->hasPermission('clients.contacts.manage'))
                <form method="post" action="{{ route('clients.sites.contacts.store',[$client,$site]) }}" class="visual-form compact-form">@csrf
                    <h4>➕ Add Site Contact</h4>
                    <div class="form-grid"><div class="form-row"><label>Name</label><input name="name" required></div><div class="form-row"><label>Role</label><select name="contact_type">@foreach($contactTypes as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div><div class="form-row"><label>Position</label><input name="position"></div><div class="form-row"><label>Email</label><input type="email" name="email"></div><div class="form-row"><label>Phone</label><input name="phone"></div><div class="form-row"><label>Mobile</label><input name="mobile"></div><div class="form-row"><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div><label class="check"><input type="checkbox" name="is_primary" value="1"><span>⭐ Primary site contact</span></label><div class="form-row" style="grid-column:1/-1"><label>Notes</label><textarea name="notes"></textarea></div></div><button class="btn primary" type="submit">💾 Add Site Contact</button>
                </form>
                @endif

                @if(auth()->user()->hasPermission('clients.sites.manage'))<form method="post" action="{{ route('clients.sites.destroy',[$client,$site]) }}" onsubmit="return confirm('Remove this site?')">@csrf @method('DELETE')<button class="btn danger" type="submit">🗑️ Remove Site</button></form>@endif
            </div>
        @empty
            <p class="muted">No sites added.</p>
        @endforelse
        </div>
        @if(auth()->user()->hasPermission('clients.sites.manage'))
        <form method="post" action="{{ route('clients.sites.store',$client) }}" class="visual-form" style="margin-top:14px">@csrf
            <h3>➕ Add Site</h3><div class="form-grid"><div class="form-row"><label>Name</label><input name="name" required></div><div class="form-row"><label>Site Code</label><input name="site_code"></div><div class="form-row"><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div><div class="form-row"><label>Manual Distance from Office (km)</label><input type="number" step="0.1" min="0" name="distance_from_office_km"></div><div class="form-row" style="grid-column:1/-1"><label>📍 Site Location</label><textarea name="location" required placeholder="Street address, site name, town, province"></textarea></div><div class="form-row" style="grid-column:1/-1"><label>Notes</label><textarea name="notes"></textarea></div></div><button class="btn primary" type="submit">💾 Add Site</button>
        </form>
        @endif
    </div>

    <div class="card">
        <div class="actions" style="justify-content:space-between"><h2>👥 Client Contacts</h2></div>
        <p class="muted small">Use this section for client-level people such as accounts, procurement, engineering management or head-office contacts. Site-specific people are added under each site.</p>
        <div class="mini-grid">
        @forelse($client->contacts->sortByDesc('is_primary') as $contact)
            <div class="mini-card"><h3>{{ $contact->is_primary ? '⭐' : $contact->role_icon }} {{ $contact->name }}</h3><p class="muted">{{ $contact->contact_type ?: 'Contact' }}{{ $contact->position ? ' · '.$contact->position : '' }}</p><div class="contact-lines"><span>✉️ {{ $contact->email ?: 'No email' }}</span><span>☎️ {{ $contact->phone ?: 'No phone' }}</span><span>📱 {{ $contact->mobile ?: 'No mobile' }}</span></div>@if($contact->notes)<p class="small">{{ $contact->notes }}</p>@endif @if(auth()->user()->hasPermission('clients.contacts.manage'))<form method="post" action="{{ route('clients.contacts.destroy',[$client,$contact]) }}" onsubmit="return confirm('Remove this contact?')">@csrf @method('DELETE')<button class="btn danger" type="submit">🗑️ Remove Contact</button></form>@endif</div>
        @empty
            <p class="muted">No client-level contacts added.</p>
        @endforelse
        </div>
        @if(auth()->user()->hasPermission('clients.contacts.manage'))
        <form method="post" action="{{ route('clients.contacts.store',$client) }}" class="visual-form" style="margin-top:14px">@csrf
            <h3>➕ Add Client Contact</h3><div class="form-grid"><div class="form-row"><label>Name</label><input name="name" required></div><div class="form-row"><label>Role</label><select name="contact_type">@foreach($contactTypes as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div><div class="form-row"><label>Position</label><input name="position"></div><div class="form-row"><label>Email</label><input type="email" name="email"></div><div class="form-row"><label>Phone</label><input name="phone"></div><div class="form-row"><label>Mobile</label><input name="mobile"></div><div class="form-row"><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div><label class="check"><input type="checkbox" name="is_primary" value="1"><span>⭐ Primary client contact</span></label><div class="form-row" style="grid-column:1/-1"><label>Notes</label><textarea name="notes"></textarea></div></div><button class="btn primary" type="submit">💾 Add Contact</button>
        </form>
        @endif
    </div>
</div>
<style>
.client-hero{display:grid;grid-template-columns:auto 1fr auto;gap:16px;align-items:center}.client-avatar{width:68px;height:68px;border-radius:22px;background:linear-gradient(135deg,var(--brand),var(--brand2));display:grid;place-items:center;font-size:30px}.client-hero h2{margin:0 0 4px}.client-layout{display:grid;grid-template-columns:1.4fr .6fr;gap:16px}.client-section-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:16px}.snapshot-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.snapshot-grid div,.mini-card{border:1px solid var(--line);border-radius:18px;background:rgba(15,23,42,.035);padding:14px}.snapshot-grid span{display:block;color:var(--muted);font-size:12px}.snapshot-grid strong{display:block;margin-top:4px;font-size:22px;word-break:break-word}.mini-grid{display:grid;gap:12px}.mini-card h3{margin:0 0 6px}.site-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.contact-lines{display:grid;gap:5px;color:var(--muted);font-size:13px;margin:10px 0}.visual-form{border:1px solid var(--line);border-radius:18px;background:rgba(15,23,42,.035);padding:14px}.compact-form{margin-top:12px}.site-contacts{margin-top:14px;border-top:1px solid var(--line);padding-top:12px}.site-contacts h4{margin:0 0 10px}.contact-chip{display:grid;grid-template-columns:1fr auto;gap:4px 8px;border:1px solid var(--line);border-radius:14px;padding:10px;margin-bottom:8px;background:rgba(15,23,42,.035)}.contact-chip strong{grid-column:1/2}.contact-chip span,.contact-chip small{grid-column:1/2;color:var(--muted)}.contact-chip form{grid-row:1/4;grid-column:2/3}.contact-chip .btn{padding:8px 10px}.map-actions form{display:inline-flex}@media(min-width:1600px){.client-section-grid{grid-template-columns:1.25fr .75fr}.mini-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.site-card{grid-column:span 1}}@media(max-width:1200px){.client-hero,.client-layout,.client-section-grid{grid-template-columns:1fr}.client-actions{justify-content:flex-start}.snapshot-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:720px){.snapshot-grid,.detail-grid{grid-template-columns:1fr}.client-actions .btn,.client-actions form,.map-actions .btn,.map-actions form{width:100%}.client-actions form .btn,.map-actions form .btn{width:100%}.contact-chip{grid-template-columns:1fr}.contact-chip form{grid-row:auto;grid-column:auto}.form-grid{grid-template-columns:1fr}}
</style>
@endsection
