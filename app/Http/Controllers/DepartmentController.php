<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DepartmentController extends Controller
{
    public function index()
    {
        return view('departments.index', [
            'departments' => Department::withCount('users')->orderBy('name')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('departments.create', ['department' => new Department(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']);

        Department::create($data);

        return redirect()->route('departments.index')->with('success', 'Department created.');
    }

    public function edit(Department $department)
    {
        return view('departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $data = $this->validated($request, $department->id);
        $data['slug'] = Str::slug($data['name']);

        $department->update($data);

        return redirect()->route('departments.index')->with('success', 'Department updated.');
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return redirect()->route('departments.index')->with('success', 'Department deleted.');
    }

    private function validated(Request $request, ?int $ignoreDepartmentId = null): array
    {
        $unique = 'unique:departments,name';
        if ($ignoreDepartmentId) {
            $unique .= ',' . $ignoreDepartmentId;
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255', $unique],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
