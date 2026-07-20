<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Schema\Blueprint;

class UpdateController extends Controller
{
    public function v11()
    {
        return view('updates.v1_1', [
            'attendanceInstalled' => Schema::hasTable('attendance_days') && Schema::hasTable('attendance_imports') && Schema::hasTable('attendance_raw_records'),
        ]);
    }

    public function applyV11(Request $request)
    {
        DB::transaction(function () {
            $this->addAttendanceTables();
            $this->seedAttendancePermissions();
            $this->seedManualAttendanceUploadPermission();
        });

        return redirect()->route('updates.v1_1')->with('success', 'Version 1.1 update applied. Attendance management is now available.');
    }


    public function v12()
    {
        $permissionExists = Permission::where('slug', 'attendance.manual_upload')->exists();
        $directorHasPermission = false;

        if ($director = Role::where('slug', 'director')->first()) {
            $directorHasPermission = $director->permissions()->where('slug', 'attendance.manual_upload')->exists();
        }

        return view('updates.v1_2', [
            'permissionExists' => $permissionExists,
            'directorHasPermission' => $directorHasPermission,
        ]);
    }

    public function applyV12(Request $request)
    {
        DB::transaction(function () {
            $this->seedManualAttendanceUploadPermission();
        });

        return redirect()->route('updates.v1_2')->with('success', 'Version 1.2 update applied. Director manual attendance CSV upload is now available.');
    }

    public function v13()
    {
        $documentsInstalled = Schema::hasTable('employee_documents');
        $permissionCount = Permission::whereIn('slug', [
            'employee_documents.view',
            'employee_documents.upload',
            'employee_documents.manage',
        ])->count();

        return view('updates.v1_3', [
            'documentsInstalled' => $documentsInstalled,
            'permissionCount' => $permissionCount,
        ]);
    }

    public function applyV13(Request $request)
    {
        DB::transaction(function () {
            $this->addEmployeeDocumentTables();
            $this->seedEmployeeDocumentPermissions();
        });

        return redirect()->route('updates.v1_3')->with('success', 'Version 1.3 update applied. Employee profile documents and expiry reminders are now available.');
    }

    private function addEmployeeDocumentTables(): void
    {
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
    }

    private function addAttendanceTables(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'attendance_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('attendance_name')->nullable()->after('name');
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
    }

