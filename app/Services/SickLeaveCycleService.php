<?php

namespace App\Services;

use App\Models\EmployeeSickRecord;
use App\Models\LeaveRequest;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks the statutory paid sick leave entitlement: 6 weeks (30 working days on
 * a five-day week) for every 36-month cycle. The cycle is anchored to the
 * employee's start date (falling back to the account creation date), which is
 * the standard BCEA approach: a new cycle starts every 36 months of employment.
 *
 * Sick usage is counted in working days (weekends and company-closed public
 * holidays excluded) from both director-recorded sick records and approved
 * leave requests whose leave type is a sick type.
 */
class SickLeaveCycleService
{
    public function __construct(private LeaveBalanceService $leaveBalance)
    {
    }

    public function cycleMonths(): int
    {
        return max(1, (int) SystemSetting::valueFor('sick_leave_cycle_months', 36));
    }

    public function entitlementDays(): int
    {
        return max(0, (int) SystemSetting::valueFor('sick_leave_cycle_days', 30));
    }

    /**
     * The current 36-month sick leave cycle for an employee, with usage.
     */
    public function cycleFor(User $employee, ?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?: now())->copy()->startOfDay();
        $months = $this->cycleMonths();
        $anchor = $this->cycleAnchor($employee);

        $cycleNumber = 1;
        $cycleStart = $anchor->copy();
        while ($cycleStart->copy()->addMonths($months)->lte($asOf)) {
            $cycleStart->addMonths($months);
            $cycleNumber++;
        }
        $cycleEnd = $cycleStart->copy()->addMonths($months)->subDay();

        $entitlement = $this->entitlementDays();
        $used = $this->usedSickDaysBetween($employee, $cycleStart, $cycleEnd);

        return [
            'anchor' => $anchor,
            'cycle_number' => $cycleNumber,
            'cycle_start' => $cycleStart,
            'cycle_end' => $cycleEnd,
            'cycle_months' => $months,
            'entitlement_days' => $entitlement,
            'used_days' => $used,
            'remaining_days' => max(0, $entitlement - $used),
            'over_entitlement_days' => max(0, $used - $entitlement),
            'used_percent' => $entitlement > 0 ? min(100, (int) round(($used / $entitlement) * 100)) : 0,
        ];
    }

    /**
     * Working days of sick leave taken inside the window, from both sources.
     */
    public function usedSickDaysBetween(User $employee, Carbon $from, Carbon $to): int
    {
        $used = 0;

        if (Schema::hasTable('employee_sick_records')) {
            $records = EmployeeSickRecord::query()
                ->where('user_id', $employee->id)
                ->where('status', '!=', 'removed')
                ->where(function ($query) {
                    $query->whereNull('leave_type')->orWhere('leave_type', 'sick');
                })
                ->whereDate('sick_from', '<=', $to->toDateString())
                ->whereDate('sick_to', '>=', $from->toDateString())
                ->get();

            foreach ($records as $record) {
                $start = $record->sick_from->copy()->max($from);
                $end = $record->sick_to->copy()->min($to);
                $used += $this->leaveBalance->workingDaysBetween($start, $end);
            }
        }

        if (Schema::hasTable('leave_requests') && Schema::hasTable('leave_types')) {
            $requests = LeaveRequest::query()
                ->where('user_id', $employee->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $to->toDateString())
                ->whereDate('end_date', '>=', $from->toDateString())
                ->whereHas('leaveType', function ($query) {
                    $query->where('name', 'like', '%sick%')->orWhere('code', 'like', '%sick%');
                })
                ->get();

            foreach ($requests as $request) {
                $start = $request->start_date->copy()->max($from);
                $end = $request->end_date->copy()->min($to);
                $used += $this->leaveBalance->workingDaysBetween($start, $end);
            }
        }

        return $used;
    }

    /**
     * Recent sick records for display, newest first.
     */
    public function recentRecords(User $employee, int $limit = 10)
    {
        if (!Schema::hasTable('employee_sick_records')) {
            return collect();
        }

        return EmployeeSickRecord::query()
            ->with(['marker', 'removedBy'])
            ->where('user_id', $employee->id)
            ->orderByDesc('sick_from')
            ->limit($limit)
            ->get();
    }

    private function cycleAnchor(User $employee): Carbon
    {
        $startedAt = null;

        if (Schema::hasTable('employee_profiles')) {
            $startedAt = optional($employee->profile)->started_at;
        }

        $anchor = $startedAt ?: $employee->created_at;

        return ($anchor ? Carbon::parse($anchor) : now())->copy()->startOfDay();
    }
}
