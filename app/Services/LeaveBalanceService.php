<?php

namespace App\Services;

use App\Models\EmployeeLeaveAllocation;
use App\Models\EmployeeSickRecord;
use App\Models\PublicHoliday;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class LeaveBalanceService
{
    public function summary(User $employee, ?int $year = null): array
    {
        $year = $year ?: (int) now()->format('Y');
        $allocation = null;

        if (Schema::hasTable('employee_leave_allocations')) {
            $allocation = EmployeeLeaveAllocation::where('user_id', $employee->id)
                ->where('year', $year)
                ->first();
        }

        $allocated = $allocation ? (float) $allocation->allocated_days : 0.0;
        $carried = $allocation ? (float) $allocation->carried_over_days : 0.0;
        $total = $allocated + $carried;

        $usedNormal = $this->usedDays($employee, $year, 'normal');
        $usedSick = $this->usedDays($employee, $year, 'sick');
        $usedUnpaid = $this->usedDays($employee, $year, 'unpaid');
        $usedFamily = $this->usedDays($employee, $year, 'family_responsibility');

        return [
            'year' => $year,
            'allocation' => $allocation,
            'allocated_days' => $allocated,
            'carried_over_days' => $carried,
            'total_days' => $total,
            'used_normal_days' => $usedNormal,
            'remaining_normal_days' => $total - $usedNormal,
            'used_sick_days' => $usedSick,
            'used_unpaid_days' => $usedUnpaid,
            'used_family_days' => $usedFamily,
        ];
    }

    public function usedDays(User $employee, int $year, string $type): float
    {
        if (!Schema::hasTable('employee_sick_records')) {
            return 0.0;
        }

        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();

        $query = EmployeeSickRecord::query()
            ->where('user_id', $employee->id)
            ->where('status', '!=', 'removed')
            ->whereDate('sick_from', '<=', $end->toDateString())
            ->whereDate('sick_to', '>=', $start->toDateString());

        if (Schema::hasColumn('employee_sick_records', 'leave_type')) {
            $query->where('leave_type', $type);
        } elseif ($type !== 'sick') {
            return 0.0;
        }

        return $query->get()->sum(function (EmployeeSickRecord $record) use ($start, $end) {
            $from = $record->sick_from->copy()->max($start);
            $to = $record->sick_to->copy()->min($end);
            return $this->workingDaysBetween($from, $to);
        });
    }

    public function workingDaysBetween(Carbon $from, Carbon $to): int
    {
        $holidays = $this->holidayMap($from, $to);
        $days = 0;

        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            if ($date->isWeekend()) {
                continue;
            }
            if (isset($holidays[$date->toDateString()])) {
                continue;
            }
            $days++;
        }

        return $days;
    }

    private function holidayMap(Carbon $from, Carbon $to): array
    {
        if (!Schema::hasTable('public_holidays')) {
            return [];
        }

        return PublicHoliday::query()
            ->whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip()
            ->all();
    }
}
