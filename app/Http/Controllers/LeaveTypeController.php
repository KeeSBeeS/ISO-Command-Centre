<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LeaveTypeController extends Controller
{
    public function index()
    {
        abort_unless(Schema::hasTable('leave_types'), 404, 'Run the v2.5 update first.');

        return view('settings.leave_types.index', [
            'leaveTypes' => LeaveType::orderBy('sort_order')->orderBy('name')->paginate(30),
        ]);
    }

    public function create()
    {
        abort_unless(Schema::hasTable('leave_types'), 404, 'Run the v2.5 update first.');

        return view('settings.leave_types.create', [
            'leaveType' => new LeaveType(['is_active' => true, 'is_deductible' => true, 'sort_order' => 10]),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Schema::hasTable('leave_types'), 404, 'Run the v2.5 update first.');

        $data = $this->validated($request);
        $data['is_deductible'] = $request->boolean('is_deductible');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 10);
        LeaveType::create($data);

        return redirect()->route('leave_types.index')->with('success', 'Leave type created.');
    }

    public function edit(LeaveType $leaveType)
    {
        return view('settings.leave_types.edit', compact('leaveType'));
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $data = $this->validated($request, $leaveType->id);
        $data['is_deductible'] = $request->boolean('is_deductible');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 10);
        $leaveType->update($data);

        return redirect()->route('leave_types.index')->with('success', 'Leave type updated.');
    }

    public function destroy(LeaveType $leaveType)
    {
        $leaveType->update(['is_active' => false]);

        return redirect()->route('leave_types.index')->with('success', 'Leave type marked inactive.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('leave_types', 'code')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:3000'],
            'is_deductible' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }
}
