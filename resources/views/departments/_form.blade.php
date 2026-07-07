<div class="card" style="max-width:780px">
    <div class="form-row"><label>Name</label><input type="text" name="name" value="{{ old('name',$department->name) }}" required></div>
    <div class="form-row"><label>Status</label><select name="is_active" required><option value="1" @selected(old('is_active',$department->is_active ? 1 : 0)==1)>Active</option><option value="0" @selected(old('is_active',$department->is_active ? 1 : 0)==0)>Inactive</option></select></div>
    <div class="form-row"><label>Description</label><textarea name="description">{{ old('description',$department->description) }}</textarea></div>
    <div class="actions"><button class="btn primary" type="submit">Save Department</button><a class="btn" href="{{ route('departments.index') }}">Cancel</a></div>
</div>
