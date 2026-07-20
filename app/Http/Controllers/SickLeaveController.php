<?php

namespace App\Http\Controllers;

use App\Models\EmployeeSickRecord;
use App\Models\User;
use App\Services\SickLeaveCycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SickLeaveController extends Controller
{
    public function index(Request $request, SickLeaveCycleService $sickCycle)
    {
        if (!Schema::hasTable('employee_sick_records')) {
            return redirect()->route('updates.v2_9_0')->withErrors(['sick_leave' => 'Apply Version 2.9.0 before using the sick leave register.']);
        }

        $employees = User::query()
            ->where(function ($query) {
                $query->where('status', 'active')->orWhereNull('status');
            })
            ->orderBy('name')
            ->get();

        $rows = $employees->map(function (User $employee) use ($sickCycle) {
            return [
                'employee' => $employee,
                'cycle' => $sickCycle->cycleFor($employee),
            ];
        });

        $records = EmployeeSickRecord::query()
            ->with(['employee', 'marker', 'removedBy'])
            ->orderByDesc('sick_from')
            ->paginate(25)
            ->withQueryString();

        return view('leave.sick', [
            'rows' => $rows,
            'records' => $records,
            'employees' => $employees,
            'cycleMonths' => $sickCycle->cycleMonths(),
            'entitlementDays' => $sickCycle->entitlementDays(),
            'canManage' => $request->user()->hasPermission('sick_leave.manage'),
        ]);
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('employee_sick_records')) {
            return redirect()->route('updates.v2_9_0');
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'sick_from' => ['required', 'date'],
            'sick_to' => ['required', 'date', 'after_or_equal:sick_from'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        EmployeeSickRecord::create([
            'user_id' => $data['user_id'],
            'marked_by' => $request->user()->id,
            'sick_from' => $data['sick_from'],
            'sick_to' => $data['sick_to'],
            'leave_type' => 'sick',
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        $employee = User::find($data['user_id']);

        return redirect()
            ->route('sick_leave.index')
            ->with('success', 'Sick leave recorded for ' . ($employee?->name ?: 'employee') . ' from ' . $data['sick_from'] . ' to ' . $data['sick_to'] . '.');
    }

    public function remove(Request $request, EmployeeSickRecord $sickRecord)
    {
        $data = $request->validate([
            'removal_reason' => ['required', 'string', 'max:500'],
        ]);

        $sickRecord->update([
            'status' => 'removed',
            'removal_reason' => $data['removal_reason'],
            'removed_at' => now(),
            'removed_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('sick_leave.index')
            ->with('success', 'Sick leave record removed. It no longer counts against the employee\'s cycle.');
    }
}
