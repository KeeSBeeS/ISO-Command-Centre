<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        return view('roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderByDesc('level')->orderBy('name')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('roles.create', [
            'role' => new Role(['level' => 10, 'is_system' => false]),
            'permissions' => Permission::orderBy('module')->orderBy('name')->get()->groupBy('module'),
            'selectedPermissions' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'level' => $data['level'] ?? 10,
                'is_system' => false,
            ]);

            $role->permissions()->sync($data['permission_ids'] ?? []);
        });

        return redirect()->route('roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role)
    {
        $role->load('permissions');

        return view('roles.edit', [
            'role' => $role,
            'permissions' => Permission::orderBy('module')->orderBy('name')->get()->groupBy('module'),
            'selectedPermissions' => $role->permissions->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $data = $this->validated($request, $role->id);

        DB::transaction(function () use ($data, $role) {
            $role->update([
                'name' => $data['name'],
                'slug' => $role->is_system ? $role->slug : Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'level' => $data['level'] ?? $role->level,
            ]);

            $role->permissions()->sync($data['permission_ids'] ?? []);
        });

        return redirect()->route('roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->withErrors(['role' => 'System roles cannot be deleted.']);
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Role deleted.');
    }

    private function validated(Request $request, ?int $ignoreRoleId = null): array
    {
        $unique = 'unique:roles,name';
        if ($ignoreRoleId) {
            $unique .= ',' . $ignoreRoleId;
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255', $unique],
            'description' => ['nullable', 'string'],
            'level' => ['required', 'integer', 'min:1', 'max:200'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);
    }
}
