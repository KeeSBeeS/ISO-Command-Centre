<div class="grid cols-2">
    <div class="card">
        <h2>Employee Details</h2>
        <div class="form-grid">
            <div class="form-row"><label>Name</label><input type="text" name="name" value="{{ old('name',$employee->name) }}" required></div>
            <div class="form-row"><label>Email</label><input type="email" name="email" value="{{ old('email',$employee->email) }}" required></div>
            <div class="form-row"><label>Attendance CSV Name</label><input type="text" name="attendance_name" value="{{ old('attendance_name',$employee->attendance_name) }}" placeholder="Leave blank if same as Name"><small class="muted">Must match the Name column in the attendance CSV, e.g. Craig or Willem.</small></div>
            <div class="form-row"><label>Employee Code</label><input type="text" name="employee_code" value="{{ old('employee_code',$employee->employee_code) }}"></div>
            <div class="form-row"><label>Employee Number</label><input type="text" name="employee_number" value="{{ old('employee_number',optional($profile)->employee_number) }}"></div>
            <div class="form-row"><label>Job Title</label><input type="text" name="job_title" value="{{ old('job_title',optional($profile)->job_title ?? $employee->position) }}"></div>
            <div class="form-row"><label>Status</label><select name="status" required><option value="active" @selected(old('status',$employee->status)==='active')>Active</option><option value="inactive" @selected(old('status',$employee->status)==='inactive')>Inactive</option></select></div>
            <div class="form-row"><label>Phone</label><input type="text" name="phone" value="{{ old('phone',optional($profile)->phone ?? $employee->phone) }}"></div>
            <div class="form-row"><label>Mobile</label><input type="text" name="mobile" value="{{ old('mobile',optional($profile)->mobile) }}"></div>
            <div class="form-row"><label>Started At</label><input type="date" name="started_at" value="{{ old('started_at',optional(optional($profile)->started_at)->format('Y-m-d')) }}"></div>
            <div class="form-row"><label>Emergency Contact</label><input type="text" name="emergency_contact" value="{{ old('emergency_contact',optional($profile)->emergency_contact) }}"></div>
        </div>
        <div class="form-row"><label>Notes</label><textarea name="notes">{{ old('notes',optional($profile)->notes) }}</textarea></div>
        <div class="form-row">
            <label>Password {{ $employee->exists ? '(leave blank to keep current)' : '(leave blank to auto-generate)' }}</label>
            <div style="display:grid;grid-template-columns:1fr auto;gap:8px">
                <input id="employee-password" type="text" name="password" autocomplete="new-password" placeholder="Temporary password">
                <button class="btn" type="button" onclick="generateEmployeePassword()">Generate</button>
            </div>
            <small class="muted">On new user creation the login details are emailed to the user and the user must change this password on first login.</small>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h2>Departments</h2>
            <div class="checkbox-grid">
                @foreach($departments as $department)
                    <label class="check"><input type="checkbox" name="department_ids[]" value="{{ $department->id }}" @checked(in_array($department->id, old('department_ids',$selectedDepartments)))><span>{{ $department->name }}</span></label>
                @endforeach
            </div>
        </div>
        <div class="card">
            <h2>Roles</h2>
            <div class="checkbox-grid">
                @foreach($roles as $role)
                    <label class="check"><input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(in_array($role->id, old('role_ids',$selectedRoles)))><span>{{ $role->name }}<br><small class="muted">Level {{ $role->level }}</small></span></label>
                @endforeach
            </div>
        </div>

        @if(auth()->user()->hasPermission('permissions.manage') && isset($permissions))
        <div class="card">
            <h2>Direct User Permissions</h2>
            <p class="muted small">Use this only for exceptions where one person needs access outside their role. Role permissions should remain the normal access method.</p>
            @foreach($permissions as $module => $items)
                <h3 style="margin-bottom:8px">{{ $module }}</h3>
                <div class="checkbox-grid" style="margin-bottom:14px">
                    @foreach($items as $permission)
                        <label class="check"><input type="checkbox" name="direct_permission_ids[]" value="{{ $permission->id }}" @checked(in_array($permission->id, old('direct_permission_ids',$selectedDirectPermissions ?? [])))><span>{{ $permission->name }}<br><small class="muted">{{ $permission->slug }}</small></span></label>
                    @endforeach
                </div>
            @endforeach
        </div>
        @endif

    </div>
</div>
<div style="height:14px"></div>
<div class="actions"><button class="btn primary" type="submit">Save Employee</button><a class="btn" href="{{ route('employees.index') }}">Cancel</a></div>
<script>
function generateEmployeePassword(){
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    var out = '';
    if (window.crypto && window.crypto.getRandomValues) {
        var values = new Uint32Array(14);
        window.crypto.getRandomValues(values);
        for (var i = 0; i < values.length; i++) out += chars[values[i] % chars.length];
    } else {
        for (var j = 0; j < 14; j++) out += chars[Math.floor(Math.random() * chars.length)];
    }
    document.getElementById('employee-password').value = out;
}
</script>
