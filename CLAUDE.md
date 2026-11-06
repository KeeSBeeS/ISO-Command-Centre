# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

ISO Admin Command Centre (`isoadmin.co.za`): a Laravel 11 / PHP 8.2+ admin platform for HR and operations. It covers employees, departments, roles and permissions, time attendance, leave and calendar, employee documents and compliance, vehicles, fuel, servicing and Cartrack tracking, and customers/sites. The current version is in `VERSION`. `app/Support/PlatformVersion.php` is stale, so don't trust it.

## Update workflow (required)

- **Every update bumps the version.** Each change shipped to the platform must increase the version in `VERSION`, using semantic versioning (`major.minor.patch`). Add a matching entry to `CHANGELOG.md` and a `README_V<version>_<TOPIC>.md` release note listing the changed files and how to apply them. If the update changes the database, also add its `/updates/vX-Y-Z` updater (see below).
- **Plan before implementing.** Before making any changes for an update, write a plan and get the user's agreement before proceeding. The plan should cover:
  - the goal and the version number the update will become;
  - the files to change or add;
  - any database, permission or settings changes, and how they stay idempotent;
  - how the change will be checked.

## Deployment model (shapes everything)

The production target is **shared hosting with no console access**. There is no SSH, Artisan, Composer or NPM on the server. The rules below follow from that:

- **Do not use Laravel migrations.** `database/migrations/` only holds the stock skeleton files. The schema lives in PHP:
  - `InstallController::createTables()` builds a fresh install at `/install`. It needs `INSTALLER_KEY` in `.env` and writes `storage/app/isoadmin_installed.lock`.
  - `UpdateController` holds one pair of methods per release. `vXYZ()` renders `resources/views/updates/vX_Y_Z.blade.php`, and `applyVXYZ()` runs the DB changes inside `DB::transaction`. Routes are `/updates/vX-Y-Z` in `routes/web.php`.
  - Schema changes must be **idempotent**: guard every change with `Schema::hasTable` / `Schema::hasColumn`, because updaters can be re-run. MySQL is the production DB, so set short explicit foreign-key names. Long auto-generated names hit error 1059 (see `CHANGELOG.md`).
  - A new release that changes the schema needs all of: a new updater method pair, routes, an update view, the same table/column logic in `InstallController` so fresh installs match, a `VERSION` bump, and a `README_V<version>_<TOPIC>.md` release note. Releases ship as "changed files only" packages.
- **Code must tolerate a schema that is behind.** Controllers and services often check `Schema::hasTable`/`hasColumn` and fail gracefully, for example `abort_unless(Schema::hasTable('crm_clients'), 404, 'Run the v2.2 update first.')`. Keep that pattern.
- **Scheduled jobs are URL-triggered cron calls with secret keys**, not `schedule:run`. Examples: `/attendance-email-import/{key}`, `/document-reminders/send/{key}`, `/vehicle-tracking/sync/{key}`.
- **No frontend build.** The Vite/Tailwind/`package.json` files are leftover skeleton. Blade views (`resources/views/layouts/app.blade.php`) use inline CSS/JS and no `@vite`.
- The `public/*hotfix*.php` and `public/isoadmin-clear-cache-*.php` files are standalone, key-protected, one-off scripts that patch a live install. They sit outside Laravel's routing.

## Commands (local dev only)

`vendor/` is not committed, so run `composer install` first.

```bash
composer install
```

```bash
php artisan serve
```

```bash
php artisan test
```

```bash
php artisan test --filter=TestName
```

```bash
vendor/bin/pint
```

`php -l path/to/File.php` is a quick syntax check that works without vendor. The only tests are the Laravel example tests, and `phpunit.xml` leaves the sqlite DB commented out.

## Architecture

- **Routing.** All routes are in `routes/web.php`, with no API routes. The middleware nesting is: `EnsureInstalled` (lock file plus core tables) → `auth` → `ForcePasswordChange` → per-route `CheckPermission::class . ':<permission.slug>'`.
- **Authorization.** Permissions are custom and database-backed (`roles`, `permissions`, `role_user`, `permission_role`, `permission_user`), not Gates or Policies. `User::hasPermission()` merges role permissions with direct user permissions. The `system-administrator` role bypasses every check. Views gate menu items and actions with `$user->hasPermission('...')`. New permissions are seeded inside the matching `applyVXYZ()` and in `InstallController::defaultPermissions()`, and later updaters re-sync System Administrator.
- **Settings.** Runtime configuration is stored in the DB: `SystemSetting::valueFor()` and `PlatformSetting::getValue()/setValue()`. That covers things like workday times and the Google/Cartrack API keys. Attendance mailbox settings come from `.env` (`ATTENDANCE_MAIL_*`, `ATTENDANCE_IMPORT_KEY`).
- **Services (`app/Services`)** hold the non-trivial logic:
  - `AttendanceCsvImporter` parses CSV into `attendance_raw_records` and rebuilds the per-user `attendance_days` row. On each day, the earliest punch is the start time and the latest punch is the checkout time. It matches employees by `attendance_name`, then by name or Person ID, with leading zeros tolerated.
  - `AttendanceMailboxImporter` (PHP IMAP) feeds the CSV importer.
  - `AttendancePolicyService` applies workday, public-holiday and expected-minutes rules.
  - `VehicleFuelCsvImporter` works per vehicle. It skips duplicates and raises the vehicle's ODO when the import shows a higher reading.
  - `CartrackFleetApiService`, `GoogleMapsDistanceService`, `LeaveBalanceService`, `SouthAfricanHolidaySync`, `VehicleReportBuilder`.
  - `EncryptedAttachmentStorage` encrypts uploaded documents at rest and serves downloads.
- **Domain rules documented in `README_INSTALL.md`:** a vehicle assignment checks for an active, non-expired "Vehicle Policy" employee document (it warns but doesn't block); a document reminder date is `expiry_date - advance days`; the next service ODO is `last service ODO + interval`; fuel-up KM travelled is calculated from the previous lower ODO.

## Repo state caveats

- The `README_V*.md` and `CHANGELOG.md` files describe features up to v2.8.5, including compliance, policies, the Update Manager and overtime. Some of the code they describe is missing from this repo. `UpdateController` and the routes stop at v2.6.11. `ClientController`, `OvertimeController` and `SettingsController` exist but have no routes. Check what is actually wired up before assuming a documented feature is present.
- `README.md` is the stock Laravel README. The project documentation is in `README_INSTALL.md` and the per-version `README_V*.md` files.
- `SOURCE_MANIFEST.md` and `FILES_INCLUDED.txt` are old package manifests (v2.6.11 / v2.6.9).
