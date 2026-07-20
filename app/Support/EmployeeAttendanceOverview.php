<?php

namespace App\Support;

use App\Models\AttendanceDay;
use App\Models\PublicHoliday;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Builds a date-range filtered time-attendance register for an employee.
 *
 * The register mirrors the biometric "Start/End Work Time" and "Late" reports:
 * every working day in the selected range is listed with its shift/timetable,
 * check-in, check-out and how many minutes late the clock-in was, plus a set of
 * at-a-glance totals. When no date range is supplied it defaults to the last
 * four weeks of available data.
 *
 * This is a self-contained helper so the feature can be added to the employee
 * profile page with a single Blade @include and no controller changes.
 */
class EmployeeAttendanceOverview
{
    public static function build(Request $request, User $employee): ?array
    {
        if (!Schema::hasTable('attendance_days')) {
            return null;
        }

        $startTime = self::normaliseTime(SystemSetting::valueFor('attendance_company_start_time', '06:00'), '06:00');
        $closeTime = self::normaliseTime(SystemSetting::valueFor('attendance_company_close_time', '15:00'), '15:00');
        $startMinutes = self::minutes($startTime);
        $closeMinutes = self::minutes($closeTime);

        $availableFrom = AttendanceDay::where('user_id', $employee->id)->min('attendance_date');
        $availableTo = AttendanceDay::where('user_id', $employee->id)->max('attendance_date');

        $latest = $availableTo ? Carbon::parse($availableTo) : Carbon::today();
        $defaultTo = $latest->toDateString();
        $defaultFrom = $latest->copy()->subDays(27)->toDateString();
        if ($availableFrom && $defaultFrom < $availableFrom) {
            $defaultFrom = $availableFrom;
        }

        $dateFrom = self::normaliseDate($request->input('att_from'), $defaultFrom);
        $dateTo = self::normaliseDate($request->input('att_to'), $defaultTo);
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $from = Carbon::parse($dateFrom);
        $to = Carbon::parse($dateTo);
        $rangeCapped = false;
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366);
            $dateFrom = $from->toDateString();
            $rangeCapped = true;
        }

        $hasHolidayColumn = Schema::hasColumn('attendance_days', 'is_public_holiday');

        $daysByDate = AttendanceDay::where('user_id', $employee->id)
            ->whereBetween('attendance_date', [$dateFrom, $dateTo])
            ->get()
            ->keyBy(fn (AttendanceDay $day) => optional($day->attendance_date)->format('Y-m-d'));

        $holidaysByDate = collect();
        if (Schema::hasTable('public_holidays')) {
            $holidaysByDate = PublicHoliday::whereBetween('holiday_date', [$dateFrom, $dateTo])
                ->get()
                ->keyBy(fn (PublicHoliday $holiday) => optional($holiday->holiday_date)->format('Y-m-d'));
        }

        $rows = [];
        $present = 0;
        $absent = 0;
        $lateDays = 0;
        $lateMinutes = 0;
        $earlyDays = 0;
        $earlyMinutes = 0;
        $holidayCount = 0;

        for ($cursor = $to->copy(); $cursor->gte($from); $cursor->subDay()) {
            $key = $cursor->format('Y-m-d');
            $day = $daysByDate->get($key);
            $holiday = $holidaysByDate->get($key);

            if ($cursor->isWeekend() && !$day) {
                continue;
            }

            $isHoliday = ($holiday && $holiday->is_company_closed)
                || ($day && $hasHolidayColumn && $day->is_public_holiday);
            $holidayName = $holiday?->name ?? ($day->public_holiday_name ?? null);

            $checkIn = $day && $day->start_time ? $day->start_time->format('H:i:s') : null;
            $checkOut = $day && $day->end_time ? $day->end_time->format('H:i:s') : null;
            $startMin = $day && $day->start_time ? ((int) $day->start_time->format('H') * 60 + (int) $day->start_time->format('i')) : null;
            $endMin = $day && $day->end_time ? ((int) $day->end_time->format('H') * 60 + (int) $day->end_time->format('i')) : null;

            $rowLate = 0;
            $rowEarly = 0;
            $status = 'absent';

            if ($isHoliday) {
                $status = 'holiday';
                $holidayCount++;
            } elseif ($startMin !== null) {
                $status = 'present';
                $present++;
                if ($startMin > $startMinutes) {
                    $lateDays++;
                    $rowLate = $startMin - $startMinutes;
                    $lateMinutes += $rowLate;
                }
                if ($endMin !== null && $endMin < $closeMinutes) {
                    $earlyDays++;
                    $rowEarly = $closeMinutes - $endMin;
                    $earlyMinutes += $rowEarly;
                }
            } else {
                $absent++;
            }

            $rows[] = [
                'date' => $cursor->copy(),
                'weekday' => $cursor->format('D'),
                'status' => $status,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'work_hours' => $day ? $day->work_hours : null,
                'late_minutes' => $rowLate,
                'late_label' => $rowLate > 0 ? self::formatMinutes($rowLate) : null,
                'early_minutes' => $rowEarly,
                'early_label' => $rowEarly > 0 ? self::formatMinutes($rowEarly) : null,
                'holiday_name' => $isHoliday ? $holidayName : null,
                'record_count' => $day ? (int) $day->record_count : 0,
            ];
        }

        return [
            'timetable' => sprintf('%s:00 - %s:00', $startTime, $closeTime),
            'start_time' => $startTime,
            'close_time' => $closeTime,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'default_from' => $defaultFrom,
            'default_to' => $defaultTo,
            'available_from' => $availableFrom,
            'available_to' => $availableTo,
            'has_data' => (bool) $availableFrom,
            'is_filtered' => $request->filled('att_from') || $request->filled('att_to'),
            'range_capped' => $rangeCapped,
            'rows' => $rows,
            'summary' => [
                'working_days' => $present + $absent,
                'present' => $present,
                'absent' => $absent,
                'late_days' => $lateDays,
                'late_minutes' => $lateMinutes,
                'late_label' => self::formatMinutes($lateMinutes),
                'early_days' => $earlyDays,
                'early_minutes' => $earlyMinutes,
                'early_label' => self::formatMinutes($earlyMinutes),
                'public_holidays' => $holidayCount,
            ],
        ];
    }

    private static function normaliseTime(?string $value, string $default): string
    {
        $value = trim((string) $value);

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : $default;
    }

    private static function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return ($hour * 60) + $minute;
    }

    private static function normaliseDate(?string $value, string $default): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $default;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private static function formatMinutes(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours > 0
            ? sprintf('%dh %02dm', $hours, $remaining)
            : sprintf('%d min', $remaining);
    }
}
