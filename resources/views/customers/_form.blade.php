<div class="card" style="max-width:960px">
    <div class="grid cols-2">
        <div class="form-row"><label>Company / Customer Name</label><input type="text" name="company_name" value="{{ old('company_name', $customer->company_name) }}" required></div>
        <div class="form-row"><label>Customer Code</label><input type="text" name="customer_code" value="{{ old('customer_code', $customer->customer_code) }}" placeholder="Optional internal account code"></div>
        <div class="form-row"><label>Type</label><select name="customer_type" required>
            @foreach(['customer'=>'Customer','prospect'=>'Prospect','supplier'=>'Supplier','partner'=>'Partner','other'=>'Other'] as $value=>$label)
                <option value="{{ $value }}" @selected(old('customer_type', $customer->customer_type ?: 'customer') === $value)>{{ $label }}</option>
            @endforeach
        </select></div>
        <div class="form-row"><label>Industry</label><input type="text" name="industry" value="{{ old('industry', $customer->industry) }}" placeholder="Mining, manufacturing, transport..."></div>
        <div class="form-row"><label>Status</label><select name="status" required><option value="active" @selected(old('status', $customer->status ?: 'active') === 'active')>Active</option><option value="inactive" @selected(old('status', $customer->status) === 'inactive')>Inactive</option></select></div>
        <div class="form-row"><label>Account Manager</label><select name="account_manager_id"><option value="">Not assigned</option>@foreach($managers as $manager)<option value="{{ $manager->id }}" @selected((int) old('account_manager_id', $customer->account_manager_id) === $manager->id)>{{ $manager->name }} · {{ $manager->email }}</option>@endforeach</select></div>
        <div class="form-row"><label>Contact Person</label><input type="text" name="contact_person" value="{{ old('contact_person', $customer->contact_person) }}"></div>
        <div class="form-row"><label>Email</label><input type="email" name="email" value="{{ old('email', $customer->email) }}"></div>
        <div class="form-row"><label>Phone</label><input type="text" name="phone" value="{{ old('phone', $customer->phone) }}"></div>
        <div class="form-row"><label>Website</label><input type="text" name="website" value="{{ old('website', $customer->website) }}" placeholder="https://"></div>
    </div>
    <div class="form-row"><label>Address</label><textarea name="address" rows="3">{{ old('address', $customer->address) }}</textarea></div>
    <div class="form-row"><label>Notes</label><textarea name="notes" rows="4">{{ old('notes', $customer->notes) }}</textarea></div>
    <div class="actions"><button class="btn primary" type="submit">Save Customer</button><a class="btn" href="{{ route('customers.index') }}">Cancel</a></div>
</div>
