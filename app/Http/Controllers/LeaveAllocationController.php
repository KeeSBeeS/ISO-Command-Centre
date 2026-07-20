<?php

namespace App\Http\Controllers;

use App\Models\EmployeeLeaveAllocation;
use App\Models\User;
use App\Services\LeaveBalanceService;
use App\Services\SickLeaveCycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LeaveAllocationController extends Controller
{
    public function index(Request $request, LeaveBalanceService $leaveBalance, SickLeaveCycleService $sickCycle)
    {
        if (!Schema::hasTable('employee_leave_allocations')) {
            return redirect()->route('updates.v2_9_0')->withErrors(['leave_allocations' => 'Apply Version 2.9.0 before managing leave allocations.']);
        }

        $year = (int) ($request->input('year') ?: now()->format('Y'));
        $year = max(2000, min(2100, $year));

        $employees = User::query()
            ->where(function ($query) {
                $query->where('status', 'active')->orWhereNull('status');
            })
            ->orderBy('name')
            ->get();

        $rows = $employees->map(function (User $employee) use ($leaveBalance, $sickCycle, $year) {
            return [
                'employee' => $employee,
                'summary' => $leaveBalance->summary($employee, $year),
                'sick_cycle' => $sickCycle->cycleFor($employee),
            ];
        });

        return view('leave.allocations', [
            'rows' => $rows,
            'year' => $year,
            'canManage' => $request->user()->hasPermission('leave_allocations.manage'),
        ]);
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('employee_leave_allocations')) {
            return redirect()->route('updates.v2_9_0');
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'allocated_days' => ['required', 'numeric', 'min:0', 'max:365'],
            'carried_over_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $allocation = EmployeeLeaveAllocation::updateOrCreate(
            ['user_id' => $data['user_id'], 'year' => $data['year']],
            [
                'allocated_days' => $data['allocated_days'],
                'carried_over_days' => $data['carried_over_days'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'allocated_by' => $request->user()->id,
            ]
        );

        $employee = User::find($data['user_id']);

        return redirect()
            ->route('leave_allocations.index', ['year' => $data['year']])
            ->with('success', ($employee?->name ?: 'Employee') . ' now has ' . rtrim(rtrim(number_format((float) $allocation->allocated_days, 2), '0'), '.') . ' paid leave day(s) allocated for ' . $data['year'] . '.');
    }
}
