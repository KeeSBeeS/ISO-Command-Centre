<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\EmployeeSickRecord;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Builds exception-focused attendance rollups: who arrived late, left early,
 * is missing a checkout, or did not check in at all on a scheduled workday.
 *
 * Absences are derived, not stored: a scheduled workday (weekday that is not a
 * company-closed public holiday) with no punches and no approved leave cover is
 * reported as absent. Absence detection is clamped to the latest imported
 * attendance date so future dates are never reported as absent.
 */
class AttendanceOverviewService
{
    /**
     * Per-employee rollup for the period. Returns a collection of rows sorted
     * by most exceptions first, each containing counts for late, early leave,
     * missing checkout, on-leave and absent days.
     */
    public function overview(string $dateFrom, string $dateTo, ?string $search = null): Collection
    {
        $employees = $this->trackedEmployees($search);

        if ($employees->isEmpty()) {
            return collect();
        }

        $scheduled = $this->scheduledWorkdays($dateFrom, $dateTo);
        $evaluationCutoff = $this->absenceEvaluationCutoff();
        $evaluableScheduled = array_values(array_filter($scheduled, fn ($date) => $evaluationCutoff && $date <= $evaluationCutoff));

        $daysByEmployee = AttendanceDay::query()
            ->whereIn('user_id', $employees->pluck('id'))
            ->whereDate('attendance_date', '>=', $dateFrom)
            ->whereDate('attendance_date', '<=', $dateTo)
            ->get()
            ->groupBy('user_id');

        $leaveCover = $this->leaveCoverMap($employees->pluck('id')->all(), $dateFrom, $dateTo);

        return $employees->map(function (User $employee) use ($daysByEmployee, $scheduled, $evaluableScheduled, $leaveCover) {
            return $this->employeeRow($employee, $daysByEmployee->get($employee->id, collect()), $scheduled, $evaluableScheduled, $leaveCover[$employee->id] ?? []);
        })->sortByDesc(fn (array $row) => $row['exception_score'])->values();
    }

    /**
     * Month-by-month rollup for one employee, most recent month first.
     */
    public function monthlyTrend(User $employee, int $months = 12): Collection
    {
        $latest = AttendanceDay::query()->where('user_id', $employee->id)->max('attendance_date');
        $end = $latest ? Carbon::parse($latest)->endOfMonth() : now()->endOfMonth();
        $start = $end->copy()->subMonths($months - 1)->startOfMonth();

        $days = AttendanceDay::query()
            ->where('user_id', $employee->id)
            ->whereDate('attendance_date', '>=', $start->toDateString())
            ->whereDate('attendance_date', '<=', $end->toDateString())
            ->get()
            ->groupBy(fn (AttendanceDay $day) => $day->attendance_date->format('Y-m'));

        $leaveCover = $this->leaveCoverMap([$employee->id], $start->toDateString(), $end->toDateString());
        $trend = collect();

        for ($month = $end->copy()->startOfMonth(); $month->gte($start); $month->subMonth()) {
            $monthKey = $month->format('Y-m');
            $monthFrom = $month->copy()->startOfMonth()->toDateString();
            $monthTo = $month->copy()->endOfMonth()->toDateString();
            $scheduled = $this->scheduledWorkdays($monthFrom, $monthTo);
            $evaluationCutoff = $this->absenceEvaluationCutoff();
            $evaluableScheduled = array_values(array_filter($scheduled, fn ($date) => $evaluationCutoff && $date <= $evaluationCutoff));

            $row = $this->employeeRow($employee, $days->get($monthKey, collect()), $scheduled, $evaluableScheduled, $leaveCover[$employee->id] ?? []);
            $row['month'] = $monthKey;
            $row['month_label'] = $month->format('F Y');

            if ($row['present_days'] > 0 || !empty($evaluableScheduled)) {
                $trend->push($row);
            }
        }

        return $trend;
    }

