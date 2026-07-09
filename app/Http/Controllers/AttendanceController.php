<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\AttendanceImport;
use App\Models\AttendanceRawRecord;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AttendanceCsvImporter;
use App\Services\AttendanceMailboxImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        if (!$this->attendanceInstalled()) {
            return redirect()->route('updates.v1_1')->withErrors(['attendance' => 'Apply Version 1.1 before using attendance.']);
        }

        $timing = $this->attendanceTiming();
        $selectedEmployee = $request->filled('employee_id')
            ? User::query()->find($request->integer('employee_id'))
            : null;

        $latestAttendanceDate = AttendanceDay::query()->max('attendance_date');
        $employeeMinDate = $selectedEmployee
            ? AttendanceDay::query()->where('user_id', $selectedEmployee->id)->min('attendance_date')
            : null;
        $employeeMaxDate = $selectedEmployee
            ? AttendanceDay::query()->where('user_id', $selectedEmployee->id)->max('attendance_date')
            : null;

        $hasExplicitFilter = $request->filled('date_from')
            || $request->filled('date_to')
            || $request->filled('search')
            || $request->boolean('late_only')
            || $request->boolean('public_holidays_only')
            || ($request->filled('employee_id') && !$selectedEmployee);

        if ($selectedEmployee && !$request->filled('date_from') && !$request->filled('date_to')) {
            $dateFrom = $employeeMinDate ?: ($latestAttendanceDate ?: now()->toDateString());
            $dateTo = $employeeMaxDate ?: $dateFrom;
        } else {
            $dateFrom = $request->input('date_from', $hasExplicitFilter ? now()->toDateString() : ($latestAttendanceDate ?: now()->toDateString()));
            $dateTo = $request->input('date_to', $dateFrom);
        }

        $baseQuery = AttendanceDay::query()
            ->with(['user.roles', 'user.departments'])
            ->whereBetween('attendance_date', [$dateFrom, $dateTo])
            ->when($selectedEmployee, fn ($query) => $query->where('user_id', $selectedEmployee->id))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('attendance_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        $days = (clone $baseQuery)
            ->when($request->boolean('late_only') && Schema::hasColumn('attendance_days', 'is_late'), function ($query) {
                $query->where('is_late', true);
            })
            ->when($request->boolean('public_holidays_only') && Schema::hasColumn('attendance_days', 'is_public_holiday'), function ($query) {
                $query->where('is_public_holiday', true);
            })
            ->orderByDesc('attendance_date')
            ->orderBy('start_time')
            ->paginate(30)
            ->withQueryString();

        $summaryQuery = clone $baseQuery;
        $lateQuery = clone $baseQuery;
        $holidayQuery = clone $baseQuery;

        $workingDayScope = function ($query) {
            if (Schema::hasColumn('attendance_days', 'is_public_holiday')) {
                $query->where(function ($nested) {
                    $nested->where('is_public_holiday', false)->orWhereNull('is_public_holiday');
                });
            }
        };

        return view('attendance.index', [
            'days' => $days,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'latestAttendanceDate' => $latestAttendanceDate,
            'presentCount' => (clone $summaryQuery)->whereNotNull('start_time')->where($workingDayScope)->distinct('user_id')->count('user_id'),
            'absentCount' => (clone $summaryQuery)->whereNull('start_time')->where($workingDayScope)->count(),
            'recordCount' => (clone $summaryQuery)->sum('record_count'),
            'singleRecordCount' => (clone $summaryQuery)->where('record_count', '<=', 1)->count(),
            'lateClockInCount' => Schema::hasColumn('attendance_days', 'is_late') ? $lateQuery->where('is_late', true)->count() : 0,
            'publicHolidayAttendanceCount' => Schema::hasColumn('attendance_days', 'is_public_holiday') ? $holidayQuery->where('is_public_holiday', true)->count() : 0,
            'latestImport' => AttendanceImport::latest()->first(),
            'attendanceStartTime' => $timing['start'],
            'attendanceCloseTime' => $timing['close'],
            'selectedEmployee' => $selectedEmployee,
            'employeeAttendanceSummary' => $selectedEmployee
                ? $this->employeeAttendanceSummary($selectedEmployee->id, $dateFrom, $dateTo, $timing['start_minutes'], $timing['close_minutes'])
                : null,
        ]);
    }

    public function show(Request $request, string $attendanceDay)
    {
        if (!$this->attendanceInstalled()) {
            return redirect()->route('updates.v1_1');
        }

        $employee = $this->resolveAttendanceEmployee($attendanceDay);
        abort_unless($employee, 404, 'Employee attendance records not found.');

        $timing = $this->attendanceTiming();

        $firstDate = AttendanceRawRecord::query()->where('user_id', $employee->id)->min('attendance_date')
            ?: AttendanceDay::query()->where('user_id', $employee->id)->min('attendance_date');
        $lastDate = AttendanceRawRecord::query()->where('user_id', $employee->id)->max('attendance_date')
            ?: AttendanceDay::query()->where('user_id', $employee->id)->max('attendance_date');

        $dateFrom = $request->input('date_from') ?: $firstDate;
        $dateTo = $request->input('date_to') ?: $lastDate;

        $rawBase = AttendanceRawRecord::query()
            ->with('import')
            ->where('user_id', $employee->id)
            ->when($dateFrom, fn ($query) => $query->whereDate('attendance_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('attendance_date', '<=', $dateTo))
            ->when($request->filled('status'), fn ($query) => $query->where('attendance_status', $request->input('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($nested) use ($search) {
                    $nested->where('employee_name', 'like', "%{$search}%")
                        ->orWhere('attendance_status', 'like', "%{$search}%");
                });
            });

        $dayBase = AttendanceDay::query()
            ->where('user_id', $employee->id)
            ->when($dateFrom, fn ($query) => $query->whereDate('attendance_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('attendance_date', '<=', $dateTo));

        $rawRecords = (clone $rawBase)
            ->orderByDesc('recorded_at')
            ->paginate(100, ['*'], 'records_page')
            ->withQueryString();

        $dailySummaries = (clone $dayBase)
            ->orderByDesc('attendance_date')
            ->paginate(31, ['*'], 'days_page')
            ->withQueryString();

        $rawCount = (clone $rawBase)->count();
        $dayCount = (clone $rawBase)->distinct('attendance_date')->count('attendance_date');
        $importCount = (clone $rawBase)->whereNotNull('attendance_import_id')->distinct('attendance_import_id')->count('attendance_import_id');
        $firstRecord = (clone $rawBase)->min('recorded_at');
        $lastRecord = (clone $rawBase)->max('recorded_at');

        $statusBreakdown = (clone $rawBase)
            ->select('attendance_status', DB::raw('COUNT(*) as total'))
            ->groupBy('attendance_status')
            ->orderByDesc('total')
            ->get();

        $imports = AttendanceImport::query()
            ->whereIn('id', (clone $rawBase)->whereNotNull('attendance_import_id')->select('attendance_import_id')->distinct())
            ->latest()
            ->limit(10)
            ->get();

        $lateSummary = ($dateFrom && $dateTo)
            ? $this->employeeAttendanceSummary($employee->id, $dateFrom, $dateTo, $timing['start_minutes'], $timing['close_minutes'])
            : [
                'days' => (clone $dayBase)->count(),
                'late_days' => 0,
                'late_minutes' => 0,
                'late_label' => '0 min',
                'early_leave_days' => 0,
                'early_leave_minutes' => 0,
                'early_leave_label' => '0 min',
            ];

        return view('attendance.show', [
            'employee' => $employee,
            'employeeCode' => $this->attendanceEmployeeRouteValue($employee),
            'rawRecords' => $rawRecords,
            'dailySummaries' => $dailySummaries,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'attendanceStartTime' => $timing['start'],
            'attendanceCloseTime' => $timing['close'],
            'summary' => [
                'raw_count' => $rawCount,
                'day_count' => $dayCount,
                'import_count' => $importCount,
                'first_record' => $firstRecord,
                'last_record' => $lastRecord,
            ],
            'statusBreakdown' => $statusBreakdown,
            'imports' => $imports,
            'lateSummary' => $lateSummary,
        ]);
    }

    public function upload()
    {
        if (!$this->attendanceInstalled()) {
            return redirect()->route('updates.v1_1');
        }

        return view('attendance.upload');
    }

    public function importUpload(Request $request, AttendanceCsvImporter $importer)
    {
        if (!$this->attendanceInstalled()) {
            return redirect()->route('updates.v1_1');
        }

        $data = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $data['csv_file'];
        $import = $importer->importString(file_get_contents($file->getRealPath()), [
            'source' => 'upload',
            'filename' => $file->getClientOriginalName(),
            'imported_by' => $request->user()->id,
            'received_at' => now(),
        ]);

        return redirect()->route('attendance.imports')->with('success', $this->summaryMessage($import));
    }


    public function manualUpload()
    {
        if (!$this->attendanceInstalled()) {
            return redirect()->route('updates.v1_1');
        }

        return view('attendance.manual_upload');
    }

    public function importManualUpload(Request $request, AttendanceCsvImporter $importer)
    {
        if (!$this->attendanceInstalled()) {
            return redirect()->route('updates.v1_1');
        }

        $data = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'import_note' => ['nullable', 'string', 'max:500'],
        ]);

        $file = $data['csv_file'];
        $import = $importer->importString(file_get_contents($file->getRealPath()), [
            'source' => 'director_manual_upload',
            'filename' => $file->getClientOriginalName(),
            'imported_by' => $request->user()->id,
            'received_at' => now(),
            'received_subject' => $data['import_note'] ?? 'Director manual CSV upload',
        ]);

        return redirect()->route('attendance.imports')->with('success', 'Director manual CSV upload completed. ' . $this->summaryMessage($import));
    }

    public function fetchEmail(AttendanceMailboxImporter $mailboxImporter)
    {
        if (!$this->attendanceInstalled()) {
            return redirect()->route('updates.v1_1');
        }

        $result = $mailboxImporter->importUnread();

        if (!$result['ok']) {
            return back()->withErrors(['email_import' => $result['message']]);
        }

        return redirect()->route('attendance.imports')->with('success', $result['message']);
    }

    public function fetchEmailByKey(string $key, AttendanceMailboxImporter $mailboxImporter)
    {
        if (!$this->attendanceInstalled()) {
            abort(503, 'Attendance tables are not installed.');
        }

        $expected = env('ATTENDANCE_IMPORT_KEY');
        if (!$expected || !hash_equals((string) $expected, $key)) {
            abort(403, 'Invalid attendance import key.');
        }

        $result = $mailboxImporter->importUnread();

        return Response::json($result);
    }

    public function imports()
    {
        if (!$this->attendanceInstalled()) {
            return redirect()->route('updates.v1_1');
        }

        return view('attendance.imports', [
            'imports' => AttendanceImport::with('importer')->latest()->paginate(25),
        ]);
    }

    private function employeeAttendanceSummary(int $userId, string $dateFrom, string $dateTo, int $startMinutes, int $closeMinutes): array
    {
        $days = AttendanceDay::query()
            ->where('user_id', $userId)
            ->whereBetween('attendance_date', [$dateFrom, $dateTo])
            ->when(Schema::hasColumn('attendance_days', 'is_public_holiday'), function ($query) {
                $query->where(function ($nested) {
                    $nested->where('is_public_holiday', false)->orWhereNull('is_public_holiday');
                });
            })
            ->orderBy('attendance_date')
            ->get();

        $lateMinutes = 0;
        $earlyLeaveMinutes = 0;
        $lateDays = 0;
        $earlyLeaveDays = 0;

        foreach ($days as $day) {
            $start = $day->start_time ? ((int) $day->start_time->format('H') * 60 + (int) $day->start_time->format('i')) : null;
            $end = $day->end_time ? ((int) $day->end_time->format('H') * 60 + (int) $day->end_time->format('i')) : null;

            if ($start !== null && $start > $startMinutes) {
                $lateDays++;
                $lateMinutes += $start - $startMinutes;
            }

            if ($end !== null && $end < $closeMinutes) {
                $earlyLeaveDays++;
                $earlyLeaveMinutes += $closeMinutes - $end;
            }
        }

        return [
            'days' => $days->count(),
            'late_days' => $lateDays,
            'late_minutes' => $lateMinutes,
            'late_label' => $this->formatMinutes($lateMinutes),
            'early_leave_days' => $earlyLeaveDays,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'early_leave_label' => $this->formatMinutes($earlyLeaveMinutes),
        ];
    }

    private function attendanceTiming(): array
    {
        $start = $this->normaliseTime(SystemSetting::valueFor('attendance_company_start_time', '06:00'), '06:00');
        $close = $this->normaliseTime(SystemSetting::valueFor('attendance_company_close_time', '15:00'), '15:00');

        return [
            'start' => $start,
            'close' => $close,
            'start_minutes' => $this->minutesFromTime($start),
            'close_minutes' => $this->minutesFromTime($close),
        ];
    }

    private function resolveAttendanceEmployee(string $identifier): ?User
    {
        $identifier = trim(urldecode($identifier));

        return User::query()
            ->where(function ($query) use ($identifier) {
                $query->where('employee_code', $identifier)
                    ->orWhere('attendance_name', $identifier)
                    ->orWhere('email', $identifier);

                if (is_numeric($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
            })
            ->first();
    }

    private function attendanceEmployeeRouteValue(User $user): string
    {
        return (string) ($user->employee_code ?: $user->id);
    }

    private function normaliseTime(?string $value, string $default): string
    {
        $value = trim((string) $value);
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : $default;
    }

    private function minutesFromTime(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        return ($hour * 60) + $minute;
    }

    private function formatMinutes(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours > 0
            ? sprintf('%dh %02dm', $hours, $remaining)
            : sprintf('%d min', $remaining);
    }

    private function attendanceInstalled(): bool
    {
        return Schema::hasTable('attendance_days') && Schema::hasTable('attendance_imports') && Schema::hasTable('attendance_raw_records');
    }

    private function summaryMessage(AttendanceImport $import): string
    {
        return 'Attendance import completed: ' . $import->matched_rows . ' matched row(s), ' . $import->skipped_rows . ' skipped row(s), ' . $import->day_rows . ' daily record(s) rebuilt. All matched raw punch records are retained for employee drill-down.';
    }
}
