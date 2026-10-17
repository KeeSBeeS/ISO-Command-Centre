# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

ISO Admin Command Framework ("ISO Command Centre") — a Laravel 11 / PHP 8.2+ internal admin platform for `isoadmin.co.za`: employees, departments, roles/permissions, time attendance, leave, employee documents, vehicles & fuel, vehicle tracking (Cartrack + Google Maps), customers, a calendar and personal dashboards. `README.md` is the stock Laravel readme; the real docs are `README_INSTALL.md`, the per-release `README_V*.md` notes and `CHANGELOG.md`.

## Deployment model drives everything

Production is **shared hosting with no console access** (no Composer, NPM, Vite or Artisan on the server). Releases ship as "changed-files-only" packages uploaded over the live install. Consequences:

- **Do not add Laravel migrations** for schema changes. `database/migrations` only holds the stock Laravel tables. Schema changes are done by **web updaters** in `app/Http/Controllers/UpdateController.php`: each version has a `vXY()` page (shows status, view `resources/views/updates/vX_Y.blade.php`) and an `applyVXY()` POST action, routed at `/updates/vX-Y` behind `CheckPermission:settings.manage`. Updaters must be idempotent — guard every change with `Schema::hasTable` / `Schema::hasColumn`, seed permissions with `updateOrInsert`/`firstOrCreate`, re-run `seedSystemAdministratorAllPermissions()`, and write the version to `system_settings.platform_version`. Newer updaters often re-call earlier `add*`/`seed*` helpers so a skipped step is recovered.
- `InstallController` (`/install`, gated by `INSTALLER_KEY` in `.env`) writes `storage/app/isoadmin_installed.lock` (checked by `EnsureInstalled`) and creates the core schema — but only the pre-v2.5 tables (users, RBAC, departments, profiles, employee documents, attendance, vehicles/fuel/documents/services, dashboard widgets, leave types). `system_settings`, `leave_requests`, `public_holidays`, `vehicle_tracking_snapshots`, `quick_action_preferences`, `customers` and later columns exist only via updaters, so a fresh install must still run the v2.5+ updaters.
- Code must tolerate partially-updated databases: controllers, views and the nav in `layouts/app.blade.php` check `Schema::hasTable` / `hasColumn` before touching optional tables/columns. Keep doing this for anything new.
- `AttendanceCsvImporter::ensureRawRecordDetailColumns()` alters `attendance_raw_records` at import time — schema changes are not confined to `UpdateController`.
- Front-end assets are **not built**: `layouts/app.blade.php` has an inline `<style>` (CSS variables, dark theme) and inline `<script>`; don't introduce `@vite` or compiled assets.
- One-off repair scripts live in `public/*.php` (key-protected via `?key=`, run once then deleted), e.g. route-cache clearing and Blade hotfixes.
- Scheduled work is DirectAdmin cron hitting key-protected URLs, not `artisan schedule`: `/attendance-email-import/{key}` (`ATTENDANCE_IMPORT_KEY`), `/document-reminders/send/{key}` (`DOCUMENT_REMINDER_KEY`, mails `DOCUMENT_REMINDER_EMAIL_TO`), `/vehicle-tracking/sync/{key}` (`cartrack_cron_key` system setting). Keys are compared with `hash_equals`.
- Several controllers/services read `env()` directly (attendance mailbox, reminder keys, installer key) rather than via `config()`, so `php artisan config:cache` would break them.
- Each release bumps `VERSION`, adds a `README_V<x>_<y>_<z>_<TOPIC>.md` listing changed files and apply steps, and usually a `CHANGELOG.md` entry. (`app/Support/PlatformVersion.php` is stale at 2.4.0; the DB `platform_version` setting is what updaters maintain and the layout footer shows.)

### Code and docs are out of sync