    /**
     * Chronological day-by-day log for one employee, newest first. Every
     * scheduled workday in the range appears, even when there are no punches,
     * plus any non-working days on which the employee badged in anyway.
     */
    public function dayLog(User $employee, string $dateFrom, string $dateTo): Collection
    {
        $days = AttendanceDay::query()
            ->where('user_id', $employee->id)
            ->whereDate('attendance_date', '>=', $dateFrom)
            ->whereDate('attendance_date', '<=', $dateTo)
            ->get()
            ->keyBy(fn (AttendanceDay $day) => $day->attendance_date->toDateString());

        $holidays = $this->holidayNames($dateFrom, $dateTo);
        $leaveCover = $this->leaveCoverMap([$employee->id], $dateFrom, $dateTo)[$employee->id] ?? [];
        $evaluationCutoff = $this->absenceEvaluationCutoff();

        $log = collect();
        $from = Carbon::parse($dateFrom)->startOfDay();

        for ($date = Carbon::parse($dateTo)->startOfDay(); $date->gte($from); $date->subDay()) {
            $dateString = $date->toDateString();
            $day = $days->get($dateString);
            $isHoliday = isset($holidays[$dateString]);
            $isWeekend = $date->isWeekend();
            $isWorkday = !$isHoliday && !$isWeekend;

            if (!$isWorkday && !$day) {
                continue;
            }

            if ($isWorkday && !$day && (!$evaluationCutoff || $dateString > $evaluationCutoff)) {
                continue;
            }

            $log->push([
                'date' => $dateString,
                'weekday' => $date->format('l'),
                'is_workday' => $isWorkday,
                'holiday_name' => $holidays[$dateString] ?? null,
                'day' => $day,
                'leave_label' => $leaveCover[$dateString] ?? null,
                'status' => $this->dayStatus($day, $isWorkday, $holidays[$dateString] ?? null, $leaveCover[$dateString] ?? null),
            ]);
        }

        return $log;
    }

    /**
     * Company-wide totals for the KPI cards on the overview page.
     */
    public function totals(Collection $overviewRows): array
    {
        return [
            'employees' => $overviewRows->count(),
            'scheduled_days' => (int) $overviewRows->sum('evaluable_days'),
            'present_days' => (int) $overviewRows->sum('present_days'),
            'late_days' => (int) $overviewRows->sum('late_days'),
            'late_minutes' => (int) $overviewRows->sum('late_minutes'),
            'late_label' => $this->formatMinutes((int) $overviewRows->sum('late_minutes')),
            'early_days' => (int) $overviewRows->sum('early_days'),
            'early_minutes' => (int) $overviewRows->sum('early_minutes'),
            'early_label' => $this->formatMinutes((int) $overviewRows->sum('early_minutes')),
            'missing_checkout_days' => (int) $overviewRows->sum('missing_checkout_days'),
            'on_leave_days' => (int) $overviewRows->sum('on_leave_days'),
            'absent_days' => (int) $overviewRows->sum('absent_days'),
            'non_working_punch_days' => (int) $overviewRows->sum('non_working_punch_days'),
        ];
    }

