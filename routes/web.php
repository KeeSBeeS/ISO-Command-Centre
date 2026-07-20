<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeComplianceController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\CronJobController;
use App\Http\Controllers\CoreSettingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\GoogleApiSettingController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\PlatformUpdateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleTrackingController;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Support\Facades\Route;

Route::get('/install', [InstallController::class, 'index'])->name('install.index');
Route::post('/install', [InstallController::class, 'install'])->name('install.run');

Route::middleware([EnsureInstalled::class])->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

    Route::get('/attendance-email-import/{key}', [AttendanceController::class, 'fetchEmailByKey'])->name('attendance.email.cron');
    Route::get('/document-reminders/send/{key}', [EmployeeDocumentController::class, 'sendReminderSummary'])->name('employee_documents.reminders.cron');
    Route::get('/vehicle-tracking/sync/{key}', [VehicleTrackingController::class, 'cron'])->name('vehicle_tracking.cron');

    Route::middleware(['auth'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/password', [AuthController::class, 'showPassword'])->name('password.edit');
        Route::put('/password', [AuthController::class, 'updatePassword'])->name('password.update');

        Route::middleware([ForcePasswordChange::class])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])
            ->middleware(CheckPermission::class . ':dashboard.view')
            ->name('dashboard');
        Route::get('/dashboard/edit', [DashboardController::class, 'edit'])
            ->middleware(CheckPermission::class . ':dashboard.customize')
            ->name('dashboard.edit');
        Route::put('/dashboard', [DashboardController::class, 'update'])
            ->middleware(CheckPermission::class . ':dashboard.customize')
            ->name('dashboard.update');
        Route::get('/dashboard/quick-actions', [DashboardController::class, 'editQuickActions'])
            ->middleware(CheckPermission::class . ':dashboard.quick_actions.manage')
            ->name('dashboard.quick_actions.edit');
        Route::put('/dashboard/quick-actions', [DashboardController::class, 'updateQuickActions'])
            ->middleware(CheckPermission::class . ':dashboard.quick_actions.manage')
            ->name('dashboard.quick_actions.update');

        Route::get('/updates/v1-1', [UpdateController::class, 'v11'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v1_1');
        Route::post('/updates/v1-1', [UpdateController::class, 'applyV11'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v1_1.apply');

        Route::get('/updates/v1-2', [UpdateController::class, 'v12'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v1_2');
        Route::post('/updates/v1-2', [UpdateController::class, 'applyV12'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v1_2.apply');

        Route::get('/updates/v1-3', [UpdateController::class, 'v13'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v1_3');
        Route::post('/updates/v1-3', [UpdateController::class, 'applyV13'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v1_3.apply');

        Route::get('/updates/v1-4', [UpdateController::class, 'v14'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v1_4');
        Route::post('/updates/v1-4', [UpdateController::class, 'applyV14'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v1_4.apply');

        Route::get('/updates/v1-5', [UpdateController::class, 'v15'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v1_5');
        Route::post('/updates/v1-5', [UpdateController::class, 'applyV15'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v1_5.apply');

        Route::get('/updates/v1-6', [UpdateController::class, 'v16'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v1_6');
        Route::post('/updates/v1-6', [UpdateController::class, 'applyV16'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v1_6.apply');

        Route::get('/updates/v1-7', [UpdateController::class, 'v17'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v1_7');
        Route::post('/updates/v1-7', [UpdateController::class, 'applyV17'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v1_7.apply');

        Route::get('/updates/v2-5', [UpdateController::class, 'v25'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_5');
        Route::post('/updates/v2-5', [UpdateController::class, 'applyV25'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_5.apply');

        Route::get('/updates/v2-5-2', [UpdateController::class, 'v252'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_5_2');
        Route::post('/updates/v2-5-2', [UpdateController::class, 'applyV252'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_5_2.apply');

        Route::get('/updates/v2-5-3', [UpdateController::class, 'v253'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_5_3');
        Route::post('/updates/v2-5-3', [UpdateController::class, 'applyV253'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_5_3.apply');


        Route::get('/updates/v2-6', [UpdateController::class, 'v26'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6');
        Route::post('/updates/v2-6', [UpdateController::class, 'applyV26'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6.apply');

        Route::get('/updates/v2-6-1', [UpdateController::class, 'v261'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_1');
        Route::post('/updates/v2-6-1', [UpdateController::class, 'applyV261'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_1.apply');

        Route::get('/updates/v2-6-2', [UpdateController::class, 'v262'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_2');
        Route::post('/updates/v2-6-2', [UpdateController::class, 'applyV262'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_2.apply');

        Route::get('/updates/v2-6-3', [UpdateController::class, 'v263'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_3');
        Route::post('/updates/v2-6-3', [UpdateController::class, 'applyV263'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_3.apply');

        Route::get('/updates/v2-6-4', [UpdateController::class, 'v264'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_4');
        Route::post('/updates/v2-6-4', [UpdateController::class, 'applyV264'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_4.apply');

        Route::get('/updates/v2-6-5', [UpdateController::class, 'v265'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_5');
        Route::post('/updates/v2-6-5', [UpdateController::class, 'applyV265'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_5.apply');

        Route::get('/updates/v2-6-6', [UpdateController::class, 'v266'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_6');
        Route::post('/updates/v2-6-6', [UpdateController::class, 'applyV266'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_6.apply');


        Route::get('/updates/v2-6-7', [UpdateController::class, 'v267'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_7');
        Route::post('/updates/v2-6-7', [UpdateController::class, 'applyV267'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_7.apply');

        Route::get('/updates/v2-6-8', [UpdateController::class, 'v268'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_8');
        Route::post('/updates/v2-6-8', [UpdateController::class, 'applyV268'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_8.apply');

        Route::get('/updates/v2-6-9', [UpdateController::class, 'v269'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_9');
        Route::post('/updates/v2-6-9', [UpdateController::class, 'applyV269'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_9.apply');

        Route::get('/updates/v2-6-10', [UpdateController::class, 'v2610'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_10');
        Route::post('/updates/v2-6-10', [UpdateController::class, 'applyV2610'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_10.apply');

        Route::get('/updates/v2-6-11', [UpdateController::class, 'v2611'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_11');
        Route::post('/updates/v2-6-11', [UpdateController::class, 'applyV2611'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_6_11.apply');

        Route::get('/updates/v2-8-6', [UpdateController::class, 'v286'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_8_6');
        Route::post('/updates/v2-8-6', [UpdateController::class, 'applyV286'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_8_6.apply');

        Route::get('/updates/v2-8-8', [UpdateController::class, 'v288'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_8_8');
        Route::post('/updates/v2-8-8', [UpdateController::class, 'applyV288'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_8_8.apply');

        Route::get('/updates/v2-9-0', [UpdateController::class, 'v290'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_9_0');
        Route::post('/updates/v2-9-0', [UpdateController::class, 'applyV290'])
            ->middleware(CheckPermission::class . ':settings.manage')
            ->name('updates.v2_9_0.apply');

        Route::get('/settings/updates', [PlatformUpdateController::class, 'index'])
            ->middleware(CheckPermission::class . ':platform_updates.view')
            ->name('platform_updates.index');
        Route::put('/settings/updates/settings', [PlatformUpdateController::class, 'updateSettings'])
            ->middleware(CheckPermission::class . ':platform_updates.manage')
            ->name('platform_updates.settings.update');
        Route::post('/settings/updates/upload', [PlatformUpdateController::class, 'upload'])
            ->middleware(CheckPermission::class . ':platform_updates.manage')
            ->name('platform_updates.upload');
        Route::post('/settings/updates/github-download', [PlatformUpdateController::class, 'downloadGithub'])
            ->middleware(CheckPermission::class . ':platform_updates.manage')
            ->name('platform_updates.github.download');
        Route::post('/settings/updates/apply', [PlatformUpdateController::class, 'apply'])
            ->middleware(CheckPermission::class . ':platform_updates.manage')
            ->name('platform_updates.apply');
        Route::delete('/settings/updates/packages/{filename}', [PlatformUpdateController::class, 'destroyPackage'])
            ->middleware(CheckPermission::class . ':platform_updates.manage')
            ->name('platform_updates.packages.destroy');
        Route::get('/settings/updates/backups/{filename}/download', [PlatformUpdateController::class, 'downloadBackup'])
            ->middleware(CheckPermission::class . ':platform_updates.manage')
            ->name('platform_updates.backups.download');
        Route::delete('/settings/updates/backups/{filename}', [PlatformUpdateController::class, 'destroyBackup'])
            ->middleware(CheckPermission::class . ':platform_updates.manage')
            ->name('platform_updates.backups.destroy');

        Route::get('/profile', [ProfileController::class, 'show'])
            ->middleware(CheckPermission::class . ':profile.view')
            ->name('profile.show');

        Route::get('/cron-jobs', [CronJobController::class, 'index'])
            ->middleware(CheckPermission::class . ':cron_jobs.view')
            ->name('cron_jobs.index');
        Route::post('/cron-jobs/attendance-email-import', [CronJobController::class, 'runAttendanceEmailImport'])
            ->middleware(CheckPermission::class . ':cron_jobs.run')
            ->name('cron_jobs.attendance_email_import');

        Route::get('/settings/core', [CoreSettingController::class, 'index'])
            ->middleware(CheckPermission::class . ':core_settings.view')
            ->name('core_settings.index');
        Route::put('/settings/core', [CoreSettingController::class, 'update'])
            ->middleware(CheckPermission::class . ':core_settings.manage')
            ->name('core_settings.update');

        Route::get('/settings/google-api', [GoogleApiSettingController::class, 'index'])
            ->middleware(CheckPermission::class . ':google_api_settings.view')
            ->name('google_api_settings.index');
        Route::put('/settings/google-api', [GoogleApiSettingController::class, 'update'])
            ->middleware(CheckPermission::class . ':google_api_settings.manage')
            ->name('google_api_settings.update');


        Route::get('/settings/vehicle-tracking', [VehicleTrackingController::class, 'settings'])
            ->middleware(CheckPermission::class . ':vehicle_tracking.settings.view')
            ->name('vehicle_tracking.settings');
        Route::put('/settings/vehicle-tracking', [VehicleTrackingController::class, 'updateSettings'])
            ->middleware(CheckPermission::class . ':vehicle_tracking.settings.manage')
            ->name('vehicle_tracking.settings.update');
        Route::post('/settings/vehicle-tracking/test', [VehicleTrackingController::class, 'test'])
            ->middleware(CheckPermission::class . ':vehicle_tracking.settings.manage')
            ->name('vehicle_tracking.settings.test');

        Route::get('/settings/leave-types', [LeaveTypeController::class, 'index'])
            ->middleware(CheckPermission::class . ':leave_types.view')
            ->name('leave_types.index');
        Route::get('/settings/leave-types/create', [LeaveTypeController::class, 'create'])
            ->middleware(CheckPermission::class . ':leave_types.manage')
            ->name('leave_types.create');
        Route::post('/settings/leave-types', [LeaveTypeController::class, 'store'])
            ->middleware(CheckPermission::class . ':leave_types.manage')
            ->name('leave_types.store');
        Route::get('/settings/leave-types/{leaveType}/edit', [LeaveTypeController::class, 'edit'])
            ->middleware(CheckPermission::class . ':leave_types.manage')
            ->name('leave_types.edit');
        Route::put('/settings/leave-types/{leaveType}', [LeaveTypeController::class, 'update'])
            ->middleware(CheckPermission::class . ':leave_types.manage')
            ->name('leave_types.update');
        Route::delete('/settings/leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])
            ->middleware(CheckPermission::class . ':leave_types.manage')
            ->name('leave_types.destroy');

        Route::get('/employee-documents/reminders', [EmployeeDocumentController::class, 'reminders'])
            ->middleware(CheckPermission::class . ':employee_documents.view')
            ->name('employee_documents.reminders');

        Route::get('/employee-compliance', [EmployeeComplianceController::class, 'index'])
            ->middleware(CheckPermission::class . ':employee_compliance.view')
            ->name('employee_compliance.index');

        Route::get('/calendar', [CalendarController::class, 'index'])
            ->middleware(CheckPermission::class . ':calendar.view')
            ->name('calendar.index');

        Route::get('/leave', [LeaveRequestController::class, 'index'])
            ->middleware(CheckPermission::class . ':leave.view')
            ->name('leave.index');
        Route::get('/leave/create', [LeaveRequestController::class, 'create'])
            ->middleware(CheckPermission::class . ':leave.create')
            ->name('leave.create');
        Route::post('/leave', [LeaveRequestController::class, 'store'])
            ->middleware(CheckPermission::class . ':leave.create')
            ->name('leave.store');
        Route::get('/leave/{leaveRequest}', [LeaveRequestController::class, 'show'])
            ->middleware(CheckPermission::class . ':leave.view')
            ->name('leave.show');
        Route::post('/leave/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])
            ->middleware(CheckPermission::class . ':leave.manage')
            ->name('leave.approve');
        Route::post('/leave/{leaveRequest}/decline', [LeaveRequestController::class, 'decline'])
            ->middleware(CheckPermission::class . ':leave.manage')
            ->name('leave.decline');
        Route::post('/leave/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])
            ->middleware(CheckPermission::class . ':leave.view')
            ->name('leave.cancel');

        Route::get('/vehicle-tracking', [VehicleTrackingController::class, 'index'])
            ->middleware(CheckPermission::class . ':vehicle_tracking.view')
            ->name('vehicle_tracking.index');
        Route::post('/vehicle-tracking/sync', [VehicleTrackingController::class, 'sync'])
            ->middleware(CheckPermission::class . ':vehicle_tracking.sync')
            ->name('vehicle_tracking.sync');

        Route::get('/vehicles/reminders', [VehicleController::class, 'reminders'])
            ->middleware(CheckPermission::class . ':vehicle.reminders.view')
            ->name('vehicles.reminders');
        Route::get('/vehicles/service-reminders', [VehicleController::class, 'serviceReminders'])
            ->middleware(CheckPermission::class . ':vehicle.service.reminders.view')
            ->name('vehicles.service_reminders');
        Route::get('/vehicles', [VehicleController::class, 'index'])
            ->middleware(CheckPermission::class . ':vehicle.view')
            ->name('vehicles.index');
        Route::get('/vehicles/create', [VehicleController::class, 'create'])
            ->middleware(CheckPermission::class . ':vehicle.create')
            ->name('vehicles.create');
        Route::post('/vehicles', [VehicleController::class, 'store'])
            ->middleware(CheckPermission::class . ':vehicle.create')
            ->name('vehicles.store');
        Route::get('/vehicles/fuel/create', [VehicleController::class, 'fuelSelect'])
            ->middleware(CheckPermission::class . ':vehicle.fuel.manage')
            ->name('vehicles.fuel.select');
        Route::post('/vehicles/fuel/create', [VehicleController::class, 'fuelSelectProceed'])
            ->middleware(CheckPermission::class . ':vehicle.fuel.manage')
            ->name('vehicles.fuel.select.proceed');
        Route::get('/vehicle/fuel/create', [VehicleController::class, 'fuelSelect'])
            ->middleware(CheckPermission::class . ':vehicle.fuel.manage');
        Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])
            ->middleware(CheckPermission::class . ':vehicle.view')
            ->name('vehicles.show');
        Route::get('/vehicle', [VehicleController::class, 'index'])
            ->middleware(CheckPermission::class . ':vehicle.view');
        Route::get('/vehicle/{vehicle}', [VehicleController::class, 'show'])
            ->middleware(CheckPermission::class . ':vehicle.view');
        Route::post('/vehicles/{vehicle}/tracking/sync', [VehicleTrackingController::class, 'syncVehicle'])
            ->middleware(CheckPermission::class . ':vehicle_tracking.sync')
            ->name('vehicles.tracking.sync');
        Route::put('/vehicles/{vehicle}/tracking/link', [VehicleTrackingController::class, 'updateVehicleLink'])
            ->middleware(CheckPermission::class . ':vehicle_tracking.link')
            ->name('vehicles.tracking.link');
        Route::get('/vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])
            ->middleware(CheckPermission::class . ':vehicle.edit')
            ->name('vehicles.edit');
        Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])
            ->middleware(CheckPermission::class . ':vehicle.edit')
            ->name('vehicles.update');
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])
            ->middleware(CheckPermission::class . ':vehicle.edit')
            ->name('vehicles.destroy');
        Route::post('/vehicles/{vehicle}/assign', [VehicleController::class, 'assign'])
            ->middleware(CheckPermission::class . ':vehicle.assign')
            ->name('vehicles.assign');
        Route::post('/vehicles/{vehicle}/unassign', [VehicleController::class, 'unassign'])
            ->middleware(CheckPermission::class . ':vehicle.assign')
            ->name('vehicles.unassign');
        Route::get('/vehicles/{vehicle}/fuel/create', [VehicleController::class, 'fuelCreate'])
            ->middleware(CheckPermission::class . ':vehicle.fuel.manage')
            ->name('vehicles.fuel.create');
        Route::post('/vehicles/{vehicle}/fuel', [VehicleController::class, 'fuelStore'])
            ->middleware(CheckPermission::class . ':vehicle.fuel.manage')
            ->name('vehicles.fuel.store');
        Route::get('/vehicles/{vehicle}/fuel/import', [VehicleController::class, 'fuelImport'])
            ->middleware(CheckPermission::class . ':vehicle.fuel.import')
            ->name('vehicles.fuel.import');
        Route::post('/vehicles/{vehicle}/fuel/import', [VehicleController::class, 'fuelImportStore'])
            ->middleware(CheckPermission::class . ':vehicle.fuel.import')
            ->name('vehicles.fuel.import.store');
        Route::get('/vehicles/{vehicle}/services/create', [VehicleController::class, 'serviceCreate'])
            ->middleware(CheckPermission::class . ':vehicle.service.manage')
            ->name('vehicles.services.create');
        Route::post('/vehicles/{vehicle}/services', [VehicleController::class, 'serviceStore'])
            ->middleware(CheckPermission::class . ':vehicle.service.manage')
            ->name('vehicles.services.store');
        Route::get('/vehicles/{vehicle}/documents/create', [VehicleController::class, 'documentCreate'])
            ->middleware(CheckPermission::class . ':vehicle.documents.upload')
            ->name('vehicles.documents.create');
        Route::post('/vehicles/{vehicle}/documents', [VehicleController::class, 'documentStore'])
            ->middleware(CheckPermission::class . ':vehicle.documents.upload')
            ->name('vehicles.documents.store');
        Route::get('/vehicle-documents/{document}/download', [VehicleController::class, 'documentDownload'])
            ->middleware(CheckPermission::class . ':vehicle.documents.view')
            ->name('vehicles.documents.download');
        Route::patch('/vehicle-documents/{document}/inactive', [VehicleController::class, 'documentInactive'])
            ->middleware(CheckPermission::class . ':vehicle.documents.manage')
            ->name('vehicles.documents.inactive');

        Route::get('/attendance', [AttendanceController::class, 'index'])
            ->middleware(CheckPermission::class . ':attendance.view')
            ->name('attendance.index');
        Route::get('/attendance/upload', [AttendanceController::class, 'upload'])
            ->middleware(CheckPermission::class . ':attendance.import')
            ->name('attendance.upload');
        Route::post('/attendance/upload', [AttendanceController::class, 'importUpload'])
            ->middleware(CheckPermission::class . ':attendance.import')
            ->name('attendance.import');
        Route::get('/attendance/manual-upload', [AttendanceController::class, 'manualUpload'])
            ->middleware(CheckPermission::class . ':attendance.manual_upload')
            ->name('attendance.manual_upload');
        Route::post('/attendance/manual-upload', [AttendanceController::class, 'importManualUpload'])
            ->middleware(CheckPermission::class . ':attendance.manual_upload')
            ->name('attendance.manual_import');
        Route::post('/attendance/email/fetch', [AttendanceController::class, 'fetchEmail'])
            ->middleware(CheckPermission::class . ':attendance.import')
            ->name('attendance.email.fetch');
        Route::get('/attendance/imports', [AttendanceController::class, 'imports'])
            ->middleware(CheckPermission::class . ':attendance.view')
            ->name('attendance.imports');
        Route::get('/attendance/{attendanceDay}', [AttendanceController::class, 'show'])
            ->middleware(CheckPermission::class . ':attendance.view')
            ->name('attendance.show');


        Route::get('/customers', [CustomerController::class, 'index'])
            ->middleware(CheckPermission::class . ':clients.view')
            ->name('customers.index');
        Route::get('/customers/create', [CustomerController::class, 'create'])
            ->middleware(CheckPermission::class . ':clients.manage')
            ->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])
            ->middleware(CheckPermission::class . ':clients.manage')
            ->name('customers.store');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])
            ->middleware(CheckPermission::class . ':clients.view')
            ->name('customers.show');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
            ->middleware(CheckPermission::class . ':clients.manage')
            ->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])
            ->middleware(CheckPermission::class . ':clients.manage')
            ->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
            ->middleware(CheckPermission::class . ':clients.manage')
            ->name('customers.destroy');
        Route::get('/clients', [CustomerController::class, 'index'])
            ->middleware(CheckPermission::class . ':clients.view');

        Route::post('/customers/{customer}/sites', [CustomerController::class, 'storeSite'])
            ->middleware(CheckPermission::class . ':customer_sites.manage')
            ->name('customers.sites.store');
        Route::put('/customers/{customer}/sites/{site}', [CustomerController::class, 'updateSite'])
            ->middleware(CheckPermission::class . ':customer_sites.manage')
            ->name('customers.sites.update');
        Route::delete('/customers/{customer}/sites/{site}', [CustomerController::class, 'destroySite'])
            ->middleware(CheckPermission::class . ':customer_sites.manage')
            ->name('customers.sites.destroy');

        Route::post('/customers/{customer}/contacts', [CustomerController::class, 'storeContact'])
            ->middleware(CheckPermission::class . ':customer_contacts.manage')
            ->name('customers.contacts.store');
        Route::post('/customers/{customer}/sites/{site}/contacts', [CustomerController::class, 'storeSiteContact'])
            ->middleware(CheckPermission::class . ':customer_contacts.manage')
            ->name('customers.sites.contacts.store');
        Route::put('/customers/{customer}/contacts/{contact}', [CustomerController::class, 'updateContact'])
            ->middleware(CheckPermission::class . ':customer_contacts.manage')
            ->name('customers.contacts.update');
        Route::delete('/customers/{customer}/contacts/{contact}', [CustomerController::class, 'destroyContact'])
            ->middleware(CheckPermission::class . ':customer_contacts.manage')
            ->name('customers.contacts.destroy');

        Route::post('/customers/{customer}/interactions', [CustomerController::class, 'storeInteraction'])
            ->middleware(CheckPermission::class . ':customer_interactions.manage')
            ->name('customers.interactions.store');
        Route::put('/customers/{customer}/interactions/{interaction}', [CustomerController::class, 'updateInteraction'])
            ->middleware(CheckPermission::class . ':customer_interactions.manage')
            ->name('customers.interactions.update');
        Route::delete('/customers/{customer}/interactions/{interaction}', [CustomerController::class, 'destroyInteraction'])
            ->middleware(CheckPermission::class . ':customer_interactions.manage')
            ->name('customers.interactions.destroy');

        Route::get('/employees', [EmployeeController::class, 'index'])
            ->middleware(CheckPermission::class . ':employees.view')
            ->name('employees.index');
        Route::get('/employees/create', [EmployeeController::class, 'create'])
            ->middleware(CheckPermission::class . ':employees.create')
            ->name('employees.create');
        Route::post('/employees', [EmployeeController::class, 'store'])
            ->middleware(CheckPermission::class . ':employees.create')
            ->name('employees.store');

        Route::get('/employees/{employee}/documents/create', [EmployeeDocumentController::class, 'create'])
            ->middleware(CheckPermission::class . ':employee_documents.upload')
            ->name('employee_documents.create');
        Route::post('/employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])
            ->middleware(CheckPermission::class . ':employee_documents.upload')
            ->name('employee_documents.store');
        Route::get('/employee-documents/{document}/download', [EmployeeDocumentController::class, 'download'])
            ->middleware(CheckPermission::class . ':employee_documents.view')
            ->name('employee_documents.download');
        Route::get('/employee-documents/{document}/edit', [EmployeeDocumentController::class, 'edit'])
            ->middleware(CheckPermission::class . ':employee_documents.manage')
            ->name('employee_documents.edit');
        Route::put('/employee-documents/{document}', [EmployeeDocumentController::class, 'update'])
            ->middleware(CheckPermission::class . ':employee_documents.manage')
            ->name('employee_documents.update');
        Route::patch('/employee-documents/{document}/inactive', [EmployeeDocumentController::class, 'markInactive'])
            ->middleware(CheckPermission::class . ':employee_documents.manage')
            ->name('employee_documents.inactive');
        Route::patch('/employee-documents/{document}/reactivate', [EmployeeDocumentController::class, 'reactivate'])
            ->middleware(CheckPermission::class . ':employee_documents.manage')
            ->name('employee_documents.reactivate');
        Route::delete('/employee-documents/{document}', [EmployeeDocumentController::class, 'destroy'])
            ->middleware(CheckPermission::class . ':employee_documents.manage')
            ->name('employee_documents.destroy');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])
            ->middleware(CheckPermission::class . ':employees.view')
            ->name('employees.show');
        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])
            ->middleware(CheckPermission::class . ':employees.edit')
            ->name('employees.edit');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])
            ->middleware(CheckPermission::class . ':employees.edit')
            ->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])
            ->middleware(CheckPermission::class . ':employees.delete')
            ->name('employees.destroy');

        Route::get('/departments', [DepartmentController::class, 'index'])
            ->middleware(CheckPermission::class . ':departments.view')
            ->name('departments.index');
        Route::get('/departments/create', [DepartmentController::class, 'create'])
            ->middleware(CheckPermission::class . ':departments.create')
            ->name('departments.create');
        Route::post('/departments', [DepartmentController::class, 'store'])
            ->middleware(CheckPermission::class . ':departments.create')
            ->name('departments.store');
        Route::get('/departments/{department}/edit', [DepartmentController::class, 'edit'])
            ->middleware(CheckPermission::class . ':departments.edit')
            ->name('departments.edit');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])
            ->middleware(CheckPermission::class . ':departments.edit')
            ->name('departments.update');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])
            ->middleware(CheckPermission::class . ':departments.delete')
            ->name('departments.destroy');

        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware(CheckPermission::class . ':roles.view')
            ->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])
            ->middleware(CheckPermission::class . ':roles.create')
            ->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware(CheckPermission::class . ':roles.create')
            ->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])
            ->middleware(CheckPermission::class . ':roles.edit')
            ->name('roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->middleware(CheckPermission::class . ':roles.edit')
            ->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->middleware(CheckPermission::class . ':roles.delete')
            ->name('roles.destroy');
        });
    });
});