- The release notes/CHANGELOG describe v2.7–v2.8 features (customer sites/contacts, document-type matrix, policy acknowledgements, medical/licence compliance) and updaters up to v2.8.x. **None of that is in this repo** — the newest updater/route is v2.6.11. Don't assume a documented feature exists; check the code.
- There is also **unwired code** from a parallel line of development. It has no routes and its tables are created nowhere (not in the installer or any updater): `ClientController` + `CrmClient`/`CrmClientSite`/`CrmClientContact` (`crm_clients`, …), `OvertimeController` + `OvertimeEntry`, `SettingsController` + `PlatformSetting` (`platform_settings`), `CalendarEvent`, `EmployeeSickRecord`, `EmployeeLeaveAllocation`, and the services `LeaveBalanceService`, `AttendancePolicyService`, `SouthAfricanHolidaySync`, `GoogleMapsDistanceService`, `VehicleReportBuilder`, `EncryptedAttachmentStorage`. Views exist for some (`resources/views/clients`, `overtime`, `settings/index.blade.php`). Wiring any of it up needs an updater that creates the tables plus routes and permissions.

## Cross-cutting architecture

- **Routing** — everything is in `routes/web.php`, nested as `EnsureInstalled` → `auth` → `ForcePasswordChange` (users with `must_change_password` are forced to `/password`). Every route declares its permission: `->middleware(CheckPermission::class . ':module.action')`. New routes must follow this and the permission slug must be seeded (installer `defaultPermissions()` and/or an updater).
- **Authorization** — custom RBAC, no Gates/Policies. `User::hasPermission()` = active status AND (role `system-administrator` OR a direct permission in `permission_user` OR a permission via any role). `CheckPermission` also short-circuits for `system-administrator`. Controllers do extra in-method checks with `hasPermission` (e.g. `leave.manage`, `permissions.manage`); there is no record-level ownership check beyond those. Default roles: `system-administrator`, `director`, `manager`, `employee` (`roles.level` orders them, `is_system` protects built-ins).
- **Settings** — runtime configuration lives in the `system_settings` key/value table (`SystemSetting::valueFor($key, $default)`), grouped and edited in Core Settings (`/settings/core`), Google API (`/settings/google-api`) and Vehicle Tracking settings (`/settings/vehicle-tracking`); those settings screens are also restricted to `system-administrator` in the nav/controllers. Updaters seed the keys. `.env` holds only mailbox credentials, cron/installer keys and the reminder recipient.
- **File storage** — uploads go to the default disk via `storeAs('employee-documents/{user_id}/…')` and `storeAs('vehicle-documents/{vehicle_id}/…')`; downloads stream through controller routes (never public URLs).
- **Expiry/reminder pattern** — shared by employee and vehicle documents: `has_expiry`, `expires_at`, `remind_days_before`, and a stored `reminder_date = expires_at − remind_days_before`; `scopeReminderDue()` drives the reminder centres, the dashboard and the calendar. Documents are never deleted, only set `status = inactive`.

## Modules

### Employees, departments, roles (`EmployeeController`, `DepartmentController`, `RoleController`, `ProfileController`)
- **An employee is a `User`** — there is no separate employees table. `users` carries login + HR identity (`employee_code`, `attendance_name`, `phone`, `position`, `status`, `must_change_password`, `credentials_emailed_at`). `employee_profiles` (1:1, `user_id` unique) holds `employee_number`, `job_title`, mobile, emergency contact, start date, notes. `users.position` mirrors `profile.job_title`.
- Creating/resetting an employee generates a temporary password, emails it via `Mail::raw` (falls back to showing it in a flash message) and sets `must_change_password`.
- "Delete" deactivates (`status = inactive` on user and profile); inactive users lose all permissions and are skipped by the attendance matcher.
- Departments ↔ users and roles ↔ users are many-to-many; roles ↔ permissions many-to-many; users ↔ permissions direct via `permission_user` (only editable by holders of `permissions.manage`).
- The employee show page aggregates other modules: documents, current vehicle assignments, late-attendance stats from `attendance_days`, and a "valid vehicle policy" flag computed in the Blade view.

