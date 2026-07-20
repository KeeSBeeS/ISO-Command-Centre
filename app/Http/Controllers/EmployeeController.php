<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\Department;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
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

    public function show(User $employee)
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

        return view('employees.show', compact('employee'));
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
