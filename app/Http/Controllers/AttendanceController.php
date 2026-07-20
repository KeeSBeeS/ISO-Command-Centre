<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\AttendanceImport;
use App\Models\AttendanceRawRecord;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AttendanceCsvImporter;
use App\Services\AttendanceMailboxImporter;
use App\Services\AttendanceOverviewService;
use App\Services\LeaveBalanceService;
use App\Services\SickLeaveCycleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;

class AttendanceController extends Controller
{
    public function index(Request $request, AttendanceOverviewService $overview)
    {
        if (!$this->attendanceInstalled()) {
            return redirect()->route('updates.v1_1')->withErrors(['attendance' => 'Apply Version 1.1 before using attendance.']);
        }

        if ($request->filled('employee_id')) {
            $selectedEmployee = User::query()->find($request->integer('employee_id'));
            if ($selectedEmployee) {
                return redirect()->route('attendance.show', $this->attendanceEmployeeRouteValue($selectedEmployee));
            }
        }

        $timing = $this->attendanceTiming();
        $latestAttendanceDate = AttendanceDay::query()->max('attendance_date');
        $anchor = $latestAttendanceDate ? Carbon::parse($latestAttendanceDate) : now();

        $dateFrom = $request->input('date_from') ?: $anchor->copy()->startOfMonth()->toDateString();
        $dateTo = $request->input('date_to') ?: $anchor->toDateString();
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $viewMode = $request->input('view') === 'daily' ? 'daily' : 'overview';
        $search = $request->filled('search') ? (string) $request->string('search') : null;

        $overviewRows = $overview->overview($dateFrom, $dateTo, $search);
        $totals = $overview->totals($overviewRows);

        $days = AttendanceDay::query()
            ->with(['user.roles', 'user.departments'])
            ->whereDate('attendance_date', '>=', $dateFrom)
            ->whereDate('attendance_date', '<=', $dateTo)
            ->when($search, function ($query) use ($search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('attendance_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                });
            })
            ->when($request->boolean('late_only') && Schema::hasColumn('attendance_days', 'is_late'), function ($query) {
                $query->where('is_late', true);
            })
            ->when($request->boolean('early_only') && Schema::hasColumn('attendance_days', 'is_early_leave'), function ($query) {
                $query->where('is_early_leave', true);
            })
            ->when($request->boolean('missing_only'), function ($query) {
                $query->whereNotNull('start_time')->whereNull('end_time');
            })
            ->when($request->boolean('public_holidays_only') && Schema::hasColumn('attendance_days', 'is_public_holiday'), function ($query) {
                $query->where('is_public_holiday', true);
            })
            ->orderByDesc('attendance_date')
            ->orderBy('start_time')
            ->paginate(31)
            ->withQueryString();

        $anchorMonth = Carbon::parse($dateFrom)->startOfMonth();

        return view('attendance.index', [
            'viewMode' => $viewMode,
            'overviewRows' => $overviewRows,
            'totals' => $totals,
            'days' => $days,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
            'latestAttendanceDate' => $latestAttendanceDate,
            'latestImport' => AttendanceImport::latest()->first(),
            'attendanceStartTime' => $timing['start'],
            'attendanceCloseTime' => $timing['close'],
            'previousMonth' => $anchorMonth->copy()->subMonth(),
            'nextMonth' => $anchorMonth->copy()->addMonth(),
            'periodLabel' => Carbon::parse($dateFrom)->format('j M Y') . ' – ' . Carbon::parse($dateTo)->format('j M Y'),
        ]);
    }

    public function show(Request $request, string $attendanceDay, AttendanceOverviewService $overview, SickLeaveCycleService $sickCycle, LeaveBalanceService $leaveBalance)
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

        $dateFrom = $request->filled('date_from') ? $request->input('date_from') : null;
        $dateTo = $request->filled('date_to') ? $request->input('date_to') : null;
        $historyDateFrom = $firstDate;
        $historyDateTo = $lastDate;
        $activeDateFrom = $dateFrom ?: $historyDateFrom;
        $activeDateTo = $dateTo ?: $historyDateTo;

        $rawBase = AttendanceRawRecord::query()
            ->with('import')
            ->where('user_id', $employee->id)
            ->when($dateFrom, fn ($query) => $query->whereDate('attendance_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('attendance_date', '<=', $dateTo))
            ->when($request->filled('status'), fn ($query) => $query->where('attendance_status', $request->input('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = (string) $request->string('search');
                $query->where(function ($nested) use ($search) {
                    $nested->where('employee_name', 'like', "%{$search}%")
                        ->orWhere('attendance_status', 'like', "%{$search}%");

                    foreach (['person_id', 'department', 'attendance_check_point', 'custom_name', 'data_source', 'handling_type', 'temperature', 'abnormal'] as $column) {
                        if (Schema::hasColumn('attendance_raw_records', $column)) {
                            $nested->orWhere($column, 'like', "%{$search}%");
                        }
                    }
                });
            });

        $rawRecords = (clone $rawBase)
            ->orderByDesc('recorded_at')
            ->paginate(100, ['*'], 'records_page')
            ->withQueryString();

        $punchHistory = (clone $rawBase)
            ->select(
                'attendance_date',
                DB::raw('COUNT(*) as total_records'),
                DB::raw('MIN(recorded_at) as first_punch'),
                DB::raw('MAX(recorded_at) as last_punch')
            )
            ->groupBy('attendance_date')
            ->orderByDesc('attendance_date')
            ->paginate(60, ['*'], 'history_page')
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

        $dayLog = ($activeDateFrom && $activeDateTo)
            ? $overview->dayLog($employee, $activeDateFrom, $activeDateTo)
            : collect();

        $periodSummary = $this->summariseDayLog($dayLog);
        $monthlyTrend = $overview->monthlyTrend($employee, 12);

        $sickCycleSummary = null;
        if (Schema::hasTable('employee_sick_records') || Schema::hasTable('leave_requests')) {
            $sickCycleSummary = $sickCycle->cycleFor($employee);
        }

        $leaveSummary = Schema::hasTable('employee_leave_allocations')
            ? $leaveBalance->summary($employee)
            : null;

        return view('attendance.show', [
            'employee' => $employee,
            'employeeCode' => $this->attendanceEmployeeRouteValue($employee),
            'rawRecords' => $rawRecords,
            'punchHistory' => $punchHistory,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'historyDateFrom' => $historyDateFrom,
            'historyDateTo' => $historyDateTo,
            'activeDateFrom' => $activeDateFrom,
            'activeDateTo' => $activeDateTo,
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
            'dayLog' => $dayLog->take(180),
            'dayLogTotal' => $dayLog->count(),
            'periodSummary' => $periodSummary,
            'monthlyTrend' => $monthlyTrend,
            'sickCycleSummary' => $sickCycleSummary,
            'leaveSummary' => $leaveSummary,
        ]);
    }

    private function summariseDayLog(\Illuminate\Support\Collection $dayLog): array
    {
        $present = 0;
        $late = 0;
        $lateMinutes = 0;
        $early = 0;
        $earlyMinutes = 0;
        $missing = 0;
        $absent = 0;
        $onLeave = 0;

        foreach ($dayLog as $entry) {
            $day = $entry['day'];

            if (!$entry['is_workday']) {
                continue;
            }

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
                    $missing++;
                }
                continue;
            }

            if ($entry['leave_label']) {
                $onLeave++;
            } else {
                $absent++;
            }
        }

        return [
            'present_days' => $present,
            'late_days' => $late,
            'late_minutes' => $lateMinutes,
            'late_label' => $this->formatMinutes($lateMinutes),
            'early_leave_days' => $early,
            'early_leave_minutes' => $earlyMinutes,
            'early_leave_label' => $this->formatMinutes($earlyMinutes),
            'missing_checkout_days' => $missing,
            'absent_days' => $absent,
            'on_leave_days' => $onLeave,
        ];
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
        $candidates = $this->employeeCodeCandidates($identifier);

        return User::query()
            ->where(function ($query) use ($identifier, $candidates) {
                if (Schema::hasColumn('users', 'employee_code')) {
                    $query->whereIn('employee_code', $candidates);
                }

                if (Schema::hasColumn('users', 'attendance_employee_code')) {
                    $query->orWhereIn('attendance_employee_code', $candidates);
                }

                if (Schema::hasColumn('users', 'attendance_name')) {
                    $query->orWhere('attendance_name', $identifier);
                }

                $query->orWhere('email', $identifier);

                if (is_numeric($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
            })
            ->first();
    }

    private function employeeCodeCandidates(string $identifier): array
    {
        $clean = trim($identifier);
        $clean = trim($clean, "' \t\n\r\0\x0B");

        if ($clean === '') {
            return [$identifier];
        }

        $candidates = [$identifier, $clean];

        if (ctype_digit($clean)) {
            $stripped = ltrim($clean, '0');
            if ($stripped !== '') {
                $candidates[] = $stripped;
                $candidates[] = str_pad($stripped, 4, '0', STR_PAD_LEFT);
            }
        }

        return array_values(array_unique(array_filter($candidates, fn ($value) => $value !== '')));
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