### Time attendance (`AttendanceController`, `AttendanceCsvImporter`, `AttendanceMailboxImporter`, `CronJobController`)
- Sources: manual CSV upload, director "manual upload", or IMAP fetch of CSV attachments from the attendance mailbox (requires the PHP IMAP extension; manual trigger at `/cron-jobs`).
- Pipeline: `attendance_imports` (one per file, `imported_by` → users) → `attendance_raw_records` (one per punch, `source_row_hash` unique for de-duplication, `user_id` nullable) → `attendance_days` (unique per `user_id` + `attendance_date`, rebuilt by `rebuildDay()`).
- Two CSV shapes are detected: event logs (one row per punch) and daily summaries (Name/Date/Check-In/Check-Out).
- Employee matching (`findEmployee`, active users only): Person ID against `users.employee_code` (and `attendance_employee_code` if present) with leading-zero variants (`0001` ↔ `1`), then exact `attendance_name`, then `name`, then case-insensitive versions. Unmatched rows are skipped and counted.
- Day rules: the earliest punch up to **09:00 (hardcoded)** is the clock-in, otherwise the first punch is a late clock-in (`is_late`, `late_minutes`); the last punch is checkout; single punches and duplicate clock-ins are recorded in `anomalies`. Days on a `public_holidays` row with `is_company_closed` keep the punches for audit but don't count as work or late.
- Separately, the attendance views compute late/early-leave against the `attendance_company_start_time` / `attendance_company_close_time` system settings — these are not the same as the importer's 09:00 `is_late` flag.
- `/attendance/{identifier}` resolves an employee (employee code with leading-zero variants, attendance name, email or user id), not an `AttendanceDay` id, despite the route parameter name.

### Leave (`LeaveRequestController`, `LeaveTypeController`)
- `leave_types` (code, `is_deductible`, `is_active`, sort order) → `leave_requests` (`user_id`, `leave_type_id` nullable, start/end dates, `total_days`, `status` pending/approved/declined/cancelled, `reviewed_by` → users).
- `LeaveRequest::scopeVisibleTo()`: holders of `leave.manage` see everything, everyone else sees only their own. Requests created by a manager are auto-approved.
- Leave is not linked to attendance; it surfaces on the calendar and dashboard only.

### Employee documents (`EmployeeDocumentController`)
- `employee_documents` belongs to a user (`user_id`) and uploader (`uploaded_by`). Types are fixed in `EmployeeDocument::TYPES` (medical, sick_note, warning, certificate, company_policy, vehicle_policy, other).
- **Link to vehicles:** an active, non-expired `vehicle_policy` document is what `VehicleController::hasValidVehiclePolicy()` checks when assigning a vehicle.
- Reminder centre at `/employee-documents/reminders`; the cron URL emails a summary and stamps `last_reminder_sent_at`.

### Vehicles & fuel (`VehicleController`, `VehicleFuelCsvImporter`)
- `vehicles` is the hub: `vehicle_assignments`, `vehicle_fuel_ups`, `vehicle_documents`, `vehicle_service_records`, `vehicle_tracking_snapshots` all hang off `vehicle_id` (cascade delete; snapshots null on delete).
- **Assignments link vehicles to users.** Assigning closes any active assignment (`status = inactive`, `unassigned_at`) and creates a new one; `Vehicle::currentAssignment` is the latest active row. If the driver lacks a valid vehicle policy document the assignment still saves with `policy_warning = true` and a flash warning.
- **Odometer:** `vehicles.odo` only ever increases and is bumped by manual fuel-ups, fuel CSV imports, service records and Cartrack syncs. Manual fuel-ups compute KM travelled from the previous lower odometer reading. CSV import is per vehicle, de-duplicated by `source_row_hash`, and `total_cost = litres × price`.
- **Service tracking** (`Vehicle::getServiceSummaryAttribute`): next service = highest `service_odo` + `service_interval_km`, compared with `latest_odometer` (max fuel-up odometer, else `odo`); states are not-configured / no-baseline / ok / due-soon (within `service_reminder_km`) / overdue.
- Vehicle documents (NATIS, licence disk, insurance, …) use the shared expiry/reminder pattern (`/vehicles/reminders`); service reminders are at `/vehicles/service-reminders`.

