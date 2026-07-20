<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

class InstallController extends Controller
{
    public function index()
    {
        return view('install.index', [
            'installed' => $this->installed(),
            'keyHint' => env('INSTALLER_KEY') ? 'Use the INSTALLER_KEY value from your .env file.' : 'Add INSTALLER_KEY=your-secure-key to .env before installing.',
        ]);
    }

    public function install(Request $request)
    {
        if ($this->installed()) {
            return redirect()->route('login')->with('success', 'ISO Admin is already installed.');
        }

        $data = $request->validate([
            'installer_key' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!$this->validInstallerKey($data['installer_key'])) {
            return back()->withErrors(['installer_key' => 'Invalid installer key.'])->withInput();
        }

        DB::transaction(function () use ($data) {
            $this->createTables();
            $this->seedPermissionsAndRoles();
            $this->createFirstSystemAdministrator($data);
        });

        $this->writeInstallLock();

        return redirect()->route('login')->with('success', 'ISO Admin installed. Log in with the System Administrator account you created.');
    }

    private function installed(): bool
    {
        try {
            return file_exists(storage_path('app/isoadmin_installed.lock'))
                && Schema::hasTable('users')
                && Schema::hasTable('roles')
                && Schema::hasTable('permissions');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function validInstallerKey(string $key): bool
    {
        $expected = env('INSTALLER_KEY');

        if (!$expected) {
            $expected = substr(hash('sha256', config('app.key') . '|isoadmin'), 0, 24);
        }

        return hash_equals((string) $expected, $key);
    }

    private function writeInstallLock(): void
    {
        $path = storage_path('app');

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        file_put_contents($path . '/isoadmin_installed.lock', now()->toDateTimeString());
    }

    private function createTables(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'employee_code')) {
                $table->string('employee_code')->nullable()->after('id');
            }
            if (!Schema::hasColumn('users', 'attendance_name')) {
                $table->string('attendance_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'position')) {
                $table->string('position')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('active')->after('position');
            }
            if (!Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('status');
            }
            if (!Schema::hasColumn('users', 'password_changed_at')) {
                $table->dateTime('password_changed_at')->nullable()->after('must_change_password');
            }
            if (!Schema::hasColumn('users', 'credentials_emailed_at')) {
                $table->dateTime('credentials_emailed_at')->nullable()->after('password_changed_at');
            }
        });

        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('level')->default(10);
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('module')->index();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('department_user')) {
            Schema::create('department_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('department_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['department_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['role_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $table) {
                $table->id();
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['permission_id', 'role_id']);
            });
        }

        if (!Schema::hasTable('permission_user')) {
            Schema::create('permission_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['permission_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('employee_profiles')) {
            Schema::create('employee_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('employee_number')->nullable();
                $table->string('job_title')->nullable();
                $table->string('phone')->nullable();
                $table->string('mobile')->nullable();
                $table->string('emergency_contact')->nullable();
                $table->date('started_at')->nullable();
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('employee_documents')) {
            Schema::create('employee_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('document_type')->index();
                $table->string('title');
                $table->string('file_path');
                $table->string('original_filename');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->boolean('has_expiry')->default(false);
                $table->date('expires_at')->nullable()->index();
                $table->unsignedInteger('remind_days_before')->nullable();
                $table->date('reminder_date')->nullable()->index();
                $table->dateTime('last_reminder_sent_at')->nullable();
                $table->string('status')->default('active')->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('attendance_imports')) {
            Schema::create('attendance_imports', function (Blueprint $table) {
                $table->id();
                $table->string('source')->default('upload');
                $table->string('source_identifier')->nullable()->index();
                $table->string('filename')->nullable();
                $table->string('received_from')->nullable();
                $table->string('received_subject')->nullable();
                $table->dateTime('received_at')->nullable();
                $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('raw_rows')->default(0);
                $table->unsignedInteger('matched_rows')->default(0);
                $table->unsignedInteger('skipped_rows')->default(0);
                $table->unsignedInteger('day_rows')->default(0);
                $table->string('status')->default('completed');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('attendance_raw_records')) {
            Schema::create('attendance_raw_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attendance_import_id')->nullable()->constrained('attendance_imports')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('employee_name')->index();
                $table->string('attendance_status')->nullable()->index();
                $table->dateTime('recorded_at')->index();
                $table->date('attendance_date')->index();
                $table->string('source_row_hash', 64)->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('attendance_days')) {
            Schema::create('attendance_days', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->date('attendance_date')->index();
                $table->dateTime('start_time')->nullable();
                $table->dateTime('end_time')->nullable();
                $table->string('first_status')->nullable();
                $table->string('last_status')->nullable();
                $table->unsignedInteger('record_count')->default(0);
                $table->unsignedInteger('work_minutes')->default(0);
                $table->foreignId('attendance_import_id')->nullable()->constrained('attendance_imports')->nullOnDelete();
                $table->text('source_names')->nullable();
                $table->text('anomalies')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'attendance_date']);
            });
        }


        if (!Schema::hasTable('vehicles')) {
            Schema::create('vehicles', function (Blueprint $table) {
                $table->id();
                $table->string('make');
                $table->string('model');
                $table->unsignedSmallInteger('year_model')->nullable()->index();
                $table->string('colour')->nullable();
                $table->string('tracking_company_name')->nullable();
                $table->string('tracking_company_contact')->nullable();
                $table->string('tracking_device_number')->nullable();
                $table->text('tracking_notes')->nullable();
                $table->unsignedInteger('odo')->default(0);
                $table->unsignedInteger('service_interval_km')->default(0);
                $table->unsignedInteger('service_reminder_km')->default(1000);
                $table->string('registration_number')->nullable()->index();
                $table->string('vehicle_key')->nullable()->index();
                $table->string('status')->default('active')->index();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vehicle_assignments')) {
            Schema::create('vehicle_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('assigned_at')->index();
                $table->dateTime('unassigned_at')->nullable()->index();
                $table->string('status')->default('active')->index();
                $table->boolean('policy_warning')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vehicle_fuel_ups')) {
            Schema::create('vehicle_fuel_ups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('source')->default('manual')->index();
                $table->string('car_name')->nullable()->index();
                $table->string('model_name')->nullable();
                $table->decimal('km_per_litre', 10, 2)->nullable();
                $table->unsignedInteger('odometer')->nullable()->index();
                $table->decimal('km', 10, 2)->nullable();
                $table->decimal('litres', 10, 2)->nullable();
                $table->decimal('price_per_litre', 10, 2)->nullable();
                $table->decimal('total_cost', 12, 2)->nullable();
                $table->decimal('city_percentage', 6, 2)->nullable();
                $table->date('fuelup_date')->index();
                $table->dateTime('date_added')->nullable();
                $table->string('tags')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('missed_fuelup')->default(false);
                $table->boolean('partial_fuelup')->default(false);
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('brand')->nullable();
                $table->string('source_row_hash', 64)->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vehicle_documents')) {
            Schema::create('vehicle_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('document_type')->index();
                $table->string('title');
                $table->string('file_path');
                $table->string('original_filename');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->boolean('has_expiry')->default(false);
                $table->date('expires_at')->nullable()->index();
                $table->unsignedInteger('remind_days_before')->nullable();
                $table->date('reminder_date')->nullable()->index();
                $table->dateTime('last_reminder_sent_at')->nullable();
                $table->string('status')->default('active')->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vehicle_service_records')) {
            Schema::create('vehicle_service_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('service_date')->index();
                $table->unsignedInteger('service_odo')->index();
                $table->unsignedInteger('next_service_odo_snapshot')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('dashboard_widget_preferences')) {
            Schema::create('dashboard_widget_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('widget_key')->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('size')->default('medium');
                $table->boolean('is_visible')->default(true);
                $table->timestamps();
                $table->unique(['user_id', 'widget_key']);
            });
        }

        if (!Schema::hasTable('leave_types')) {
            Schema::create('leave_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_deductible')->default(true);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(10);
                $table->timestamps();
            });
        }
    }

    private function seedPermissionsAndRoles(): void
    {
        $permissions = $this->defaultPermissions();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        $systemAdministrator = Role::firstOrCreate(
            ['slug' => 'system-administrator'],
            ['name' => 'System Administrator', 'description' => 'Top-level platform owner with unrestricted access.', 'level' => 110, 'is_system' => true]
        );

        $director = Role::firstOrCreate(
            ['slug' => 'director'],
            ['name' => 'Director', 'description' => 'Full company command access.', 'level' => 100, 'is_system' => true]
        );

        $manager = Role::firstOrCreate(
            ['slug' => 'manager'],
            ['name' => 'Manager', 'description' => 'Department and operational management access.', 'level' => 70, 'is_system' => true]
        );

        $employee = Role::firstOrCreate(
            ['slug' => 'employee'],
            ['name' => 'Employee', 'description' => 'Base employee access.', 'level' => 10, 'is_system' => true]
        );

        $allPermissionIds = Permission::pluck('id')->all();
        $systemAdministrator->permissions()->sync($allPermissionIds);
        $director->permissions()->sync($allPermissionIds);

        $managerPermissionIds = Permission::whereIn('slug', [
            'dashboard.view',
            'dashboard.customize',
            'employees.view',
            'employees.create',
            'employees.edit',
            'departments.view',
            'clients.view',
            'clients.manage',
            'equipment.view',
            'equipment.manage',
            'employee_documents.view',
            'employee_documents.upload',
            'employee_documents.manage',
            'employee_compliance.view',
            'attendance.view',
            'attendance.import',
            'cron_jobs.view',
            'cron_jobs.run',
            'profile.view',
            'leave_types.view',
            'leave_types.manage',
            'vehicle.view',
            'vehicle.create',
            'vehicle.edit',
            'vehicle.assign',
            'vehicle.fuel.view',
            'vehicle.fuel.manage',
            'vehicle.fuel.import',
            'vehicle.documents.view',
            'vehicle.documents.upload',
            'vehicle.documents.manage',
            'vehicle.reminders.view',
            'vehicle.service.view',
            'vehicle.service.manage',
            'vehicle.service.reminders.view',
        ])->pluck('id')->all();
        $manager->permissions()->sync($managerPermissionIds);

        $employeePermissionIds = Permission::whereIn('slug', [
            'dashboard.view',
            'dashboard.customize',
            'profile.view',
        ])->pluck('id')->all();
        $employee->permissions()->sync($employeePermissionIds);

        $this->seedDefaultLeaveTypes();
    }

    private function seedDefaultLeaveTypes(): void
    {
        if (!Schema::hasTable('leave_types')) {
            return;
        }

        $defaults = [
            ['name' => 'Annual Leave', 'code' => 'ANNUAL', 'description' => 'Standard annual leave deducted from allocated leave.', 'is_deductible' => true, 'is_active' => true, 'sort_order' => 10],
            ['name' => 'Sick Leave', 'code' => 'SICK', 'description' => 'Sick leave with medical or sick-note support where required.', 'is_deductible' => true, 'is_active' => true, 'sort_order' => 20],
            ['name' => 'Family Responsibility Leave', 'code' => 'FAMILY', 'description' => 'Family responsibility leave.', 'is_deductible' => true, 'is_active' => true, 'sort_order' => 30],
            ['name' => 'Unpaid Leave', 'code' => 'UNPAID', 'description' => 'Leave that is not deducted from paid leave allocation.', 'is_deductible' => false, 'is_active' => true, 'sort_order' => 40],
            ['name' => 'Special Leave', 'code' => 'SPECIAL', 'description' => 'Company-approved special leave.', 'is_deductible' => false, 'is_active' => true, 'sort_order' => 50],
        ];

        foreach ($defaults as $leaveType) {
            DB::table('leave_types')->updateOrInsert(['code' => $leaveType['code']], array_merge($leaveType, ['updated_at' => now(), 'created_at' => now()]));
        }
    }

    private function createFirstSystemAdministrator(array $data): void
    {
        $department = Department::firstOrCreate(
            ['slug' => 'management'],
            ['name' => 'Management', 'description' => 'Company leadership and central administration.', 'is_active' => true]
        );

        $systemAdministratorRole = Role::where('slug', 'system-administrator')->firstOrFail();

        $user = User::updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
                'status' => 'active',
                'position' => 'System Administrator',
                'must_change_password' => false,
                'password_changed_at' => now(),
            ]
        );

        $user->departments()->syncWithoutDetaching([$department->id]);
        $user->roles()->syncWithoutDetaching([$systemAdministratorRole->id]);
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            ['job_title' => 'System Administrator', 'status' => 'active']
        );
    }

    private function defaultPermissions(): array
    {
        return [
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view', 'module' => 'Dashboard', 'description' => 'Access the central command dashboard.'],
            ['name' => 'Customise Own Dashboard', 'slug' => 'dashboard.customize', 'module' => 'Dashboard', 'description' => 'Edit personal dashboard widgets, ordering and size.'],
            ['name' => 'View Employees', 'slug' => 'employees.view', 'module' => 'Employees', 'description' => 'View employee records.'],
            ['name' => 'Create Employees', 'slug' => 'employees.create', 'module' => 'Employees', 'description' => 'Create new employee records.'],
            ['name' => 'Edit Employees', 'slug' => 'employees.edit', 'module' => 'Employees', 'description' => 'Update employee details, departments and roles.'],
            ['name' => 'Deactivate Employees', 'slug' => 'employees.delete', 'module' => 'Employees', 'description' => 'Deactivate employee access.'],
            ['name' => 'View Departments', 'slug' => 'departments.view', 'module' => 'Departments', 'description' => 'View departments.'],
            ['name' => 'Create Departments', 'slug' => 'departments.create', 'module' => 'Departments', 'description' => 'Create company departments.'],
            ['name' => 'Edit Departments', 'slug' => 'departments.edit', 'module' => 'Departments', 'description' => 'Update department details.'],
            ['name' => 'Delete Departments', 'slug' => 'departments.delete', 'module' => 'Departments', 'description' => 'Delete departments.'],
            ['name' => 'View Roles', 'slug' => 'roles.view', 'module' => 'Permissions', 'description' => 'View roles and permissions.'],
            ['name' => 'Create Roles', 'slug' => 'roles.create', 'module' => 'Permissions', 'description' => 'Create custom access roles.'],
            ['name' => 'Edit Roles', 'slug' => 'roles.edit', 'module' => 'Permissions', 'description' => 'Assign permissions to roles.'],
            ['name' => 'Delete Roles', 'slug' => 'roles.delete', 'module' => 'Permissions', 'description' => 'Delete non-system roles.'],
            ['name' => 'Manage Permissions', 'slug' => 'permissions.manage', 'module' => 'Permissions', 'description' => 'Full permission matrix administration.'],
            ['name' => 'View Clients', 'slug' => 'clients.view', 'module' => 'Clients', 'description' => 'Future module permission: view clients.'],
            ['name' => 'Manage Clients', 'slug' => 'clients.manage', 'module' => 'Clients', 'description' => 'Future module permission: create and update clients.'],
            ['name' => 'View Equipment', 'slug' => 'equipment.view', 'module' => 'Equipment', 'description' => 'Future module permission: view equipment.'],
            ['name' => 'Manage Equipment', 'slug' => 'equipment.manage', 'module' => 'Equipment', 'description' => 'Future module permission: create and update equipment.'],
            ['name' => 'View Employee Documents', 'slug' => 'employee_documents.view', 'module' => 'Employee Documents', 'description' => 'View employee profile documents and document reminders.'],
            ['name' => 'Upload Employee Documents', 'slug' => 'employee_documents.upload', 'module' => 'Employee Documents', 'description' => 'Upload medicals, sick notes, warnings, certificates and policies.'],
            ['name' => 'Manage Employee Documents', 'slug' => 'employee_documents.manage', 'module' => 'Employee Documents', 'description' => 'Mark employee documents inactive and manage document lifecycle.'],
            ['name' => 'View Employee Compliance', 'slug' => 'employee_compliance.view', 'module' => 'Employee Documents', 'description' => 'View the employee document compliance overview.'],
            ['name' => 'View Attendance', 'slug' => 'attendance.view', 'module' => 'Attendance', 'description' => 'View employee time attendance.'],
            ['name' => 'Import Attendance', 'slug' => 'attendance.import', 'module' => 'Attendance', 'description' => 'Upload CSV files and import attendance from email.'],
            ['name' => 'Director Manual Attendance Upload', 'slug' => 'attendance.manual_upload', 'module' => 'Attendance', 'description' => 'Allows directors to manually upload attendance CSV exports.'],
            ['name' => 'Manage Attendance Settings', 'slug' => 'attendance.manage', 'module' => 'Attendance', 'description' => 'Manage attendance import and future attendance settings.'],
            ['name' => 'View Vehicles', 'slug' => 'vehicle.view', 'module' => 'Vehicles', 'description' => 'View vehicles, assignments and fuel records.'],
            ['name' => 'Create Vehicles', 'slug' => 'vehicle.create', 'module' => 'Vehicles', 'description' => 'Add company vehicles.'],
            ['name' => 'Edit Vehicles', 'slug' => 'vehicle.edit', 'module' => 'Vehicles', 'description' => 'Edit and deactivate company vehicles.'],
            ['name' => 'Assign Vehicles', 'slug' => 'vehicle.assign', 'module' => 'Vehicles', 'description' => 'Assign vehicles to employees, managers and directors.'],
            ['name' => 'View Fuel Records', 'slug' => 'vehicle.fuel.view', 'module' => 'Fuel Tracking', 'description' => 'View fuel-up and usage records.'],
            ['name' => 'Manage Fuel Records', 'slug' => 'vehicle.fuel.manage', 'module' => 'Fuel Tracking', 'description' => 'Manually add fuel-up records.'],
            ['name' => 'Import Fuel CSV', 'slug' => 'vehicle.fuel.import', 'module' => 'Fuel Tracking', 'description' => 'Import fuel-up CSV exports.'],
            ['name' => 'View Vehicle Documents', 'slug' => 'vehicle.documents.view', 'module' => 'Vehicle Documents', 'description' => 'View NATIS, license disk and vehicle documents.'],
            ['name' => 'Upload Vehicle Documents', 'slug' => 'vehicle.documents.upload', 'module' => 'Vehicle Documents', 'description' => 'Upload NATIS, license disk and related vehicle documents.'],
            ['name' => 'Manage Vehicle Documents', 'slug' => 'vehicle.documents.manage', 'module' => 'Vehicle Documents', 'description' => 'Mark vehicle documents inactive.'],
            ['name' => 'View Vehicle Reminders', 'slug' => 'vehicle.reminders.view', 'module' => 'Vehicle Documents', 'description' => 'View expiring vehicle document reminders.'],
            ['name' => 'View Vehicle Services', 'slug' => 'vehicle.service.view', 'module' => 'Vehicle Services', 'description' => 'View vehicle service history and service interval status.'],
            ['name' => 'Manage Vehicle Services', 'slug' => 'vehicle.service.manage', 'module' => 'Vehicle Services', 'description' => 'Add vehicle service records and manage service tracking.'],
            ['name' => 'View Vehicle Service Reminders', 'slug' => 'vehicle.service.reminders.view', 'module' => 'Vehicle Services', 'description' => 'View upcoming and overdue vehicle service reminders.'],
            ['name' => 'View Own Profile', 'slug' => 'profile.view', 'module' => 'Profile', 'description' => 'View own user profile page.'],
            ['name' => 'View Cron Jobs', 'slug' => 'cron_jobs.view', 'module' => 'Cron Jobs', 'description' => 'View cron job URLs and job status.'],
            ['name' => 'Run Cron Jobs Manually', 'slug' => 'cron_jobs.run', 'module' => 'Cron Jobs', 'description' => 'Manually trigger safe cron jobs from the web interface.'],
            ['name' => 'View Leave Types', 'slug' => 'leave_types.view', 'module' => 'Leave Settings', 'description' => 'View leave types available to employees.'],
            ['name' => 'Manage Leave Types', 'slug' => 'leave_types.manage', 'module' => 'Leave Settings', 'description' => 'Create and edit leave types and whether they deduct allocated leave.'],
            ['name' => 'Manage Settings', 'slug' => 'settings.manage', 'module' => 'Settings', 'description' => 'Manage platform-level settings.'],
        ];
    }
}
