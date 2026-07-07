<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\PlatformSetting;
use App\Models\PublicHoliday;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AttendancePolicyService
{
    public function workdayStart(): string
    {
        return (string) PlatformSetting::getValue('attendance.work_start', '06:00');
    }

    public function workdayEnd(): string
    {
        return (string) PlatformSetting::getValue('attendance.work_end', '15:00');
    }

    public function workdayNames(): array
    {
        $value = (string) PlatformSetting::getValue('attendance.workdays', 'monday,tuesday,wednesday,thursday,friday');
        return array_values(array_filter(array_map('trim', explode(',', strtolower($value)))));
    }

    public function holidayName(Carbon|string $date): ?string
    {
        if (!Schema::hasTable('public_holidays')) {
            return null;
        }

        $dateString = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();
        return PublicHoliday::whereDate('holiday_date', $dateString)->value('title');
    }

    public function isScheduledWorkday(Carbon|string $date): bool
    {
        $carbon = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        return in_array(strtolower($carbon->format('l')), $this->workdayNames(), true) && !$this->holidayName($carbon);
    }

    public function expectedMinutes(): int
    {
        $base = Carbon::createFromFormat('H:i', $this->workdayStart());
        $end = Carbon::createFromFormat('H:i', $this->workdayEnd());
        return max(0, $base->diffInMinutes($end, false));
    }

    public function apply(AttendanceDay $day): void
    {
        if (!Schema::hasColumn('attendance_days', 'scheduled_start')) {
            return;
        }

        $date = $day->attendance_date instanceof Carbon ? $day->attendance_date->copy() : Carbon::parse($day->attendance_date);
        $holidayName = $this->holidayName($date);
        $isWorkday = $this->isScheduledWorkday($date);
        $expectedMinutes = $isWorkday ? $this->expectedMinutes() : 0;

        $scheduledStart = Carbon::parse($date->toDateString() . ' ' . $this->workdayStart() . ':00');
        $scheduledEnd = Carbon::parse($date->toDateString() . ' ' . $this->workdayEnd() . ':00');
        $lateMinutes = ($isWorkday && $day->start_time && $day->start_time->gt($scheduledStart))
            ? $scheduledStart->diffInMinutes($day->start_time)
            : 0;
        $earlyLeaveMinutes = ($isWorkday && $day->end_time && $day->end_time->lt($scheduledEnd))
            ? $day->end_time->diffInMinutes($scheduledEnd)
            : 0;
        $varianceMinutes = (int) $day->work_minutes - $expectedMinutes;

        $label = $holidayName ? 'Public Holiday' : ($isWorkday ? 'Workday' : 'Non-workday');
        if ($isWorkday && $lateMinutes > 0) {
            $label .= ' · Late';
        }
        if ($isWorkday && $earlyLeaveMinutes > 0) {
            $label .= ' · Early Leave';
        }

        $day->forceFill([
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledEnd,
            'expected_work_minutes' => $expectedMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'variance_minutes' => $varianceMinutes,
            'is_workday' => $isWorkday,
            'is_public_holiday' => (bool) $holidayName,
            'holiday_name' => $holidayName,
            'attendance_status_label' => $label,
        ])->save();
    }

    public function rebuildExistingDays(): int
    {
        if (!Schema::hasTable('attendance_days')) {
            return 0;
        }

        $count = 0;
        AttendanceDay::query()->chunkById(100, function ($days) use (&$count) {
            foreach ($days as $day) {
                $this->apply($day);
                $count++;
            }
        });

        return $count;
    }
}