    /**
     * Employees whose attendance is tracked: active users that either carry an
     * employee code or already have attendance history.
     */
    public function trackedEmployees(?string $search = null): Collection
    {
        return User::query()
            ->with('departments')
            ->where(function ($query) {
                $query->where('status', 'active')->orWhereNull('status');
            })
            ->where(function ($query) {
                $query->whereNotNull('employee_code')
                    ->where('employee_code', '!=', '')
                    ->orWhereIn('id', AttendanceDay::query()->select('user_id'));
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('attendance_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Dates (Y-m-d) in the range that are scheduled workdays: weekdays that are
     * not company-closed public holidays.
     */
    public function scheduledWorkdays(string $dateFrom, string $dateTo): array
    {
        $holidays = $this->holidayNames($dateFrom, $dateTo);
        $dates = [];
        $to = Carbon::parse($dateTo)->startOfDay();

        for ($date = Carbon::parse($dateFrom)->startOfDay(); $date->lte($to); $date->addDay()) {
            if ($date->isWeekend() || isset($holidays[$date->toDateString()])) {
                continue;
            }
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    private function employeeRow(User $employee, Collection $days, array $scheduled, array $evaluableScheduled, array $leaveCover): array
    {
        $daysByDate = $days->keyBy(fn (AttendanceDay $day) => $day->attendance_date->toDateString());

        $present = 0;
        $late = 0;
        $lateMinutes = 0;
        $early = 0;
        $earlyMinutes = 0;
        $missingCheckout = 0;
        $onLeave = 0;
        $absent = 0;

        foreach ($evaluableScheduled as $date) {
            $day = $daysByDate->get($date);

            if ($day && $day->start_time) {
                $present++;
                if ($day->is_late) {
                    $late++;
                    $lateMinutes += (int) $day->late_minutes;
                }
                if ($day->is_early_leave ?? false) {
                    $early++;
                    $earlyMinutes += (int) $day->early_leave_minutes;
                }
                if (!$day->end_time) {
                    $missingCheckout++;
                }
                continue;
            }

            if (isset($leaveCover[$date])) {
                $onLeave++;
                continue;
            }

            $absent++;
        }

        $nonWorkingPunches = $days->filter(function (AttendanceDay $day) use ($scheduled) {
            return $day->start_time && !in_array($day->attendance_date->toDateString(), $scheduled, true);
        })->count();

        $evaluable = count($evaluableScheduled);

        return [
            'employee' => $employee,
            'scheduled_days' => count($scheduled),
            'evaluable_days' => $evaluable,
            'present_days' => $present,
            'late_days' => $late,
            'late_minutes' => $lateMinutes,
            'late_label' => $this->formatMinutes($lateMinutes),
            'early_days' => $early,
            'early_minutes' => $earlyMinutes,
            'early_label' => $this->formatMinutes($earlyMinutes),
            'missing_checkout_days' => $missingCheckout,
            'on_leave_days' => $onLeave,
            'absent_days' => $absent,
            'non_working_punch_days' => $nonWorkingPunches,
            'attendance_rate' => $evaluable > 0 ? (int) round((($present + $onLeave) / $evaluable) * 100) : null,
            'exception_score' => ($absent * 3) + ($late * 2) + ($early * 2) + $missingCheckout,
        ];
    }

    private function dayStatus(?AttendanceDay $day, bool $isWorkday, ?string $holidayName, ?string $leaveLabel): array
    {
        if (!$isWorkday) {
            $label = $holidayName ? 'Public Holiday' : 'Weekend';
            if ($day && $day->start_time) {
                $label .= ' · Badged in';
            }
            return ['label' => $label, 'class' => 'warn'];
        }

        if (!$day || !$day->start_time) {
            if ($leaveLabel) {
                return ['label' => 'On Leave · ' . $leaveLabel, 'class' => 'warn'];
            }
            return ['label' => 'Absent · No check-in', 'class' => 'off'];
        }

        $flags = [];
        if ($day->is_late) {
            $flags[] = 'Late ' . $this->formatMinutes((int) $day->late_minutes);
        }
        if ($day->is_early_leave ?? false) {
            $flags[] = 'Early leave ' . $this->formatMinutes((int) $day->early_leave_minutes);
        }
        if (!$day->end_time) {
            $flags[] = 'No checkout';
        }

        if (empty($flags)) {
            return ['label' => 'On time', 'class' => 'ok'];
        }

        return ['label' => implode(' · ', $flags), 'class' => 'off'];
    }

    /**
     * Map of user_id => [date => leave label] for approved leave requests and
     * active sick records overlapping the range.
     */
    private function leaveCoverMap(array $userIds, string $dateFrom, string $dateTo): array
    {
        $map = [];

        if (Schema::hasTable('leave_requests')) {
            $requests = LeaveRequest::query()
                ->with('leaveType')
                ->whereIn('user_id', $userIds)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $dateTo)
                ->whereDate('end_date', '>=', $dateFrom)
                ->get();

            foreach ($requests as $request) {
                $label = $request->leaveType->name ?? 'Leave';
                $this->fillCover($map, (int) $request->user_id, $request->start_date, $request->end_date, $dateFrom, $dateTo, $label);
            }
        }

        if (Schema::hasTable('employee_sick_records')) {
            $records = EmployeeSickRecord::query()
                ->whereIn('user_id', $userIds)
                ->where('status', '!=', 'removed')
                ->whereDate('sick_from', '<=', $dateTo)
                ->whereDate('sick_to', '>=', $dateFrom)
                ->get();

            foreach ($records as $record) {
                $this->fillCover($map, (int) $record->user_id, $record->sick_from, $record->sick_to, $dateFrom, $dateTo, $record->leave_type_label);
            }
        }

        return $map;
    }

    private function fillCover(array &$map, int $userId, $from, $to, string $rangeFrom, string $rangeTo, string $label): void
    {
        $start = Carbon::parse(max(Carbon::parse($from)->toDateString(), $rangeFrom))->startOfDay();
        $end = Carbon::parse(min(Carbon::parse($to)->toDateString(), $rangeTo))->startOfDay();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $map[$userId][$date->toDateString()] = $label;
        }
    }

    private function holidayNames(string $dateFrom, string $dateTo): array
    {
        if (!Schema::hasTable('public_holidays')) {
            return [];
        }

        return PublicHoliday::query()
            ->whereBetween('holiday_date', [$dateFrom, $dateTo])
            ->where('is_company_closed', true)
            ->get()
            ->mapWithKeys(fn (PublicHoliday $holiday) => [Carbon::parse($holiday->holiday_date)->toDateString() => $holiday->name])
            ->all();
    }

    /**
     * Absences are only evaluated up to the most recent imported attendance
     * date (capped at today) so days that have not been imported yet are not
     * flagged as absences.
     */
    private function absenceEvaluationCutoff(): ?string
    {
        $latest = AttendanceDay::query()->max('attendance_date');

        if (!$latest) {
            return null;
        }

        return min(Carbon::parse($latest)->toDateString(), now()->toDateString());
    }

    public function formatMinutes(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours > 0
            ? sprintf('%dh %02dm', $hours, $remaining)
            : sprintf('%d min', $remaining);
    }
}
