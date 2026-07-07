<div class="grid cols-2">
    <div class="card">
        <h2>Leave Type Details</h2>
        <div class="form-row"><label>Name</label><input type="text" name="name" value="{{ old('name',$leaveType->name) }}" required></div>
        <div class="form-row"><label>Code</label><input type="text" name="code" value="{{ old('code',$leaveType->code) }}" required placeholder="ANNUAL"></div>
        <div class="form-row"><label>Sort Order</label><input type="number" name="sort_order" min="0" value="{{ old('sort_order',$leaveType->sort_order ?? 10) }}"></div>
        <div class="form-row"><label>Description</label><textarea name="description">{{ old('description',$leaveType->description) }}</textarea></div>
    </div>
    <div class="card">
        <h2>Leave Allocation Rules</h2>
        <label class="check"><input type="checkbox" name="is_deductible" value="1" @checked(old('is_deductible',$leaveType->is_deductible ?? true))><span>Deduct this leave type from allocated leave</span></label>
        <div style="height:10px"></div>
        <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$leaveType->is_active ?? true))><span>Active and selectable</span></label>
        <p class="muted small">Example: Annual Leave usually deducts from allocation. Unpaid Leave or Special Leave may be non-deductible depending on company policy.</p>
    </div>
</div>
<div style="height:14px"></div>
<div class="actions"><button class="btn primary" type="submit">Save Leave Type</button><a class="btn" href="{{ route('leave_types.index') }}">Cancel</a></div>
