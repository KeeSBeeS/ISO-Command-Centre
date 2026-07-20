<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class EmployeeComplianceController extends Controller
{
    public function index(Request $request)
    {
        $employees = User::where('status', 'active')
            ->with(['departments', 'documents'])
            ->orderBy('name')
            ->get()
            ->map(function (User $employee) {
                $activeDocuments = $employee->documents->where('status', 'active');
                $expiredCount = $activeDocuments->where('expiry_state', 'expired')->count();
                $hasNoDocuments = $employee->documents->isEmpty();

                $employee->compliance = [
                    'total_documents' => $employee->documents->count(),
                    'expired_count' => $expiredCount,
                    'reminder_due_count' => $activeDocuments->where('expiry_state', 'reminder-due')->count(),
                    'has_no_documents' => $hasNoDocuments,
                    'is_compliant' => $expiredCount === 0 && !$hasNoDocuments,
                ];

                return $employee;
            });

        $summary = [
            'total_active_employees' => $employees->count(),
            'compliant_employees' => $employees->filter(fn (User $employee) => $employee->compliance['is_compliant'])->count(),
            'missing_documents_employees' => $employees->filter(fn (User $employee) => $employee->compliance['has_no_documents'])->count(),
            'documents_needing_attention' => $employees->sum(fn (User $employee) => $employee->compliance['expired_count'] + $employee->compliance['reminder_due_count']),
        ];

        $employees = $employees->sort(function (User $a, User $b) {
            return [$a->compliance['is_compliant'], -$a->compliance['expired_count']]
                <=> [$b->compliance['is_compliant'], -$b->compliance['expired_count']];
        })->values();

        return view('employee_compliance.index', compact('employees', 'summary'));
    }
}
