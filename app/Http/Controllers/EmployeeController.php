<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\Department;
use App\Models\PublicHoliday;
use App\Models\Role;
use App\Models\Permission;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $employees = User::query()
            ->with(['profile', 'departments', 'roles'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                    if (Schema::hasColumn('users', 'attendance_name')) {
                        $q->orWhere('attendance_name', 'like', "%{$search}%");
                    }
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('employees.index', compact('employees'));
    }

    public function create()
    {
        return view('employees.create', [
            'employee' => new User(['status' => 'active']),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'roles' => Role::orderByDesc('level')->orderBy('name')->get(),
            'selectedDepartments' => [],
            'selectedRoles' => [],
            'selectedDirectPermissions' => [],
            'permissions' => Permission::orderBy('module')->orderBy('name')->get()->groupBy('module'),
            'profile' => null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $temporaryPassword = $data['password'] ?: $this->generatePassword();

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($temporaryPassword),
            'employee_code' => $data['employee_code'] ?? null,
            'phone' => $data['phone'] ?? null,
            'position' => $data['job_title'] ?? null,
            'status' => $data['status'],
        ];
        if (Schema::hasColumn('users', 'attendance_name')) {
            $userData['attendance_name'] = $data['attendance_name'] ?? null;
        }
        if (Schema::hasColumn('users', 'must_change_password')) {
            $userData['must_change_password'] = true;
        }

        $employee = User::create($userData);

        $employee->profile()->create([
            'employee_number' => $data['employee_number'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'phone' => $data['phone'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'emergency_contact' => $data['emergency_contact'] ?? null,
            'started_at' => $data['started_at'] ?? null,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);

        $employee->departments()->sync($data['department_ids'] ?? []);
        $employee->roles()->sync($data['role_ids'] ?? []);
        if (Schema::hasTable('permission_user') && $request->user() && $request->user()->hasPermission('permissions.manage')) {
            $employee->directPermissions()->sync($data['direct_permission_ids'] ?? []);
        }

        $emailSent = $this->sendNewUserCredentials($employee, $temporaryPassword);

        if ($emailSent && Schema::hasColumn('users', 'credentials_emailed_at')) {
            $employee->forceFill(['credentials_emailed_at' => now()])->save();
        }

        $message = $emailSent
            ? 'Employee created. Login details were emailed to the user. They must change the password on first login.'
            : 'Employee created, but the login email could not be sent. Temporary password: ' . $temporaryPassword;

        return redirect()->route('employees.show', $employee)->with($emailSent ? 'success' : 'warning', $message);
    }

    public function show(Request $request, User $employee)
    {
        $relations = ['profile', 'departments', 'roles.permissions'];
        if (Schema::hasTable('permission_user')) {
            $relations[] = 'directPermissions';
        }
        if (Schema::hasTable('employee_documents')) {
            $relations[] = 'documents.uploader';
        }
        if (Schema::hasTable('vehicle_assignments')) {
            $relations[] = 'currentVehicleAssignments.vehicle';
        }

        $employee->load($relations);

        $attendanceOverview = $this->buildAttendanceOverview($request, $employee);

        return view('employees.show', compact('employee', 'attendanceOverview'));
    }

    /**
     * Build a date-range filtered time-attendance register for an employee.
     *
     * The register mirrors the biometric "Start/End Work Time" and "Late" reports:
     * every working day in the selected range is listed with its shift/timetable,
     * check-in, check-out and how many minutes late the clock-in was, plus a set of
     * at-a-glance totals. When no date range is supplied it defaults to the last
     * four weeks of available data.
     */
    private function buildAttendanceOverview(Request $request, User $employee): ?array
    {
        if (!Schema::hasTable('attendance_days')) {
            return null;
        }

        $startTime = $this->normaliseAttendanceTime(SystemSetting::valueFor('attendance_company_start_time', '06:00'), '06:00');
        $closeTime = $this->normaliseAttendanceTime(SystemSetting::valueFor('attendance_company_close_time', '15:00'), '15:00');
        $startMinutes = $this->attendanceMinutes($startTime);
        $closeMinutes = $this->attendanceMinutes($closeTime);

        $availableFrom = AttendanceDay::where('user_id', $employee->id)->min('attendance_date');
        $availableTo = AttendanceDay::where('user_id', $employee->id)->max('attendance_date');

        $latest = $availableTo ? Carbon::parse($availableTo) : Carbon::today();
        $defaultTo = $latest->toDateString();
        $defaultFrom = $latest->copy()->subDays(27)->toDateString();
        if ($availableFrom && $defaultFrom < $availableFrom) {
            $defaultFrom = $availableFrom;
        }

        $dateFrom = $this->normaliseAttendanceDate($request->input('att_from'), $defaultFrom);
        $dateTo = $this->normaliseAttendanceDate($request->input('att_to'), $defaultTo);
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        // Guard against an unbounded iteration on very wide ranges.
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
            $isWeekend = $cursor->isWeekend();

            // Skip weekends unless the employee actually clocked in that day.
            if ($isWeekend && !$day) {
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
                'late_label' => $rowLate > 0 ? $this->formatAttendanceMinutes($rowLate) : null,
                'early_minutes' => $rowEarly,
                'early_label' => $rowEarly > 0 ? $this->formatAttendanceMinutes($rowEarly) : null,
                'holiday_name' => $isHoliday ? $holidayName : null,
                'record_count' => $day ? (int) $day->record_count : 0,
                'day' => $day,
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
                'late_label' => $this->formatAttendanceMinutes($lateMinutes),
                'early_days' => $earlyDays,
                'early_minutes' => $earlyMinutes,
                'early_label' => $this->formatAttendanceMinutes($earlyMinutes),
                'public_holidays' => $holidayCount,
            ],
        ];
    }

    private function normaliseAttendanceTime(?string $value, string $default): string
    {
        $value = trim((string) $value);

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : $default;
    }

    private function attendanceMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return ($hour * 60) + $minute;
    }

    private function normaliseAttendanceDate(?string $value, string $default): string
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

    private function formatAttendanceMinutes(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours > 0
            ? sprintf('%dh %02dm', $hours, $remaining)
            : sprintf('%d min', $remaining);
    }

    public function edit(User $employee)
    {
        $editRelations = ['profile', 'departments', 'roles'];
        if (Schema::hasTable('permission_user')) {
            $editRelations[] = 'directPermissions';
        }
        $employee->load($editRelations);

        return view('employees.edit', [
            'employee' => $employee,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'roles' => Role::orderByDesc('level')->orderBy('name')->get(),
            'selectedDepartments' => $employee->departments->pluck('id')->all(),
            'selectedRoles' => $employee->roles->pluck('id')->all(),
            'selectedDirectPermissions' => Schema::hasTable('permission_user') ? $employee->directPermissions->pluck('id')->all() : [],
            'permissions' => Permission::orderBy('module')->orderBy('name')->get()->groupBy('module'),
            'profile' => $employee->profile,
        ]);
    }

    public function update(Request $request, User $employee)
    {
        $data = $this->validated($request, $employee->id);

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'employee_code' => $data['employee_code'] ?? null,
            'phone' => $data['phone'] ?? null,
            'position' => $data['job_title'] ?? null,
            'status' => $data['status'],
        ];
        if (Schema::hasColumn('users', 'attendance_name')) {
            $userData['attendance_name'] = $data['attendance_name'] ?? null;
        }

        $employee->fill($userData);

        $newPassword = null;
        if (!empty($data['password'])) {
            $newPassword = $data['password'];
            $employee->password = Hash::make($newPassword);
            if (Schema::hasColumn('users', 'must_change_password')) {
                $employee->must_change_password = true;
            }
        }

        $employee->save();

        $employee->profile()->updateOrCreate(
            ['user_id' => $employee->id],
            [
                'employee_number' => $data['employee_number'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'phone' => $data['phone'] ?? null,
                'mobile' => $data['mobile'] ?? null,
                'emergency_contact' => $data['emergency_contact'] ?? null,
                'started_at' => $data['started_at'] ?? null,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
            ]
        );

        $employee->departments()->sync($data['department_ids'] ?? []);
        $employee->roles()->sync($data['role_ids'] ?? []);
        if (Schema::hasTable('permission_user') && $request->user() && $request->user()->hasPermission('permissions.manage')) {
            $employee->directPermissions()->sync($data['direct_permission_ids'] ?? []);
        }

        if ($newPassword) {
            $emailSent = $this->sendNewUserCredentials($employee, $newPassword, true);
            if ($emailSent && Schema::hasColumn('users', 'credentials_emailed_at')) {
                $employee->forceFill(['credentials_emailed_at' => now()])->save();
            }

            return redirect()
                ->route('employees.show', $employee)
                ->with($emailSent ? 'success' : 'warning', $emailSent
                    ? 'Employee updated. New login details were emailed to the user and password change is required on next login.'
                    : 'Employee updated, but the password email could not be sent. New temporary password: ' . $newPassword);
        }

        return redirect()->route('employees.show', $employee)->with('success', 'Employee updated.');
    }

    public function destroy(Request $request, User $employee)
    {
        if ($request->user()->id === $employee->id) {
            return back()->withErrors(['employee' => 'You cannot deactivate your own account.']);
        }

        $employee->update(['status' => 'inactive']);
        $employee->profile()->update(['status' => 'inactive']);

        return redirect()->route('employees.index')->with('success', 'Employee access deactivated.');
    }

    private function validated(Request $request, ?int $ignoreUserId = null): array
    {
        $uniqueEmail = 'unique:users,email';
        if ($ignoreUserId) {
            $uniqueEmail .= ',' . $ignoreUserId;
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'attendance_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', $uniqueEmail],
            'password' => ['nullable', 'string', 'min:8'],
            'employee_code' => ['nullable', 'string', 'max:100'],
            'employee_number' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:100'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'started_at' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'direct_permission_ids' => ['nullable', 'array'],
            'direct_permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);
    }

    private function generatePassword(int $length = 14): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $password = '';
        $max = strlen($alphabet) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }

    private function sendNewUserCredentials(User $employee, string $plainPassword, bool $reset = false): bool
    {
        try {
            $loginUrl = url('/login');
            $subject = $reset ? 'ISO Admin login details updated' : 'Your ISO Admin login details';
            $body = "Hello {$employee->name},\n\n";
            $body .= $reset
                ? "Your ISO Admin login password has been reset.\n\n"
                : "Your ISO Admin user profile has been created.\n\n";
            $body .= "Platform: ISO Admin Central Command\n";
            $body .= "Login URL: {$loginUrl}\n";
            $body .= "Email: {$employee->email}\n";
            $body .= "Temporary password: {$plainPassword}\n\n";
            $body .= "You will be asked to change this password on first login before you can access the dashboard.\n\n";
            $body .= "Regards,\nISO Admin";

            Mail::raw($body, function ($message) use ($employee, $subject) {
                $message->to($employee->email)->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }
}