    private function seedManualAttendanceUploadPermission(): void
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'attendance.manual_upload'],
            [
                'name' => 'Director Manual Attendance Upload',
                'module' => 'Attendance',
                'description' => 'Allows directors to manually upload attendance CSV exports.',
            ]
        );

        if ($director = Role::where('slug', 'director')->first()) {
            $director->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    private function seedEmployeeDocumentPermissions(): void
    {
        $permissions = [
            ['name' => 'View Employee Documents', 'slug' => 'employee_documents.view', 'module' => 'Employee Documents', 'description' => 'View employee profile documents and document reminders.'],
            ['name' => 'Upload Employee Documents', 'slug' => 'employee_documents.upload', 'module' => 'Employee Documents', 'description' => 'Upload medicals, sick notes, warnings, certificates and policies.'],
            ['name' => 'Manage Employee Documents', 'slug' => 'employee_documents.manage', 'module' => 'Employee Documents', 'description' => 'Mark employee documents inactive and manage document lifecycle.'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        $ids = Permission::whereIn('slug', [
            'employee_documents.view',
            'employee_documents.upload',
            'employee_documents.manage',
        ])->pluck('id')->all();

        if ($director = Role::where('slug', 'director')->first()) {
            $director->permissions()->syncWithoutDetaching($ids);
        }
        if ($manager = Role::where('slug', 'manager')->first()) {
            $manager->permissions()->syncWithoutDetaching($ids);
        }
    }

    private function seedAttendancePermissions(): void
    {
        $permissions = [
            ['name' => 'View Attendance', 'slug' => 'attendance.view', 'module' => 'Attendance', 'description' => 'View employee time attendance.'],
            ['name' => 'Import Attendance', 'slug' => 'attendance.import', 'module' => 'Attendance', 'description' => 'Upload CSV files and import attendance from email.'],
            ['name' => 'Manage Attendance Settings', 'slug' => 'attendance.manage', 'module' => 'Attendance', 'description' => 'Manage attendance import and future attendance settings.'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        $attendancePermissionIds = Permission::whereIn('slug', ['attendance.view', 'attendance.import', 'attendance.manage'])->pluck('id')->all();
        $managerPermissionIds = Permission::whereIn('slug', ['attendance.view', 'attendance.import'])->pluck('id')->all();
        $employeePermissionIds = Permission::whereIn('slug', ['attendance.view'])->pluck('id')->all();

        if ($director = Role::where('slug', 'director')->first()) {
            $director->permissions()->syncWithoutDetaching($attendancePermissionIds);
        }
        if ($manager = Role::where('slug', 'manager')->first()) {
            $manager->permissions()->syncWithoutDetaching($managerPermissionIds);
        }
        if ($employee = Role::where('slug', 'employee')->first()) {
            $employee->permissions()->syncWithoutDetaching($employeePermissionIds);
        }
    }

    public function v14()
    {
        $vehiclesInstalled = Schema::hasTable('vehicles')
            && Schema::hasTable('vehicle_assignments')
            && Schema::hasTable('vehicle_fuel_ups')
            && Schema::hasTable('vehicle_documents');

        $permissionCount = Permission::whereIn('slug', [
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
        ])->count();

        return view('updates.v1_4', [
            'vehiclesInstalled' => $vehiclesInstalled,
            'permissionCount' => $permissionCount,
        ]);
    }

    public function applyV14(Request $request)
    {
        DB::transaction(function () {
            $this->addVehicleTables();
            $this->seedVehiclePermissions();
        });

        return redirect()->route('updates.v1_4')->with('success', 'Version 1.4 update applied. Vehicle and fuel tracking is now available.');
    }

    private function addVehicleTables(): void
    {
        if (!Schema::hasTable('vehicles')) {
            Schema::create('vehicles', function (Blueprint $table) {
                $table->id();
                $table->string('make');
                $table->string('model');
                $table->unsignedInteger('odo')->default(0);
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
    }

    private function seedVehiclePermissions(): void
    {
        $permissions = [
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
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        $allVehiclePermissionIds = Permission::whereIn('slug', array_column($permissions, 'slug'))->pluck('id')->all();
        $managerPermissionIds = Permission::whereIn('slug', [
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
        ])->pluck('id')->all();

        if ($director = Role::where('slug', 'director')->first()) {
            $director->permissions()->syncWithoutDetaching($allVehiclePermissionIds);
        }
        if ($manager = Role::where('slug', 'manager')->first()) {
            $manager->permissions()->syncWithoutDetaching($managerPermissionIds);
        }
    }


    public function v15()
    {
        $dashboardPreferencesInstalled = Schema::hasTable('dashboard_widget_preferences');
        $permissionExists = Permission::where('slug', 'dashboard.customize')->exists();

        return view('updates.v1_5', [
            'dashboardPreferencesInstalled' => $dashboardPreferencesInstalled,
            'permissionExists' => $permissionExists,
        ]);
    }

    public function applyV15(Request $request)
    {
        DB::transaction(function () {
            $this->addDashboardWidgetPreferenceTable();
            $this->seedDashboardPermissions();
        });

        return redirect()->route('updates.v1_5')->with('success', 'Version 1.5 update applied. Dashboard customisation and automatic fuel KM calculation are now available.');
    }

    private function addDashboardWidgetPreferenceTable(): void
    {
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
    }

    private function seedDashboardPermissions(): void
    {
        $permissions = [
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view', 'module' => 'Dashboard', 'description' => 'View the central command dashboard.'],
            ['name' => 'Customise Own Dashboard', 'slug' => 'dashboard.customize', 'module' => 'Dashboard', 'description' => 'Edit personal dashboard widgets, ordering and size.'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        $ids = Permission::whereIn('slug', ['dashboard.view', 'dashboard.customize'])->pluck('id')->all();

        foreach (Role::whereIn('slug', ['director', 'manager', 'employee'])->get() as $role) {
            $role->permissions()->syncWithoutDetaching($ids);
        }
    }


    public function v16()
    {
        $columnsInstalled = Schema::hasTable('users')
            && Schema::hasColumn('users', 'must_change_password')
            && Schema::hasColumn('users', 'password_changed_at')
            && Schema::hasColumn('users', 'credentials_emailed_at');

        return view('updates.v1_6', [
            'columnsInstalled' => $columnsInstalled,
        ]);
    }

    public function applyV16(Request $request)
    {
        DB::transaction(function () {
            $this->addUserCredentialColumns();
        });

        return redirect()->route('updates.v1_6')->with('success', 'Version 1.6 update applied. New user welcome emails, password generator and forced first-login password changes are now available.');
    }

    private function addUserCredentialColumns(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
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

        User::query()
            ->whereNull('password_changed_at')
            ->update([
                'must_change_password' => false,
                'password_changed_at' => now(),
            ]);
    }



    public function v17()
    {
        $serviceInstalled = Schema::hasTable('vehicle_service_records')
            && Schema::hasTable('vehicles')
            && Schema::hasColumn('vehicles', 'service_interval_km')
            && Schema::hasColumn('vehicles', 'service_reminder_km');

        $permissionCount = Permission::whereIn('slug', [
            'vehicle.service.view',
            'vehicle.service.manage',
            'vehicle.service.reminders.view',
        ])->count();

        return view('updates.v1_7', [
            'serviceInstalled' => $serviceInstalled,
            'permissionCount' => $permissionCount,
        ]);
    }

    public function applyV17(Request $request)
    {
        DB::transaction(function () {
            $this->addVehicleServiceTracking();
            $this->seedVehicleServicePermissions();
        });

        return redirect()->route('updates.v1_7')->with('success', 'Version 1.7 update applied. Vehicle service intervals, service records and ODO-based service reminders are now available.');
    }

    private function addVehicleServiceTracking(): void
    {
        if (Schema::hasTable('vehicles') && !Schema::hasColumn('vehicles', 'service_interval_km')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->unsignedInteger('service_interval_km')->default(0)->after('odo');
            });
        }

        if (Schema::hasTable('vehicles') && !Schema::hasColumn('vehicles', 'service_reminder_km')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->unsignedInteger('service_reminder_km')->default(1000)->after('service_interval_km');
            });
        }

        if (Schema::hasTable('vehicles') && !Schema::hasTable('vehicle_service_records')) {
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
    }

    private function seedVehicleServicePermissions(): void
    {
        $permissions = [
            ['name' => 'View Vehicle Services', 'slug' => 'vehicle.service.view', 'module' => 'Vehicle Services', 'description' => 'View vehicle service history and service interval status.'],
            ['name' => 'Manage Vehicle Services', 'slug' => 'vehicle.service.manage', 'module' => 'Vehicle Services', 'description' => 'Add vehicle service records and manage service tracking.'],
            ['name' => 'View Vehicle Service Reminders', 'slug' => 'vehicle.service.reminders.view', 'module' => 'Vehicle Services', 'description' => 'View upcoming and overdue vehicle service reminders.'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        $ids = Permission::whereIn('slug', array_column($permissions, 'slug'))->pluck('id')->all();

        if ($director = Role::where('slug', 'director')->first()) {
            $director->permissions()->syncWithoutDetaching($ids);
        }
        if ($manager = Role::where('slug', 'manager')->first()) {
            $manager->permissions()->syncWithoutDetaching($ids);
        }
    }



    public function v25()
    {
        $permissionUserInstalled = Schema::hasTable('permission_user');
        $leaveTypesInstalled = Schema::hasTable('leave_types');
        $vehicleColumnsInstalled = Schema::hasTable('vehicles')
            && Schema::hasColumn('vehicles', 'year_model')
            && Schema::hasColumn('vehicles', 'colour')
            && Schema::hasColumn('vehicles', 'tracking_company_name');
        $systemAdministratorReady = Role::where('slug', 'system-administrator')->exists();

        $permissionCount = Permission::whereIn('slug', [
            'profile.view',
            'cron_jobs.view',
            'cron_jobs.run',
            'leave_types.view',
            'leave_types.manage',
        ])->count();

        return view('updates.v2_5', compact(
            'permissionUserInstalled',
            'leaveTypesInstalled',
            'vehicleColumnsInstalled',
            'systemAdministratorReady',
            'permissionCount'
        ));
    }

    public function applyV25(Request $request)
    {
        /*
         * Do not wrap this updater in DB::transaction().
         * MySQL auto-commits DDL statements such as CREATE TABLE and ALTER TABLE.
         * On shared hosting this can leave Laravel trying to commit/roll back a
         * transaction that MySQL has already closed, causing: "There is no active transaction".
         *
         * Every step below is intentionally idempotent, so this update can be
         * safely re-run after a partial v2.5 attempt.
         */
        $this->addV25TablesAndColumns();
        $this->seedV25PermissionsAndRoles($request->user());
        $this->seedV25LeaveTypes();

        return redirect()->route('updates.v2_5')->with('success', 'Version 2.5 update applied. Profiles, System Administrator role, direct user permissions, leave types, cron-job access and vehicle detail fields are now available.');
    }

    private function addV25TablesAndColumns(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasTable('users') && !Schema::hasTable('permission_user')) {
            Schema::create('permission_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['permission_id', 'user_id']);
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

        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicles', 'year_model')) {
                    $table->unsignedSmallInteger('year_model')->nullable()->after('model')->index();
                }
                if (!Schema::hasColumn('vehicles', 'colour')) {
                    $table->string('colour')->nullable()->after('year_model');
                }
                if (!Schema::hasColumn('vehicles', 'tracking_company_name')) {
                    $table->string('tracking_company_name')->nullable()->after('colour');
                }
                if (!Schema::hasColumn('vehicles', 'tracking_company_contact')) {
                    $table->string('tracking_company_contact')->nullable()->after('tracking_company_name');
                }
                if (!Schema::hasColumn('vehicles', 'tracking_device_number')) {
                    $table->string('tracking_device_number')->nullable()->after('tracking_company_contact');
                }
                if (!Schema::hasColumn('vehicles', 'tracking_notes')) {
                    $table->text('tracking_notes')->nullable()->after('tracking_device_number');
                }
            });
        }
    }

    private function seedV25PermissionsAndRoles(?User $currentUser = null): void
    {
        $permissions = [
            ['name' => 'View Own Profile', 'slug' => 'profile.view', 'module' => 'Profile', 'description' => 'View own user profile page.'],
            ['name' => 'View Cron Jobs', 'slug' => 'cron_jobs.view', 'module' => 'Cron Jobs', 'description' => 'View cron job URLs and job status.'],
            ['name' => 'Run Cron Jobs Manually', 'slug' => 'cron_jobs.run', 'module' => 'Cron Jobs', 'description' => 'Manually trigger safe cron jobs from the web interface.'],
            ['name' => 'View Leave Types', 'slug' => 'leave_types.view', 'module' => 'Leave Settings', 'description' => 'View leave types available to employees.'],
            ['name' => 'Manage Leave Types', 'slug' => 'leave_types.manage', 'module' => 'Leave Settings', 'description' => 'Create and edit leave types and whether they deduct allocated leave.'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        $systemAdministrator = Role::firstOrCreate(
            ['slug' => 'system-administrator'],
            ['name' => 'System Administrator', 'description' => 'Top-level platform owner with unrestricted access.', 'level' => 110, 'is_system' => true]
        );

        $allPermissionIds = Permission::pluck('id')->all();
        $systemAdministrator->permissions()->sync($allPermissionIds);

        if ($director = Role::where('slug', 'director')->first()) {
            $directorPermissionIds = Permission::whereNotIn('slug', ['core_settings.view', 'core_settings.manage'])->pluck('id')->all();
            $director->permissions()->syncWithoutDetaching($directorPermissionIds);
        }

        if ($manager = Role::where('slug', 'manager')->first()) {
            $managerIds = Permission::whereIn('slug', [
                'profile.view',
                'cron_jobs.view',
                'cron_jobs.run',
                'leave_types.view',
                'leave_types.manage',
            ])->pluck('id')->all();
            $manager->permissions()->syncWithoutDetaching($managerIds);
        }

        if ($employee = Role::where('slug', 'employee')->first()) {
            $profileId = Permission::where('slug', 'profile.view')->value('id');
            if ($profileId) {
                $employee->permissions()->syncWithoutDetaching([$profileId]);
            }
        }

        if ($currentUser && !$currentUser->hasRole('system-administrator')) {
            $currentUser->roles()->syncWithoutDetaching([$systemAdministrator->id]);
        }
    }

    public function v252()
    {
        return view('updates.v2_5_2');
    }

    public function applyV252(Request $request)
    {
        return redirect()->route('updates.v2_5_2')->with('success', 'Version 2.5.2 visual refresh applied. Icons, navigation styling, dashboard cards, buttons, tables and mobile layout updates are active.');
    }

    public function v253()
    {
        $coreSettingsInstalled = Schema::hasTable('system_settings');
        $permissionCount = Permission::whereIn('slug', ['core_settings.view', 'core_settings.manage'])->count();
        $systemAdministratorHasPermissions = false;

        if ($systemAdministrator = Role::where('slug', 'system-administrator')->first()) {
            $systemAdministratorHasPermissions = $systemAdministrator->permissions()
                ->whereIn('slug', ['core_settings.view', 'core_settings.manage'])
                ->count() === 2;
        }

        return view('updates.v2_5_3', compact('coreSettingsInstalled', 'permissionCount', 'systemAdministratorHasPermissions'));
    }

    public function applyV253(Request $request)
    {
        $this->addCoreSettingsTable();
        $this->seedCoreSettingsPermissions();
        $this->seedCoreSettingsDefaults();

        return redirect()->route('updates.v2_5_3')->with('success', 'Version 2.5.3 update applied. Core Settings are now available under System Settings for System Administrators only.');
    }

    private function addCoreSettingsTable(): void
    {
        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('group')->default('Core')->index();
                $table->string('label');
                $table->text('value')->nullable();
                $table->string('type')->default('text');
                $table->text('options')->nullable();
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(10);
                $table->boolean('is_core')->default(true);
                $table->timestamps();
            });
        }
    }

    private function seedCoreSettingsPermissions(): void
    {
        $permissions = [
            ['name' => 'View Core Settings', 'slug' => 'core_settings.view', 'module' => 'System Settings', 'description' => 'View protected platform-level settings. System Administrator only.'],
            ['name' => 'Manage Core Settings', 'slug' => 'core_settings.manage', 'module' => 'System Settings', 'description' => 'Edit protected platform-level settings. System Administrator only.'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $ids = Permission::whereIn('slug', array_column($permissions, 'slug'))->pluck('id')->all();

        $systemAdministrator = Role::firstOrCreate(
            ['slug' => 'system-administrator'],
            ['name' => 'System Administrator', 'description' => 'Top-level platform owner with unrestricted access.', 'level' => 110, 'is_system' => true]
        );
        $systemAdministrator->permissions()->syncWithoutDetaching($ids);

        foreach (Role::whereIn('slug', ['director', 'manager', 'employee'])->get() as $role) {
            $role->permissions()->detach($ids);
        }
    }

    private function seedCoreSettingsDefaults(): void
    {
        if (!Schema::hasTable('system_settings')) {
            return;
        }

        $defaults = [
            ['group' => 'Identity', 'key' => 'platform_name', 'label' => 'Platform Name', 'value' => 'ISO Admin', 'type' => 'text', 'description' => 'Display name used for the internal command platform.', 'sort_order' => 10],
            ['group' => 'Identity', 'key' => 'company_name', 'label' => 'Company Name', 'value' => 'ISO-Reliability Partners', 'type' => 'text', 'description' => 'Main company name shown in system references.', 'sort_order' => 20],
            ['group' => 'Identity', 'key' => 'platform_domain', 'label' => 'Platform Domain', 'value' => 'https://isoadmin.co.za', 'type' => 'url', 'description' => 'Primary platform URL.', 'sort_order' => 30],
            ['group' => 'Identity', 'key' => 'timezone', 'label' => 'Default Timezone', 'value' => 'Africa/Johannesburg', 'type' => 'text', 'description' => 'Timezone used for reminders, imports and operational dates.', 'sort_order' => 40],

            ['group' => 'Notifications', 'key' => 'default_notification_email', 'label' => 'Default Notification Email', 'value' => 'cc@isoadmin.co.za', 'type' => 'email', 'description' => 'Default recipient for system reminders where no module-specific email is configured.', 'sort_order' => 10],
            ['group' => 'Notifications', 'key' => 'support_email', 'label' => 'Support Email', 'value' => 'cc@isoadmin.co.za', 'type' => 'email', 'description' => 'Internal support/admin mailbox for platform issues.', 'sort_order' => 20],

            ['group' => 'Attendance', 'key' => 'attendance_import_email', 'label' => 'Attendance Import Email', 'value' => 'cc@isoadmin.co.za', 'type' => 'email', 'description' => 'Mailbox expected to receive attendance CSV exports.', 'sort_order' => 10],
            ['group' => 'Attendance', 'key' => 'delete_processed_attendance_email', 'label' => 'Delete Processed Attendance Emails', 'value' => '1', 'type' => 'boolean', 'description' => 'Deletes processed source emails after successful attendance import.', 'sort_order' => 20],

            ['group' => 'Documents', 'key' => 'default_document_reminder_days', 'label' => 'Default Employee Document Reminder Days', 'value' => '30', 'type' => 'integer', 'description' => 'Default advance warning period for employee documents with expiry dates.', 'sort_order' => 10],
            ['group' => 'Documents', 'key' => 'document_reminder_email', 'label' => 'Document Reminder Email', 'value' => 'cc@isoadmin.co.za', 'type' => 'email', 'description' => 'Recipient for employee document expiry summaries.', 'sort_order' => 20],

            ['group' => 'Vehicles', 'key' => 'default_vehicle_document_reminder_days', 'label' => 'Default Vehicle Document Reminder Days', 'value' => '30', 'type' => 'integer', 'description' => 'Default advance warning for NATIS, license disk and other vehicle documents.', 'sort_order' => 10],
            ['group' => 'Vehicles', 'key' => 'default_vehicle_service_reminder_km', 'label' => 'Default Vehicle Service Reminder KM', 'value' => '1000', 'type' => 'integer', 'description' => 'Default distance before service due where the vehicle reminder should appear.', 'sort_order' => 20],

            ['group' => 'Security', 'key' => 'force_first_login_password_change', 'label' => 'Force First Login Password Change', 'value' => '1', 'type' => 'boolean', 'description' => 'Keeps the first-login password-change workflow enabled for new users.', 'sort_order' => 10],
            ['group' => 'Security', 'key' => 'allow_user_dashboard_customisation', 'label' => 'Allow Dashboard Customisation', 'value' => '1', 'type' => 'boolean', 'description' => 'Allows users with permission to arrange and resize dashboard widgets.', 'sort_order' => 20],
        ];

        foreach ($defaults as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['is_core' => true, 'updated_at' => now(), 'created_at' => now()])
            );
        }
    }

    private function seedV25LeaveTypes(): void
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
            DB::table('leave_types')->updateOrInsert(
                ['code' => $leaveType['code']],
                array_merge($leaveType, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }


    public function v26()
    {
        $vehicleTrackingInstalled = Schema::hasTable('vehicle_tracking_snapshots');
        $vehicleColumnsInstalled = Schema::hasTable('vehicles')
            && Schema::hasColumn('vehicles', 'cartrack_vehicle_id')
            && Schema::hasColumn('vehicles', 'tracking_last_sync_at');
        $permissionCount = Permission::whereIn('slug', [
            'vehicle_tracking.view',
            'vehicle_tracking.sync',
            'vehicle_tracking.link',
            'vehicle_tracking.settings.view',
            'vehicle_tracking.settings.manage',
        ])->count();

        return view('updates.v2_6', compact('vehicleTrackingInstalled', 'vehicleColumnsInstalled', 'permissionCount'));
    }

    public function applyV26(Request $request)
    {
        $this->addVehicleTrackingTablesAndColumns();
        $this->seedVehicleTrackingPermissions();
        $this->seedVehicleTrackingSettings();

        return redirect()->route('updates.v2_6')->with('success', 'Version 2.6 update applied. Cartrack vehicle tracking integration, settings, sync routes and tracking permissions are now available.');
    }

    private function addVehicleTrackingTablesAndColumns(): void
    {
        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicles', 'tracking_provider')) {
                    $table->string('tracking_provider')->nullable()->after('tracking_notes')->index();
                }
                if (!Schema::hasColumn('vehicles', 'cartrack_vehicle_id')) {
                    $table->string('cartrack_vehicle_id')->nullable()->after('tracking_provider')->index();
                }
                if (!Schema::hasColumn('vehicles', 'cartrack_registration')) {
                    $table->string('cartrack_registration')->nullable()->after('cartrack_vehicle_id')->index();
                }
                if (!Schema::hasColumn('vehicles', 'cartrack_external_key')) {
                    $table->string('cartrack_external_key')->nullable()->after('cartrack_registration');
                }
                if (!Schema::hasColumn('vehicles', 'tracking_last_sync_at')) {
                    $table->dateTime('tracking_last_sync_at')->nullable()->after('cartrack_external_key')->index();
                }
                if (!Schema::hasColumn('vehicles', 'tracking_last_status')) {
                    $table->string('tracking_last_status')->nullable()->after('tracking_last_sync_at')->index();
                }
                if (!Schema::hasColumn('vehicles', 'tracking_last_latitude')) {
                    $table->decimal('tracking_last_latitude', 10, 7)->nullable()->after('tracking_last_status');
                }
                if (!Schema::hasColumn('vehicles', 'tracking_last_longitude')) {
                    $table->decimal('tracking_last_longitude', 10, 7)->nullable()->after('tracking_last_latitude');
                }
                if (!Schema::hasColumn('vehicles', 'tracking_last_address')) {
                    $table->string('tracking_last_address', 700)->nullable()->after('tracking_last_longitude');
                }
                if (!Schema::hasColumn('vehicles', 'tracking_last_speed')) {
                    $table->decimal('tracking_last_speed', 8, 2)->nullable()->after('tracking_last_address');
                }
                if (!Schema::hasColumn('vehicles', 'tracking_last_ignition')) {
                    $table->boolean('tracking_last_ignition')->nullable()->after('tracking_last_speed');
                }
                if (!Schema::hasColumn('vehicles', 'tracking_last_odometer')) {
                    $table->unsignedInteger('tracking_last_odometer')->nullable()->after('tracking_last_ignition')->index();
                }
                if (!Schema::hasColumn('vehicles', 'tracking_raw_payload')) {
                    $table->longText('tracking_raw_payload')->nullable()->after('tracking_last_odometer');
                }
            });
        }

        if (!Schema::hasTable('vehicle_tracking_snapshots')) {
            Schema::create('vehicle_tracking_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
                $table->string('provider')->default('cartrack')->index();
                $table->string('provider_vehicle_id')->nullable()->index();
                $table->string('registration_number')->nullable()->index();
                $table->dateTime('recorded_at')->nullable()->index();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('speed', 8, 2)->nullable();
                $table->unsignedInteger('odometer')->nullable()->index();
                $table->boolean('ignition')->nullable();
                $table->string('status')->nullable()->index();
                $table->string('address', 700)->nullable();
                $table->longText('raw_payload')->nullable();
                $table->timestamps();
                $table->index(['vehicle_id', 'recorded_at']);
            });
        }
    }

    private function seedVehicleTrackingPermissions(): void
    {
        $permissions = [
            ['name' => 'View Vehicle Tracking', 'slug' => 'vehicle_tracking.view', 'module' => 'Vehicle Tracking', 'description' => 'View latest vehicle tracking status and synced Cartrack data.'],
            ['name' => 'Sync Vehicle Tracking', 'slug' => 'vehicle_tracking.sync', 'module' => 'Vehicle Tracking', 'description' => 'Run a manual vehicle tracking API sync.'],
            ['name' => 'Link Vehicle Tracking IDs', 'slug' => 'vehicle_tracking.link', 'module' => 'Vehicle Tracking', 'description' => 'Link local vehicles to Cartrack vehicle identifiers.'],
            ['name' => 'View Vehicle Tracking Settings', 'slug' => 'vehicle_tracking.settings.view', 'module' => 'System Settings', 'description' => 'View Cartrack integration settings. System Administrator only.'],
            ['name' => 'Manage Vehicle Tracking Settings', 'slug' => 'vehicle_tracking.settings.manage', 'module' => 'System Settings', 'description' => 'Edit Cartrack integration settings and API credentials. System Administrator only.'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $operationalIds = Permission::whereIn('slug', [
            'vehicle_tracking.view',
            'vehicle_tracking.sync',
            'vehicle_tracking.link',
        ])->pluck('id')->all();

        foreach (Role::whereIn('slug', ['director', 'manager'])->get() as $role) {
            $role->permissions()->syncWithoutDetaching($operationalIds);
        }

        $systemAdministrator = Role::firstOrCreate(
            ['slug' => 'system-administrator'],
            ['name' => 'System Administrator', 'description' => 'Top-level platform owner with unrestricted access.', 'level' => 110, 'is_system' => true]
        );
        $systemAdministrator->permissions()->syncWithoutDetaching(Permission::pluck('id')->all());

        $settingsIds = Permission::whereIn('slug', [
            'vehicle_tracking.settings.view',
            'vehicle_tracking.settings.manage',
        ])->pluck('id')->all();

        foreach (Role::whereIn('slug', ['director', 'manager', 'employee'])->get() as $role) {
            $role->permissions()->detach($settingsIds);
        }
    }

    private function seedVehicleTrackingSettings(): void
    {
        if (!Schema::hasTable('system_settings')) {
            $this->addCoreSettingsTable();
        }

        $settings = [
            ['group' => 'Vehicle Tracking API', 'key' => 'cartrack_enabled', 'label' => 'Enable Cartrack Integration', 'value' => '0', 'type' => 'boolean', 'description' => 'Enables API-based vehicle tracking sync from Cartrack.', 'sort_order' => 10],
            ['group' => 'Vehicle Tracking API', 'key' => 'cartrack_region', 'label' => 'Cartrack Region Code', 'value' => 'za', 'type' => 'text', 'description' => 'South Africa uses za. The API base URL must match the Cartrack account region.', 'sort_order' => 20],
            ['group' => 'Vehicle Tracking API', 'key' => 'cartrack_base_url', 'label' => 'Cartrack API Base URL', 'value' => 'https://fleetapi-za.cartrack.com', 'type' => 'url', 'description' => 'Example for South Africa: https://fleetapi-za.cartrack.com. Do not include /rest/vehicles.', 'sort_order' => 30],
            ['group' => 'Vehicle Tracking API', 'key' => 'cartrack_username', 'label' => 'Cartrack API Username', 'value' => null, 'type' => 'text', 'description' => 'Fleetweb/API username supplied by Cartrack.', 'sort_order' => 40],
            ['group' => 'Vehicle Tracking API', 'key' => 'cartrack_password', 'label' => 'Cartrack API Password', 'value' => null, 'type' => 'password', 'description' => 'Generated API password from Fleetweb API Settings. Stored server-side only.', 'sort_order' => 50],
            ['group' => 'Vehicle Tracking API', 'key' => 'cartrack_timeout_seconds', 'label' => 'API Timeout Seconds', 'value' => '20', 'type' => 'integer', 'description' => 'Timeout for API requests. Keep low on shared hosting.', 'sort_order' => 60],
            ['group' => 'Vehicle Tracking API', 'key' => 'cartrack_sync_odometer', 'label' => 'Sync Odometer From Tracking', 'value' => '1', 'type' => 'boolean', 'description' => 'Updates vehicle ODO when Cartrack reports a higher odometer reading.', 'sort_order' => 70],
            ['group' => 'Vehicle Tracking API', 'key' => 'cartrack_sync_location', 'label' => 'Sync Location Data', 'value' => '1', 'type' => 'boolean', 'description' => 'Stores latest latitude, longitude, speed, address and ignition where available.', 'sort_order' => 80],
            ['group' => 'Vehicle Tracking API', 'key' => 'cartrack_sync_status', 'label' => 'Sync Vehicle Status', 'value' => '1', 'type' => 'boolean', 'description' => 'Stores the latest Cartrack status where available.', 'sort_order' => 90],
            ['group' => 'Vehicle Tracking API', 'key' => 'cartrack_cron_key', 'label' => 'Vehicle Tracking Cron Key', 'value' => Str::random(48), 'type' => 'text', 'description' => 'Secret key used by the shared-hosting cron URL for Cartrack sync.', 'sort_order' => 100],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['is_core' => true, 'updated_at' => now(), 'created_at' => now()])
            );
        }
    }


    public function v261()
    {
        $leaveInstalled = Schema::hasTable('leave_requests');
        $leaveTypesInstalled = Schema::hasTable('leave_types');
        $permissionCount = Permission::whereIn('slug', [
            'calendar.view',
            'leave.view',
            'leave.create',
            'leave.manage',
        ])->count();

        $systemAdministratorHasAllPermissions = false;
        if ($systemAdministrator = Role::where('slug', 'system-administrator')->first()) {
            $systemAdministratorHasAllPermissions = $systemAdministrator->permissions()->count() === Permission::count();
        }

        return view('updates.v2_6_1', compact('leaveInstalled', 'leaveTypesInstalled', 'permissionCount', 'systemAdministratorHasAllPermissions'));
    }

    public function applyV261(Request $request)
    {
        $this->addV261LeaveTables();
        $this->seedV25LeaveTypes();
        $this->seedV261Permissions();
        $this->seedSystemAdministratorAllPermissions($request->user());
        $this->seedV261Settings();

        return redirect()->route('updates.v2_6_1')->with('success', 'Version 2.6.1 update applied. Calendar and Leave have been restored, the footer version is visible, and System Administrator now has all permissions.');
    }

    private function addV261LeaveTables(): void
    {
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

        if (!Schema::hasTable('leave_requests')) {
            Schema::create('leave_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->nullable()->constrained('leave_types')->nullOnDelete();
                $table->date('start_date')->index();
                $table->date('end_date')->index();
                $table->decimal('total_days', 8, 2)->default(1);
                $table->string('status')->default('pending')->index();
                $table->boolean('is_deductible')->default(true)->index();
                $table->text('reason')->nullable();
                $table->text('manager_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'start_date', 'end_date']);
            });
        }
    }

    private function seedV261Permissions(): void
    {
        $permissions = [
            ['name' => 'View Calendar', 'slug' => 'calendar.view', 'module' => 'Calendar', 'description' => 'View the company leave calendar.'],
            ['name' => 'View Leave', 'slug' => 'leave.view', 'module' => 'Leave', 'description' => 'View leave requests. Employees see own leave; managers/directors see all.'],
            ['name' => 'Request Leave', 'slug' => 'leave.create', 'module' => 'Leave', 'description' => 'Submit leave requests. Managers can capture leave on behalf of employees.'],
            ['name' => 'Manage Leave', 'slug' => 'leave.manage', 'module' => 'Leave', 'description' => 'Approve, decline, cancel and manage leave requests.'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $employeeIds = Permission::whereIn('slug', ['calendar.view', 'leave.view', 'leave.create'])->pluck('id')->all();
        $managerIds = Permission::whereIn('slug', ['calendar.view', 'leave.view', 'leave.create', 'leave.manage', 'leave_types.view'])->pluck('id')->all();

        foreach (Role::whereIn('slug', ['employee'])->get() as $role) {
            $role->permissions()->syncWithoutDetaching($employeeIds);
        }

        foreach (Role::whereIn('slug', ['manager', 'director'])->get() as $role) {
            $role->permissions()->syncWithoutDetaching($managerIds);
        }
    }

    private function seedSystemAdministratorAllPermissions(?User $currentUser = null): void
    {
        $systemAdministrator = Role::firstOrCreate(
            ['slug' => 'system-administrator'],
            ['name' => 'System Administrator', 'description' => 'Top-level platform owner with unrestricted access.', 'level' => 110, 'is_system' => true]
        );

        $systemAdministrator->permissions()->sync(Permission::pluck('id')->all());

        if ($currentUser && !$currentUser->hasRole('system-administrator')) {
            $currentUser->roles()->syncWithoutDetaching([$systemAdministrator->id]);
        }
    }

    private function seedV261Settings(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'platform_version'],
                [
                    'group' => 'Identity',
                    'label' => 'Platform Version',
                    'value' => '2.6.1',
                    'type' => 'text',
                    'description' => 'Current ISO Admin Command Framework package version.',
                    'sort_order' => 5,
                    'is_core' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }



    public function v262()
    {
        $publicHolidaysInstalled = Schema::hasTable('public_holidays');
        $attendanceColumnsInstalled = Schema::hasTable('attendance_days')
            && Schema::hasColumn('attendance_days', 'is_late')
            && Schema::hasColumn('attendance_days', 'is_public_holiday');
        $publicHolidayCount = $publicHolidaysInstalled ? DB::table('public_holidays')->count() : 0;
        $lateRecordCount = $attendanceColumnsInstalled ? DB::table('attendance_days')->where('is_late', true)->count() : 0;

        return view('updates.v2_6_2', compact('publicHolidaysInstalled', 'attendanceColumnsInstalled', 'publicHolidayCount', 'lateRecordCount'));
    }

    public function applyV262(Request $request)
    {
        $this->addV262AttendanceColumns();
        $this->addV262PublicHolidayTable();
        $this->seedV262PublicHolidays();
        $this->seedV262AttendanceSettings();
        $this->seedV262Permissions();
        $this->seedV262Version();
        $rebuilt = 0;

        if (Schema::hasTable('attendance_raw_records')) {
            $rebuilt = app(\App\Services\AttendanceCsvImporter::class)->rebuildAllExistingDays();
        }

        $this->seedSystemAdministratorAllPermissions($request->user());

        return redirect()->route('updates.v2_6_2')->with('success', 'Version 2.6.2 update applied. Attendance clock-in rules, late tracking and public holidays are active. Existing attendance days rebuilt: ' . $rebuilt . '.');
    }

    private function addV262AttendanceColumns(): void
    {
        if (!Schema::hasTable('attendance_days')) {
            return;
        }

        if (!Schema::hasColumn('attendance_days', 'is_late')) {
            Schema::table('attendance_days', function (Blueprint $table) {
                $table->boolean('is_late')->default(false)->after('work_minutes')->index();
            });
        }
        if (!Schema::hasColumn('attendance_days', 'late_minutes')) {
            Schema::table('attendance_days', function (Blueprint $table) {
                $table->unsignedInteger('late_minutes')->default(0)->after('is_late');
            });
        }
        if (!Schema::hasColumn('attendance_days', 'is_public_holiday')) {
            Schema::table('attendance_days', function (Blueprint $table) {
                $table->boolean('is_public_holiday')->default(false)->after('late_minutes')->index();
            });
        }
        if (!Schema::hasColumn('attendance_days', 'public_holiday_name')) {
            Schema::table('attendance_days', function (Blueprint $table) {
                $table->string('public_holiday_name')->nullable()->after('is_public_holiday');
            });
        }
    }

    private function addV262PublicHolidayTable(): void
    {
        if (!Schema::hasTable('public_holidays')) {
            Schema::create('public_holidays', function (Blueprint $table) {
                $table->id();
                $table->date('holiday_date')->unique();
                $table->string('name');
                $table->string('country_code', 2)->default('ZA')->index();
                $table->boolean('is_company_closed')->default(true)->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });

            return;
        }

        // Some older builds already created public_holidays with a different schema.
        // The updater must be additive/idempotent so a partially failed v2.6.2 update can be rerun safely.
        if (!Schema::hasColumn('public_holidays', 'holiday_date')) {
            Schema::table('public_holidays', function (Blueprint $table) {
                $table->date('holiday_date')->nullable()->index();
            });
        }

        if (!Schema::hasColumn('public_holidays', 'name')) {
            Schema::table('public_holidays', function (Blueprint $table) {
                $table->string('name')->nullable();
            });
        }

        if (!Schema::hasColumn('public_holidays', 'country_code')) {
            Schema::table('public_holidays', function (Blueprint $table) {
                $table->string('country_code', 2)->default('ZA')->index();
            });
        }

        if (!Schema::hasColumn('public_holidays', 'is_company_closed')) {
            Schema::table('public_holidays', function (Blueprint $table) {
                $table->boolean('is_company_closed')->default(true)->index();
            });
        }

        if (!Schema::hasColumn('public_holidays', 'notes')) {
            Schema::table('public_holidays', function (Blueprint $table) {
                $table->text('notes')->nullable();
            });
        }

        if (!Schema::hasColumn('public_holidays', 'created_at')) {
            Schema::table('public_holidays', function (Blueprint $table) {
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasColumn('public_holidays', 'updated_at')) {
            Schema::table('public_holidays', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    private function seedV262PublicHolidays(): void
    {
        if (!Schema::hasTable('public_holidays')) {
            return;
        }

        $holidays = [
            ['2026-01-01', 'New Year’s Day', 'Official South African public holiday.'],
            ['2026-03-21', 'Human Rights Day', 'Official South African public holiday.'],
            ['2026-04-03', 'Good Friday', 'Official South African public holiday.'],
            ['2026-04-06', 'Family Day', 'Official South African public holiday.'],
            ['2026-04-27', 'Freedom Day', 'Official South African public holiday.'],
            ['2026-05-01', 'Workers’ Day', 'Official South African public holiday.'],
            ['2026-06-16', 'Youth Day', 'Official South African public holiday.'],
            ['2026-08-09', 'National Women’s Day', 'Official South African public holiday.'],
            ['2026-08-10', 'National Women’s Day observed', 'Observed public holiday because National Women’s Day falls on a Sunday.'],
            ['2026-09-24', 'Heritage Day', 'Official South African public holiday.'],
            ['2026-12-16', 'Day of Reconciliation', 'Official South African public holiday.'],
            ['2026-12-25', 'Christmas Day', 'Official South African public holiday.'],
            ['2026-12-26', 'Day of Goodwill', 'Official South African public holiday.'],
            ['2027-01-01', 'New Year’s Day', 'Official South African public holiday.'],
            ['2027-03-21', 'Human Rights Day', 'Official South African public holiday.'],
            ['2027-03-22', 'Human Rights Day observed', 'Observed public holiday because Human Rights Day falls on a Sunday.'],
            ['2027-03-26', 'Good Friday', 'Official South African public holiday.'],
            ['2027-03-29', 'Family Day', 'Official South African public holiday.'],
            ['2027-04-27', 'Freedom Day', 'Official South African public holiday.'],
            ['2027-05-01', 'Workers’ Day', 'Official South African public holiday.'],
            ['2027-06-16', 'Youth Day', 'Official South African public holiday.'],
            ['2027-08-09', 'National Women’s Day', 'Official South African public holiday.'],
            ['2027-09-24', 'Heritage Day', 'Official South African public holiday.'],
            ['2027-12-16', 'Day of Reconciliation', 'Official South African public holiday.'],
            ['2027-12-25', 'Christmas Day', 'Official South African public holiday.'],
            ['2027-12-26', 'Day of Goodwill', 'Official South African public holiday.'],
        ];

        foreach ($holidays as [$date, $name, $notes]) {
            DB::table('public_holidays')->updateOrInsert(
                ['holiday_date' => $date],
                [
                    'name' => $name,
                    'country_code' => 'ZA',
                    'is_company_closed' => true,
                    'notes' => $notes,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function seedV262AttendanceSettings(): void
    {
        if (!Schema::hasTable('system_settings')) {
            return;
        }

        $settings = [
            ['group' => 'Attendance', 'key' => 'attendance_clock_in_cutoff', 'label' => 'Attendance Clock-in Cut-off', 'value' => '09:00', 'type' => 'time', 'description' => 'Clock-in records after this time are marked late. Current importer rule uses 09:00.', 'sort_order' => 30],
            ['group' => 'Attendance', 'key' => 'attendance_ignore_public_holidays', 'label' => 'Exclude Public Holidays From Attendance', 'value' => '1', 'type' => 'boolean', 'description' => 'Company is closed on public holidays, so attendance records on these dates are kept for audit but excluded from normal late tracking.', 'sort_order' => 40],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['is_core' => true, 'updated_at' => now(), 'created_at' => now()])
            );
        }
    }

    private function seedV262Permissions(): void
    {
        $permissions = [
            ['name' => 'View Late Attendance', 'slug' => 'attendance.late.view', 'module' => 'Attendance', 'description' => 'View late clock-in summaries across employees and employee profiles.'],
            ['name' => 'View Public Holidays', 'slug' => 'public_holidays.view', 'module' => 'Calendar', 'description' => 'View company public holidays on the calendar and attendance exclusions.'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $managerIds = Permission::whereIn('slug', ['attendance.late.view', 'public_holidays.view'])->pluck('id')->all();
        foreach (Role::whereIn('slug', ['director', 'manager'])->get() as $role) {
            $role->permissions()->syncWithoutDetaching($managerIds);
        }

        if ($employee = Role::where('slug', 'employee')->first()) {
            $holidayId = Permission::where('slug', 'public_holidays.view')->value('id');
            if ($holidayId) {
                $employee->permissions()->syncWithoutDetaching([$holidayId]);
            }
        }
    }

    public function v263()
    {
        $publicHolidaysInstalled = Schema::hasTable('public_holidays');
        $publicHolidayNameColumn = $publicHolidaysInstalled && Schema::hasColumn('public_holidays', 'name');
        $publicHolidayCount = $publicHolidaysInstalled ? DB::table('public_holidays')->count() : 0;
        $attendanceColumnsInstalled = Schema::hasTable('attendance_days')
            && Schema::hasColumn('attendance_days', 'is_late')
            && Schema::hasColumn('attendance_days', 'is_public_holiday');

        return view('updates.v2_6_3', compact('publicHolidaysInstalled', 'publicHolidayNameColumn', 'publicHolidayCount', 'attendanceColumnsInstalled'));
    }

    public function applyV263(Request $request)
    {
        $this->addV262AttendanceColumns();
        $this->addV262PublicHolidayTable();
        $this->seedV262PublicHolidays();
        $this->seedV262AttendanceSettings();
        $this->seedV262Permissions();
        $this->seedV263Version();
        $rebuilt = 0;

        if (Schema::hasTable('attendance_raw_records')) {
            $rebuilt = app(\App\Services\AttendanceCsvImporter::class)->rebuildAllExistingDays();
        }

        $this->seedSystemAdministratorAllPermissions($request->user());

        return redirect()->route('updates.v2_6_3')->with('success', 'Version 2.6.3 repair applied. Public holiday schema repaired, holidays reseeded and attendance days rebuilt: ' . $rebuilt . '.');
    }


    public function v264()
    {
        $calendarReminderPermission = Permission::where('slug', 'calendar.reminders.view')->exists();
        $systemVersion = Schema::hasTable('system_settings')
            ? DB::table('system_settings')->where('key', 'platform_version')->value('value')
            : null;

        return view('updates.v2_6_4', compact('calendarReminderPermission', 'systemVersion'));
    }

    public function applyV264(Request $request)
    {
        $this->seedV264Permissions();
        $this->seedV264Version();
        $this->seedSystemAdministratorAllPermissions($request->user());

        return redirect()->route('updates.v2_6_4')->with('success', 'Version 2.6.4 applied. Calendar now starts on Sunday and acts as a central reminder calendar for leave, public holidays, attendance exceptions, employee documents, vehicle documents, vehicle services and tracking sync reminders.');
    }

    private function seedV264Permissions(): void
    {
        $permission = Permission::updateOrCreate(
            ['slug' => 'calendar.reminders.view'],
            [
                'name' => 'View Calendar Reminder Centre',
                'module' => 'Calendar',
                'description' => 'View the central reminder calendar combining leave, public holidays, attendance, documents, vehicle service and tracking reminders.',
            ]
        );

        foreach (Role::whereIn('slug', ['director', 'manager'])->get() as $role) {
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    private function seedV264Version(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'platform_version'],
                [
                    'group' => 'Identity',
                    'label' => 'Platform Version',
                    'value' => '2.6.4',
                    'type' => 'text',
                    'description' => 'Current ISO Admin Command Framework package version.',
                    'sort_order' => 5,
                    'is_core' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function seedV263Version(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'platform_version'],
                [
                    'group' => 'Identity',
                    'label' => 'Platform Version',
                    'value' => '2.6.3',
                    'type' => 'text',
                    'description' => 'Current ISO Admin Command Framework package version.',
                    'sort_order' => 5,
                    'is_core' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function seedV262Version(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'platform_version'],
                [
                    'group' => 'Identity',
                    'label' => 'Platform Version',
                    'value' => '2.6.2',
                    'type' => 'text',
                    'description' => 'Current ISO Admin Command Framework package version.',
                    'sort_order' => 5,
                    'is_core' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }


    public function v265()
    {
        $systemVersion = Schema::hasTable('system_settings')
            ? DB::table('system_settings')->where('key', 'platform_version')->value('value')
            : null;

        return view('updates.v2_6_5', compact('systemVersion'));
    }

    public function applyV265(Request $request)
    {
        $this->seedV265TrackingDiagnostics();
        $this->seedV265Version();
        $this->seedSystemAdministratorAllPermissions($request->user());

        return redirect()->route('updates.v2_6_5')->with('success', 'Version 2.6.5 applied. Calendar type filters are available and the Cartrack sync now checks /rest/vehicles/status before falling back to /rest/vehicles, with better diagnostics and vehicle matching.');
    }

    private function seedV265TrackingDiagnostics(): void
    {
        if (!Schema::hasTable('system_settings')) {
            return;
        }

        $settings = [
            ['key' => 'cartrack_last_sync_at', 'label' => 'Cartrack Last Sync At', 'value' => null, 'description' => 'Last time a Cartrack sync completed or attempted.'],
            ['key' => 'cartrack_last_sync_message', 'label' => 'Cartrack Last Sync Message', 'value' => null, 'description' => 'Latest Cartrack sync diagnostic message.'],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'group' => 'Vehicle Tracking',
                    'label' => $setting['label'],
                    'value' => $setting['value'],
                    'type' => 'text',
                    'description' => $setting['description'],
                    'sort_order' => 90,
                    'is_core' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function seedV265Version(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'platform_version'],
                [
                    'group' => 'Identity',
                    'label' => 'Platform Version',
                    'value' => '2.6.5',
                    'type' => 'text',
                    'description' => 'Current ISO Admin Command Framework package version.',
                    'sort_order' => 5,
                    'is_core' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }



    public function v266()
    {
        $systemVersion = Schema::hasTable('system_settings')
            ? DB::table('system_settings')->where('key', 'platform_version')->value('value')
            : null;
        $googleApiSettingsInstalled = Schema::hasTable('system_settings')
            && DB::table('system_settings')->where('group', 'Google API')->exists();

        return view('updates.v2_6_6', compact('systemVersion', 'googleApiSettingsInstalled'));
    }

    public function applyV266(Request $request)
    {
        $this->seedV266GoogleApiSettings();
        $this->seedV266Permissions();
        $this->seedV266Version();
        $this->seedSystemAdministratorAllPermissions($request->user());

        return redirect()->route('updates.v2_6_6')->with('success', 'Version 2.6.6 applied. Google API settings are restored, fleet map plotting is available on the main vehicle page, and vehicle route/history maps are available on each vehicle profile.');
    }

    private function seedV266GoogleApiSettings(): void
    {
        if (!Schema::hasTable('system_settings')) {
            return;
        }

        $settings = [
            [
                'key' => 'google_maps_enabled',
                'label' => 'Enable Google Maps',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Enable Google Maps on authenticated vehicle tracking pages.',
                'sort_order' => 10,
            ],
            [
                'key' => 'google_maps_api_key',
                'label' => 'Google Maps API Key',
                'value' => null,
                'type' => 'text',
                'description' => 'Browser API key for the Maps JavaScript API. Restrict this key to isoadmin.co.za in Google Cloud.',
                'sort_order' => 20,
            ],
            [
                'key' => 'google_maps_map_id',
                'label' => 'Google Map ID',
                'value' => null,
                'type' => 'text',
                'description' => 'Optional Google Map ID. When supplied, Advanced Markers are used.',
                'sort_order' => 30,
            ],
            [
                'key' => 'google_maps_default_latitude',
                'label' => 'Default Map Latitude',
                'value' => '-26.204103',
                'type' => 'text',
                'description' => 'Default map centre latitude used when no vehicle coordinates are available.',
                'sort_order' => 40,
            ],
            [
                'key' => 'google_maps_default_longitude',
                'label' => 'Default Map Longitude',
                'value' => '28.047305',
                'type' => 'text',
                'description' => 'Default map centre longitude used when no vehicle coordinates are available.',
                'sort_order' => 50,
            ],
            [
                'key' => 'google_maps_default_zoom',
                'label' => 'Default Map Zoom',
                'value' => '7',
                'type' => 'integer',
                'description' => 'Default Google Maps zoom level.',
                'sort_order' => 60,
            ],
        ];

        foreach ($settings as $setting) {
            $payload = [
                'group' => 'Google API',
                'label' => $setting['label'],
                'type' => $setting['type'],
                'description' => $setting['description'],
                'sort_order' => $setting['sort_order'],
                'is_core' => true,
                'updated_at' => now(),
            ];

            if (DB::table('system_settings')->where('key', $setting['key'])->exists()) {
                DB::table('system_settings')->where('key', $setting['key'])->update($payload);
            } else {
                DB::table('system_settings')->insert(array_merge($payload, [
                    'key' => $setting['key'],
                    'value' => $setting['value'],
                    'created_at' => now(),
                ]));
            }
        }
    }

    private function seedV266Permissions(): void
    {
        $permissions = [
            [
                'slug' => 'google_api_settings.view',
                'name' => 'View Google API Settings',
                'module' => 'System Settings',
                'description' => 'View Google API settings for map integrations.',
            ],
            [
                'slug' => 'google_api_settings.manage',
                'name' => 'Manage Google API Settings',
                'module' => 'System Settings',
                'description' => 'Manage Google Maps API key and map defaults.',
            ],
        ];

        $permissionIds = [];
        foreach ($permissions as $permission) {
            $record = Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
            $permissionIds[] = $record->id;
        }

        if ($systemAdministrator = Role::where('slug', 'system-administrator')->first()) {
            $systemAdministrator->permissions()->syncWithoutDetaching($permissionIds);
        }

        Role::whereIn('slug', ['director', 'manager', 'employee'])->get()->each(function (Role $role) use ($permissionIds) {
            $role->permissions()->detach($permissionIds);
        });
    }

    private function seedV266Version(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'platform_version'],
                [
                    'group' => 'Identity',
                    'label' => 'Platform Version',
                    'value' => '2.6.6',
                    'type' => 'text',
                    'description' => 'Current ISO Admin Command Framework package version.',
                    'sort_order' => 5,
                    'is_core' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }



    public function v267()
    {
        $systemVersion = Schema::hasTable('system_settings')
            ? DB::table('system_settings')->where('key', 'platform_version')->value('value')
            : null;

        return view('updates.v2_6_7', compact('systemVersion'));
    }

    public function applyV267(Request $request)
    {
        $this->seedV267Version();
        $this->seedSystemAdministratorAllPermissions($request->user());

        return redirect()->route('updates.v2_6_7')->with('success', 'Version 2.6.7 applied. Vehicle map Blade JSON output has been repaired and the specific vehicle page should now open correctly.');
    }

    private function seedV267Version(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'platform_version'],
                [
                    'group' => 'Identity',
                    'label' => 'Platform Version',
                    'value' => '2.6.7',
                    'type' => 'text',
                    'description' => 'Current ISO Admin Command Framework package version.',
                    'sort_order' => 5,
                    'is_core' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }


    public function v268()
    {
        $systemVersion = Schema::hasTable('system_settings')
            ? DB::table('system_settings')->where('key', 'platform_version')->value('value')
            : null;

        return view('updates.v2_6_8', compact('systemVersion'));
    }

    public function applyV268(Request $request)
    {
        $this->seedV268Version();
        $this->seedSystemAdministratorAllPermissions($request->user());

        return redirect()->route('updates.v2_6_8')->with('success', 'Version 2.6.8 applied. Vehicle index and vehicle profile map Blade errors have been repaired.');
    }

    private function seedV268Version(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'platform_version'],
                [
                    'group' => 'Identity',
                    'label' => 'Platform Version',
                    'value' => '2.6.8',
                    'type' => 'text',
                    'description' => 'Current ISO Admin Command Framework package version.',
                    'sort_order' => 5,
                    'is_core' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }


    public function v269()
    {
        $systemVersion = Schema::hasTable('system_settings')
            ? DB::table('system_settings')->where('key', 'platform_version')->value('value')
            : null;
        $quickActionsReady = Schema::hasTable('quick_action_preferences');

        return view('updates.v2_6_9', compact('systemVersion', 'quickActionsReady'));
    }

    public function applyV269(Request $request)
    {
        $this->addV269QuickActionPreferencesTable();
        $this->seedV269Permissions();
        $this->seedV269Version();
        $this->seedSystemAdministratorAllPermissions($request->user());

        return redirect()->route('updates.v2_6_9')->with('success', 'Version 2.6.9 applied. Vehicle dashboard, fleet fuel tracking, editable Quick Actions and the Add Fuel vehicle selection flow are now available.');
    }

    private function addV269QuickActionPreferencesTable(): void
    {
        if (!Schema::hasTable('quick_action_preferences')) {
            Schema::create('quick_action_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('action_key');
                $table->unsignedInteger('sort_order')->default(10);
                $table->boolean('is_visible')->default(true);
                $table->timestamps();
                $table->unique(['user_id', 'action_key']);
                $table->index(['user_id', 'sort_order']);
            });
        }
    }

    private function seedV269Permissions(): void
    {
        $permission = Permission::updateOrCreate(
            ['slug' => 'dashboard.quick_actions.manage'],
            [
                'name' => 'Manage Dashboard Quick Actions',
                'module' => 'Dashboard',
                'description' => 'Edit the shortcuts shown inside the homepage Quick Actions widget.',
            ]
        );

        Role::whereIn('slug', ['system-administrator', 'director', 'manager'])->get()->each(function (Role $role) use ($permission) {
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        });
    }

    private function seedV269Version(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'platform_version'],
                [
                    'group' => 'Identity',
                    'label' => 'Platform Version',
                    'value' => '2.6.9',
                    'type' => 'text',
                    'description' => 'Current ISO Admin Command Framework package version.',
                    'sort_order' => 5,
                    'is_core' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }


    public function v2610()
    {
        $systemVersion = Schema::hasTable('system_settings')
            ? DB::table('system_settings')->where('key', 'platform_version')->value('value')
            : null;
        $customersReady = Schema::hasTable('customers');

        return view('updates.v2_6_10', compact('systemVersion', 'customersReady'));
    }

    public function applyV2610(Request $request)
    {
        $this->addV2610CustomersTable();
        $this->seedV2610CustomerPermissions();
        $this->seedV2610Version();
        $this->seedSystemAdministratorAllPermissions($request->user());

        return redirect()->route('updates.v2_6_10')->with('success', 'Version 2.6.10 applied. Customers are now available in the left menu and controlled by the customer permissions.');
    }

    private function addV2610CustomersTable(): void
    {
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('company_name');
                $table->string('customer_code')->nullable()->unique();
                $table->string('contact_person')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 80)->nullable();
                $table->text('address')->nullable();
                $table->string('status', 40)->default('active')->index();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->index('company_name');
            });
        } else {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'company_name')) {
                    $table->string('company_name')->nullable()->index();
                }
                if (!Schema::hasColumn('customers', 'customer_code')) {
                    $table->string('customer_code')->nullable()->unique();
                }
                if (!Schema::hasColumn('customers', 'contact_person')) {
                    $table->string('contact_person')->nullable();
                }
                if (!Schema::hasColumn('customers', 'email')) {
                    $table->string('email')->nullable();
                }
                if (!Schema::hasColumn('customers', 'phone')) {
                    $table->string('phone', 80)->nullable();
                }
                if (!Schema::hasColumn('customers', 'address')) {
                    $table->text('address')->nullable();
                }
                if (!Schema::hasColumn('customers', 'status')) {
                    $table->string('status', 40)->default('active')->index();
                }
                if (!Schema::hasColumn('customers', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (!Schema::hasColumn('customers', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('customers', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('customers', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
                if (!Schema::hasColumn('customers', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    private function seedV2610CustomerPermissions(): void
    {
        $permissions = [
            [
                'slug' => 'clients.view',
                'name' => 'View Customers',
                'module' => 'Customers',
                'description' => 'View the Customers menu and customer records.',
            ],
            [
                'slug' => 'clients.manage',
                'name' => 'Manage Customers',
                'module' => 'Customers',
                'description' => 'Create, edit and delete customer records.',
            ],
        ];

        $permissionIds = [];
        foreach ($permissions as $permissionData) {
            $permission = Permission::updateOrCreate(
                ['slug' => $permissionData['slug']],
                [
                    'name' => $permissionData['name'],
                    'module' => $permissionData['module'],
                    'description' => $permissionData['description'],
                ]
            );
            $permissionIds[] = $permission->id;
        }

        Role::whereIn('slug', ['system-administrator', 'director', 'manager'])->get()->each(function (Role $role) use ($permissionIds) {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        });
    }

    private function seedV2610Version(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'platform_version'],
                [
                    'group' => 'Identity',
                    'label' => 'Platform Version',
                    'value' => '2.6.10',
                    'type' => 'text',
                    'description' => 'Current ISO Admin Command Framework package version.',
                    'sort_order' => 5,
                    'is_core' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }


    public function v2611()
    {
        $systemVersion = Schema::hasTable('system_settings')
            ? DB::table('system_settings')->where('key', 'platform_version')->value('value')
            : null;
        $attendanceInstalled = Schema::hasTable('attendance_days') && Schema::hasTable('attendance_imports') && Schema::hasTable('attendance_raw_records');
        $latestAttendanceDate = $attendanceInstalled ? DB::table('attendance_days')->max('attendance_date') : null;
        $attendanceDayCount = $attendanceInstalled ? DB::table('attendance_days')->count() : 0;

        return view('updates.v2_6_11', compact('systemVersion', 'attendanceInstalled', 'latestAttendanceDate', 'attendanceDayCount'));
    }

    public function applyV2611(Request $request)
    {
        $this->addAttendanceTables();
        $this->addV262AttendanceColumns();
        $this->addV262PublicHolidayTable();
        $this->seedV262PublicHolidays();
        $this->seedAttendancePermissions();
        $this->seedManualAttendanceUploadPermission();
        $this->seedV262Permissions();
        $this->seedV2611Version();

        $rebuilt = 0;
        if (Schema::hasTable('attendance_raw_records')) {
            $rebuilt = app(\App\Services\AttendanceCsvImporter::class)->rebuildAllExistingDays();
        }

        $this->seedSystemAdministratorAllPermissions($request->user());

        return redirect()->route('updates.v2_6_11')->with('success', 'Version 2.6.11 applied. Attendance CSV import compatibility restored, attendance index defaults to the latest imported day, and existing raw records rebuilt: ' . $rebuilt . '.');
    }

    public function v290()
    {
        $systemVersion = Schema::hasTable('system_settings')
            ? DB::table('system_settings')->where('key', 'platform_version')->value('value')
            : null;
        $settingsSeeded = Schema::hasTable('system_settings')
            && DB::table('system_settings')->where('group', 'Update Manager')->exists();
        $permissionCount = Permission::whereIn('slug', [
            'platform_updates.view',
            'platform_updates.manage',
        ])->count();
        $zipAvailable = class_exists(\ZipArchive::class);

        return view('updates.v2_9_0', compact('systemVersion', 'settingsSeeded', 'permissionCount', 'zipAvailable'));
    }

    public function applyV290(Request $request)
    {
        $this->seedV290UpdateManagerSettings();
        $this->seedV290Permissions();
        $this->seedV290Version();
        $this->seedSystemAdministratorAllPermissions($request->user());

        return redirect()->route('updates.v2_9_0')->with('success', 'Version 2.9.0 applied. The Update Manager is now available under the Admin menu for System Administrators, supporting ZIP upload updates and GitHub branch updates with pre-apply code backups.');
    }

    private function seedV290UpdateManagerSettings(): void
    {
        if (!Schema::hasTable('system_settings')) {
            $this->addCoreSettingsTable();
        }

        $settings = [
            ['key' => 'update_github_repository', 'label' => 'GitHub Repository', 'value' => null, 'type' => 'text', 'description' => 'GitHub repository in owner/repository format used for web updates.', 'sort_order' => 10],
            ['key' => 'update_github_branch', 'label' => 'GitHub Branch', 'value' => 'main', 'type' => 'text', 'description' => 'Branch downloaded when updating from GitHub.', 'sort_order' => 20],
            ['key' => 'update_github_token', 'label' => 'GitHub Access Token', 'value' => null, 'type' => 'password', 'description' => 'Optional token used to download private repositories. Stored server-side only.', 'sort_order' => 30],
            ['key' => 'update_backup_before_apply', 'label' => 'Backup Before Apply', 'value' => '1', 'type' => 'boolean', 'description' => 'Creates a code backup ZIP before applying an update package.', 'sort_order' => 40],
        ];

        foreach ($settings as $setting) {
            $payload = [
                'group' => 'Update Manager',
                'label' => $setting['label'],
                'type' => $setting['type'],
                'description' => $setting['description'],
                'sort_order' => $setting['sort_order'],
                'is_core' => true,
                'updated_at' => now(),
            ];

            if (DB::table('system_settings')->where('key', $setting['key'])->exists()) {
                DB::table('system_settings')->where('key', $setting['key'])->update($payload);
            } else {
                DB::table('system_settings')->insert(array_merge($payload, [
                    'key' => $setting['key'],
                    'value' => $setting['value'],
                    'created_at' => now(),
                ]));
            }
        }
    }

    private function seedV290Permissions(): void
    {
        $permissions = [
            [
                'slug' => 'platform_updates.view',
                'name' => 'View Update Manager',
                'module' => 'System Settings',
                'description' => 'View the Update Manager, update packages and code backups. System Administrator only.',
            ],
            [
                'slug' => 'platform_updates.manage',
                'name' => 'Manage Platform Updates',
                'module' => 'System Settings',
                'description' => 'Upload, download and apply platform update packages. System Administrator only.',
            ],
        ];

        $permissionIds = [];
        foreach ($permissions as $permission) {
            $record = Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
            $permissionIds[] = $record->id;
        }

        if ($systemAdministrator = Role::where('slug', 'system-administrator')->first()) {
            $systemAdministrator->permissions()->syncWithoutDetaching($permissionIds);
        }

        Role::whereIn('slug', ['director', 'manager', 'employee'])->get()->each(function (Role $role) use ($permissionIds) {
            $role->permissions()->detach($permissionIds);
        });
    }

    private function seedV290Version(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'platform_version'],
                [
                    'group' => 'Identity',
                    'label' => 'Platform Version',
                    'value' => '2.9.0',
                    'type' => 'text',
                    'description' => 'Current ISO Admin Command Framework package version.',
                    'sort_order' => 5,
                    'is_core' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function seedV2611Version(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'platform_version'],
                [
                    'group' => 'Identity',
                    'label' => 'Platform Version',
                    'value' => '2.6.11',
                    'type' => 'text',
                    'description' => 'Current ISO Admin Command Framework package version.',
                    'sort_order' => 5,
                    'is_core' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function v286()
    {
        $systemVersion = Schema::hasTable('system_settings')
            ? DB::table('system_settings')->where('key', 'platform_version')->value('value')
            : null;
        $customerCrmReady = Schema::hasTable('customer_sites') && Schema::hasTable('customer_contacts') && Schema::hasTable('customer_interactions');

        return view('updates.v2_8_6', compact('systemVersion', 'customerCrmReady'));
    }

    public function applyV286(Request $request)
    {
        $this->addV286CustomerCrmTables();
        $this->seedV286Permissions();
        $this->removeV286OrphanedClientFiles();
        $this->seedV286Version();
        $this->seedSystemAdministratorAllPermissions($request->user());

        return redirect()->route('updates.v2_8_6')->with('success', 'Version 2.8.6 applied. Customers now have a full CRM: sites/locations, contacts and an interaction/activity log. The old, unused Clients screens were removed automatically.');
    }

    private function addV286CustomerCrmTables(): void
    {
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'customer_type')) {
                    $table->string('customer_type', 40)->default('customer')->after('customer_code')->index();
                }
                if (!Schema::hasColumn('customers', 'industry')) {
                    $table->string('industry')->nullable()->after('customer_type');
                }
                if (!Schema::hasColumn('customers', 'website')) {
                    $table->string('website')->nullable()->after('email');
                }
                if (!Schema::hasColumn('customers', 'account_manager_id')) {
                    $table->foreignId('account_manager_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
                }
            });
        }

        if (!Schema::hasTable('customer_sites')) {
            Schema::create('customer_sites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('name');
                $table->string('site_code', 100)->nullable();
                $table->string('status', 40)->default('active')->index();
                $table->text('location');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('customer_contacts')) {
            Schema::create('customer_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('customer_site_id')->nullable()->constrained('customer_sites')->nullOnDelete();
                $table->string('name');
                $table->string('position')->nullable();
                $table->string('contact_type', 100)->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 100)->nullable();
                $table->string('mobile', 100)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->string('status', 40)->default('active')->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('customer_interactions')) {
            Schema::create('customer_interactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('customer_site_id')->nullable()->constrained('customer_sites')->nullOnDelete();
                $table->foreignId('customer_contact_id')->nullable()->constrained('customer_contacts')->nullOnDelete();
                $table->string('type', 40)->default('note');
                $table->string('subject');
                $table->text('notes')->nullable();
                $table->dateTime('occurred_at');
                $table->dateTime('follow_up_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['customer_id', 'occurred_at']);
            });
        }
    }

    private function seedV286Permissions(): void
    {
        $permissions = [
            [
                'slug' => 'customer_sites.manage',
                'name' => 'Manage Customer Sites',
                'module' => 'Customers',
                'description' => 'Add, edit and delete customer sites/locations.',
            ],
            [
                'slug' => 'customer_contacts.manage',
                'name' => 'Manage Customer Contacts',
                'module' => 'Customers',
                'description' => 'Add, edit and delete customer and site contact people.',
            ],
            [
                'slug' => 'customer_interactions.manage',
                'name' => 'Manage Customer Interactions',
                'module' => 'Customers',
                'description' => 'Log, edit and delete customer interactions and follow-ups.',
            ],
        ];

        $permissionIds = [];
        foreach ($permissions as $permissionData) {
            $permission = Permission::updateOrCreate(
                ['slug' => $permissionData['slug']],
                [
                    'name' => $permissionData['name'],
                    'module' => $permissionData['module'],
                    'description' => $permissionData['description'],
                ]
            );
            $permissionIds[] = $permission->id;
        }

        Role::whereIn('slug', ['system-administrator', 'director', 'manager'])->get()->each(function (Role $role) use ($permissionIds) {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        });
    }

    private function removeV286OrphanedClientFiles(): void
    {
        $controllerPath = app_path('Http/Controllers/ClientController.php');
        if (File::exists($controllerPath)) {
            File::delete($controllerPath);
        }

        $viewsPath = resource_path('views/clients');
        if (File::isDirectory($viewsPath)) {
            File::deleteDirectory($viewsPath);
        }
    }

    private function seedV286Version(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'platform_version'],
                [
                    'group' => 'Identity',
                    'label' => 'Platform Version',
                    'value' => '2.8.6',
                    'type' => 'text',
                    'description' => 'Current ISO Admin Command Framework package version.',
                    'sort_order' => 5,
                    'is_core' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function v288()
    {
        $systemVersion = Schema::hasTable('system_settings')
            ? DB::table('system_settings')->where('key', 'platform_version')->value('value')
            : null;
        $permissionExists = Permission::where('slug', 'employee_compliance.view')->exists();

        return view('updates.v2_8_8', compact('systemVersion', 'permissionExists'));
    }

    public function applyV288(Request $request)
    {
        DB::transaction(function () {
            $this->seedEmployeeCompliancePermission();
            $this->seedV288Version();
        });

        $this->seedSystemAdministratorAllPermissions($request->user());

        return redirect()->route('updates.v2_8_8')->with('success', 'Version 2.8.8 applied. The Employee Compliance Overview page and dashboard widget are now available.');
    }

    private function seedEmployeeCompliancePermission(): void
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'employee_compliance.view'],
            [
                'name' => 'View Employee Compliance',
                'module' => 'Employee Documents',
                'description' => 'View the employee document compliance overview.',
            ]
        );

        if ($director = Role::where('slug', 'director')->first()) {
            $director->permissions()->syncWithoutDetaching([$permission->id]);
        }
        if ($manager = Role::where('slug', 'manager')->first()) {
            $manager->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    private function seedV288Version(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'platform_version'],
                [
                    'group' => 'Identity',
                    'label' => 'Platform Version',
                    'value' => '2.8.8',
                    'type' => 'text',
                    'description' => 'Current ISO Admin Command Framework package version.',
                    'sort_order' => 5,
                    'is_core' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

}
