<div class="grid cols-2">
    <div class="card">
        <h2>Role Details</h2>
        <div class="form-row"><label>Name</label><input type="text" name="name" value="{{ old('name',$role->name) }}" required {{ $role->is_system ? 'readonly' : '' }}></div>
        <div class="form-row"><label>Access Level</label><input type="number" name="level" min="1" max="200" value="{{ old('level',$role->level ?? 10) }}" required></div>
        <div class="form-row"><label>Description</label><textarea name="description">{{ old('description',$role->description) }}</textarea></div>
        @if($role->is_system)<p class="muted small">System role slugs are protected. You can still change assigned permissions.</p>@endif
    </div>
    <div class="card">
        <h2>Permission Matrix</h2>
        <p class="muted small">Tick the areas this role can access. System Administrators are top-level platform owners; Directors now remain controlled by the permission matrix.</p>
        @foreach($permissions as $module => $items)
            <h3 style="margin-bottom:8px">{{ $module }}</h3>
            <div class="checkbox-grid" style="margin-bottom:14px">
                @foreach($items as $permission)
                    <label class="check"><input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array($permission->id, old('permission_ids',$selectedPermissions)))><span>{{ $permission->name }}<br><small class="muted">{{ $permission->slug }}</small></span></label>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
<div style="height:14px"></div>
<div class="actions"><button class="btn primary" type="submit">Save Role</button><a class="btn" href="{{ route('roles.index') }}">Cancel</a></div>
