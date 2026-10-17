# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

ISO Admin Command Framework ("ISO Command Centre") — a Laravel 11 / PHP 8.2+ internal admin platform for `isoadmin.co.za`: employees, departments, roles/permissions, time attendance, leave, overtime, employee documents/compliance, vehicles & fuel, vehicle tracking (Cartrack, Google Maps), customers/clients/sites, calendar and dashboards. `README.md` is the stock Laravel readme; the real docs are `README_INSTALL.md` and the per-release `README_V*.md` notes plus `CHANGELOG.md`.

## Deployment model drives everything

Production is **shared hosting with no console access** (no Composer, NPM, Vite or Artisan on the server). Releases ship as "changed-files-only" packages uploaded over the live install. Consequences:

- **Do not add Laravel migrations** for schema changes. `database/migrations` only holds the stock Laravel tables. Schema changes are done by **web updaters** in `app/Http/Controllers/UpdateController.php`: each version has a `vXY()` page (shows status, view `resources/views/updates/vX_Y.blade.php`) and an `applyVXY()` POST action, routed at `/updates/vX-Y` behind `CheckPermission:settings.manage`. Updaters must be idempotent — guard every change with `Schema::hasTable` / `Schema::hasColumn`, seed permissions with `updateOrInsert`/`firstOrCreate`, re-run `seedSystemAdministratorAllPermissions()`, and write the version to `system_settings.platform_version`. Newer updaters often re-call earlier `add*`/`seed*` helpers so a skipped step is recovered.
- `InstallController` (`/install`, gated by `INSTALLER_KEY` in `.env`) creates the whole schema for fresh installs and writes `storage/app/isoadmin_installed.lock`. When adding tables, add them to both the installer and a new updater.
- Code must tolerate partially-updated databases: models/controllers frequently check `Schema::hasTable(...)` before querying optional tables (e.g. `User::hasPermission` checks `permission_user`).
- Front-end assets are **not built**: `layouts/app.blade.php` uses an inline `<style>` block and inline `<script>`; don't introduce `@vite` or compiled assets.
- One-off repair scripts live in `public/*.php` (key-protected via `?key=`, meant to be run once then deleted), e.g. route-cache clearing and Blade hotfixes.
- Scheduled work is triggered by DirectAdmin cron hitting key-protected URLs (`/attendance-email-import/{key}`, `/document-reminders/send/{key}`, `/vehicle-tracking/sync/{key}`), not `artisan schedule`.
- Each release bumps `VERSION`, adds a `README_V<x>_<y>_<z>_<TOPIC>.md` listing changed files and apply steps, and usually a `CHANGELOG.md` entry. (`app/Support/PlatformVersion.php` is stale at 2.4.0; the DB `platform_version` setting is what updaters maintain.)

Note: the repo snapshot may lag the release notes — e.g. READMEs/CHANGELOG describe v2.7–v2.8 features (customer sites/contacts, compliance, policies) whose updaters and code are not all present here. Verify against code before assuming a feature exists.

## Architecture

- **Routing** — everything is in `routes/web.php`, nested as `EnsureInstalled` → `auth` → `ForcePasswordChange`. Every route declares its permission explicitly: `->middleware(CheckPermission::class . ':module.action')`. New routes must follow this pattern and the permission slug must be seeded by an updater (and the installer).
- **Authorization** — custom RBAC, not Gates/Policies. `Role` and `Permission` models with role pivots plus direct per-user permissions (`permission_user`). Role `system-administrator` bypasses all checks; default roles are `director`, `manager`, `employee`. Users with `status !== 'active'` have no permissions.
- **Controllers are fat**; reusable logic lives in `app/Services` (e.g. `AttendanceCsvImporter`, `AttendanceMailboxImporter` (PHP IMAP), `VehicleFuelCsvImporter`, `CartrackFleetApiService`, `GoogleMapsDistanceService`, `LeaveBalanceService`, `SouthAfricanHolidaySync`, `EncryptedAttachmentStorage` for encrypted uploads).
- **Settings** — runtime config is DB-backed (`SystemSetting::valueFor()`, `PlatformSetting::getValue()/setValue()`) and edited in Core Settings / Google API settings screens, rather than `.env`/config files (attendance mailbox and cron keys are in `.env`, see `README_INSTALL.md`).
- **Attendance** — CSV rows (from upload or the IMAP mailbox) are stored as `attendance_raw_records`, matched to employees (by attendance CSV name / employee code with leading-zero tolerance), and summarised into `attendance_days` (earliest punch = start, latest = checkout; single punch is flagged). `rebuildAllExistingDays()` regenerates summaries from raw records.

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