### Vehicle tracking (`VehicleTrackingController`, `CartrackFleetApiService`)
- Pulls the Cartrack Fleet API (credentials and toggles in `cartrack_*` system settings), matches remote vehicles to local ones by normalised registration against `registration_number`, `cartrack_registration` or `cartrack_external_key` (editable per vehicle via the tracking link form), and stores a `vehicle_tracking_snapshots` row per sync.
- It also denormalises the latest position/status/odometer onto `vehicles.tracking_last_*` columns, which drive the Google Maps fleet map on the vehicles index/show pages (`google_maps_*` settings; shown only with `vehicle_tracking.view`).

### Customers (`CustomerController`)
- A standalone `customers` table (soft deletes, `created_by` → users) guarded by the `clients.view` / `clients.manage` permissions; `/clients` is an alias for the customer index. It is not related to employees, vehicles or the unwired `CrmClient` models.

### Calendar (`CalendarController`)
- It has no table of its own. It builds a month grid from public holidays, leave requests, employee and vehicle document expiries/reminders, attendance days, vehicle service due states and tracking activity, filtered by the viewer's permissions (`availableCalendarTypes`).

### Dashboard (`DashboardController`)
- Widgets and quick actions are defined in code (`availableWidgets()`, `availableQuickActions()`, each gated by a permission) and pull metrics from attendance, documents, vehicles/fuel, leave and customers. Per-user layout is stored in `dashboard_widget_preferences` (`user_id` + `widget_key`, order, size, visibility) and `quick_action_preferences` (`user_id` + `action_key`). New widgets/actions must be added to these arrays and the matching Blade sections.

## Relationship map (active tables)

```
users ─1:1─ employee_profiles
users ─M:N─ departments        (department_user)
users ─M:N─ roles ─M:N─ permissions   (role_user, permission_role)
users ─M:N─ permissions        (permission_user, direct grants)
users ─1:N─ employee_documents (user_id; uploaded_by → users)
users ─1:N─ attendance_days    (unique user_id+date) ← rebuilt from attendance_raw_records
attendance_imports ─1:N─ attendance_raw_records (user_id nullable)
users ─1:N─ leave_requests ─N:1─ leave_types   (reviewed_by → users)
users ─M:N─ vehicles           (vehicle_assignments: status active/inactive, policy_warning)
vehicles ─1:N─ vehicle_fuel_ups | vehicle_documents | vehicle_service_records | vehicle_tracking_snapshots
users ─1:N─ dashboard_widget_preferences | quick_action_preferences
customers.created_by → users
public_holidays, system_settings: standalone, read by attendance/calendar and all modules respectively
```

Audit columns (`created_by`, `uploaded_by`, `assigned_by`, `recorded_by`, `imported_by`, `reviewed_by`) are nullable FKs to `users` with `nullOnDelete`; ownership FKs (`user_id`, `vehicle_id`) cascade.

## Local commands

Standard Laravel tooling works locally (requires `composer install`; `vendor/` is not committed):

```bash
composer install
php artisan serve
php artisan test                      # PHPUnit (tests/Unit, tests/Feature) — only example tests exist
php artisan test --filter=TestName    # single test
vendor/bin/pint                       # code style
php -l path/to/File.php               # quick syntax check (useful since there is little test coverage)
php artisan route:list                # sanity-check routes after editing web.php
```

`composer.json` contains a production-specific `cache-dir` and `@/usr/local/bin/php` script paths; adjust locally if they fail rather than committing changes to them.
