@csrf
<div class="form-grid">
    <div class="form-row"><label>🏢 Client Name</label><input name="name" value="{{ old('name',$client->name) }}" required></div>
    <div class="form-row"><label>🏷️ Client Code</label><input name="client_code" value="{{ old('client_code',$client->client_code) }}"></div>
    <div class="form-row"><label>📌 Client Type</label><select name="client_type" required>
        @foreach(['customer'=>'Customer','prospect'=>'Prospect','supplier'=>'Supplier','partner'=>'Partner','other'=>'Other'] as $value=>$label)<option value="{{ $value }}" @selected(old('client_type',$client->client_type)===$value)>{{ $label }}</option>@endforeach
    </select></div>
    <div class="form-row"><label>⚙️ Industry</label><input name="industry" value="{{ old('industry',$client->industry) }}" placeholder="Mining, manufacturing, transport..."></div>
    <div class="form-row"><label>✅ Status</label><select name="status" required>
        @foreach(['active'=>'Active','prospect'=>'Prospect','inactive'=>'Inactive'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$client->status)===$value)>{{ $label }}</option>@endforeach
    </select></div>
    <div class="form-row"><label>👤 Account Manager</label><select name="account_manager_id"><option value="">Not assigned</option>@foreach($managers as $manager)<option value="{{ $manager->id }}" @selected((int)old('account_manager_id',$client->account_manager_id)===$manager->id)>{{ $manager->name }} · {{ $manager->email }}</option>@endforeach</select></div>
    <div class="form-row"><label>☎️ Main Phone</label><input name="phone" value="{{ old('phone',$client->phone) }}"></div>
    <div class="form-row"><label>✉️ Main Email</label><input type="email" name="email" value="{{ old('email',$client->email) }}"></div>
    <div class="form-row"><label>🌐 Website</label><input name="website" value="{{ old('website',$client->website) }}"></div>
    <div class="form-row"><label>📍 Legacy Distance (optional)</label><input type="number" step="0.1" min="0" name="distance_from_office_km" value="{{ old('distance_from_office_km',$client->distance_from_office_km) }}" placeholder="Use site distance where possible"></div>
    <div class="form-row" style="grid-column:1/-1"><label>📝 Notes</label><textarea name="notes">{{ old('notes',$client->notes) }}</textarea></div>
</div>
<div class="actions"><button class="btn primary" type="submit">💾 Save Client</button><a class="btn" href="{{ route('clients.index') }}">↩️ Back</a></div>
