# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

ISO Admin Command Centre (`isoadmin.co.za`): a Laravel 11 / PHP 8.2+ admin platform for HR and operations. It covers employees, departments, roles and permissions, time attendance, leave and calendar, employee documents and compliance, vehicles, fuel, servicing and Cartrack tracking, and customers/sites. The current version is in `VERSION`. `app/Support/PlatformVersion.php` is stale, so don't trust it.

## Update workflow (required)

- **Every update bumps the version.** Each change shipped to the platform must increase the version in `VERSION`, using semantic versioning (`major.minor.patch`). Add a matching entry to `CHANGELOG.md` and a `README_V<version>_<TOPIC>.md` release note. If the update changes the database, add a migration (see below).
- **Plan before implementing.** Before making any changes for an update, write a plan and get the user's agreement before proceeding. The plan should cover:
  - the goal and the version number the update will become;
  - the files to change or add;
  - any database, permission or settings changes, and how they stay idempotent;
  - how the change will be checked.
- **Releasing:** after merging to `main`, run the GitHub Action **Release** with `v<VERSION>`, or push that tag. It refuses a version that doesn't match `VERSION`. The site's Update Manager installs the resulting GitHub release. The user guide is `docs/updates.md`.

## Deployment model (shapes everything)

The production target is **DirectAdmin shared hosting with no console access**. There is no SSH, Artisan, Composer or NPM on the server. From v3.0.0 the site updates itself from GitHub releases (the Update Manager, ported from the Jarvis repo `KeeSBeeS/KeeS-Jarvis`). The rules below follow from that:

- **Releases are self-contained zips.** `scripts/build-release.sh` (run by `.github/workflows/release.yml` after the tests) copies `app bootstrap config database installer public resources routes`, `artisan` and the Composer files, then installs `vendor/` with `--no-dev`. If a new runtime directory or top-level file is added, add it to the script too, or it will be missing from releases. The `public/*hotfix*.php` and `public/isoadmin-clear-cache-*.php` one-off scripts (for v2.8.x sites) are excluded.
- **Database changes are Laravel migrations** in `database/migrations/`. `Updater::finish()` runs `migrate --force` in the new release, and a rollback restores a database backup rather than running `down()`, so `down()` stays empty. Rules:
  - Migrations must work forwards from the previous release and be **idempotent**. Guard with `Schema::hasTable` / `Schema::hasColumn`, because sites installed before v3.0.0 built their schema without migrations. The three stock `0001_01_01_*` migrations only create missing tables.
  - MySQL is the production DB, so set short explicit foreign-key names. Long auto-generated names hit error 1059.
  - New permissions go in a migration (see `2026_11_06_000000_add_platform_updates_permission.php`) **and** in `InstallController::defaultPermissions()`.
  - A fresh install still builds its tables in `InstallController::createTables()` at `/install` (needs `INSTALLER_KEY`, writes `storage/app/isoadmin_installed.lock`), then runs `migrate`. Keep new tables in both places, or make the installer rely on the migration.
  - `UpdateController` and its `/updates/vX-Y-Z` pages are the pre-v3.0.0 updaters. Keep them for older sites, but don't add new ones.
- **Code must tolerate a schema that is behind.** Controllers and services often check `Schema::hasTable`/`hasColumn` and fail gracefully, for example `abort_unless(Schema::hasTable('crm_clients'), 404, 'Run the v2.2 update first.')`. Keep that pattern.
- **Scheduled jobs.** The old jobs are URL-triggered cron calls with secret keys: `/attendance-email-import/{key}`, `/document-reminders/send/{key}`, `/vehicle-tracking/sync/{key}`. From v3.0.0, `ISO_HOME/cron.php` also runs `schedule:run` every minute (`routes/console.php`; for example the hourly `iso:check-updates`).
- **No frontend build.** The Vite/Tailwind/`package.json` files are leftover skeleton; there is no `@vite`. Since v3.1.0 the look comes from one plain stylesheet, `public/css/iso.css` (the design system), loaded by `resources/views/layouts/app.blade.php` with `?v=<version>` so browsers fetch it again after every update. Release zips copy `public/` into the web folder, so the file reaches the site with each release.

### Server layout (v3.0.0+)

```
ISO_HOME/                    e.g. /home/<user>/iso-command-centre (private)
  releases/<version>/        one unpacked release per version (the last 3 are kept); v2.8.5 is the moved old site
  shared/.env                settings, kept across releases
  shared/storage/            uploads (app/), logs, sessions, cache, maintenance "down" file
  backups/                   gzipped SQL backups taken before each update and rollback (last 5 kept)
  current.php                returns the active release name, e.g. 'v3.0.0'
  tools.php, cron.php        plain-PHP helpers; cron.php runs every minute (DirectAdmin cron)
  update-pending.php         exists only while an update is being finished
  update-failed.php          why the last update was undone, until dismissed
  update-history.php         updates and rollbacks (a file, so database restores don't erase it)
  recovery-key.php           hash of the recovery key
public_html/                 the web folder
  index.php                  front door: defines ISO_HOME and ISO_WEB, loads releases/<current>/
  recovery.php, .htaccess    emergency page; rewrite rules (also redirect old /public/... links)
```

- The front door, `cron.php`, `tools.php`, `recovery.php` and `.htaccess` are generated from `installer/stubs/` by `iso_setup_publish()`, always from the newest installed release's stubs, so they must keep working with any release. When `ISO_HOME` is defined, `bootstrap/app.php` points the environment file and storage at `ISO_HOME/shared`, and `config('iso.home')` exposes it. In a development checkout it is undefined and everything works as normal Laravel (the Updates page says updates only work on the server).
- `installer/iso-setup.php` is a standalone plain-PHP script (no Laravel), uploaded with the zip. It has three modes:
  - **move:** a v2.8.x site, with Laravel directly in `public_html`. It moves the listed old entries into `releases/v<old>`, copies `.env` and moves `storage/app` into `shared/` (symlinking it back), clears cached config and routes, publishes the front door and leaves an update pending.
  - **upgrade:** a front door already exists.
  - **first install:** asks for the database details, writes `shared/.env` with an `INSTALLER_KEY`, then hands over to `/install`.

  With `ISO_SETUP_LIBRARY` defined it only defines functions. The Update Manager (`App\Updates\Server::loadTools()`) and the tests use that.
- Keep `tools.php.stub` and `iso-setup.php` free of Laravel dependencies and of PHP features newer than 8.2: they must work when Laravel can't start.

## Update Manager

`/settings/updates` (`PlatformUpdateController`, permission `platform_updates.manage`; System Administrators always) installs GitHub releases and switches between installed versions. The code is in `app/Updates/`:

- `GitHubReleases` reads releases of `config('iso.updates.repository')` with the token in `ISO_GITHUB_TOKEN`, which is saved to `shared/.env` from the page via `App\Support\EnvFile`. It downloads and checksum-verifies `iso-command-centre-vX.Y.Z.zip`. GitHub only sends a release asset's bytes when `Accept` is exactly `application/octet-stream`, so the header is set once per request, never merged. `UpdateCheck` caches the latest release (hourly), and the layout shows "update available" to people who can update.
- An update is two requests:
  - `Updater::start()` runs in the old release: download, unpack, back up the database, write `update-pending.php`, enter maintenance mode, switch `current.php`, then redirect to `/settings/updates/finish/<token>`.
  - `Updater::finish()` runs in the new release: `migrate`, set `system_settings.platform_version`, run `HealthCheck`, then keep the update or restore the backup and switch back.

  Maintenance mode lets only `settings/updates`, `settings/updates/finish/*` and `login` through (`bootstrap/app.php`).
- Shared hosting's opcode cache can keep serving the old release for a minute or so after a switch. So:
  - state files are read with `iso_read_php()`, which evals the file instead of using `require`;
  - the finish step shows a self-refreshing "Finishing the update" page while `config('iso.version')` is still the old one;
  - opening `/settings/updates` while an update is pending and the new version runs redirects to the finish step.

  Never assume the very next request after a switch runs the new code.
- `config('iso.version')` is `v` plus the `VERSION` file. Release folders and tags use the `v` form.
- `DatabaseBackup` dumps MySQL in plain PHP. Restoring uses `iso_restore_backup()` from `tools.php.stub`, the same code `recovery.php` uses. Releases without `app/Updates/Updater.php` (v2.8.5 and older) can't be switched to from the page, only from `recovery.php`.
- Events are logged to the Laravel log as `platform_update.*` (`UpdateLog`). There is no activity-log table.
- `tests/Feature/EveryPageTest` opens every GET page as a System Administrator, after running every old `/updates/vX-Y-Z` step, and fails on any 500 or a page without the design system. Run it on MySQL too: `DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=... php artisan test --filter=EveryPageTest`.
- Tests: `tests/Support/FakeServer` builds a throwaway `ISO_HOME` plus web folder with stand-in releases, and runs the front door, `cron.php`, `recovery.php` and `iso-setup.php` in a separate PHP process. `tests/Support/InstallsIsoSchema` builds the schema through `InstallController`, as `/install` does. `DatabaseBackupTest` needs MySQL (`ISO_TEST_MYSQL_*`), and the release workflow provides it.

## Responsive design (required)

- **Design for PC first.** Most people use the site on a desktop or laptop, so lay pages out for a wide screen first.
- **Every page must still be fully usable on tablet and phone.** That covers every page, form, table and action, and it applies to every change, not only new pages.
- **Use the design system** in `public/css/iso.css` rather than new one-off styles:
  - layout: `.grid.cols-2/3/4`, `.actions`, `.page-head`, `.stack`;
  - building blocks: `.card` (and `.card.flush` with `.card-head`), `.metric`, `.metric-box`, `.kv`;
  - controls: `.btn` (`.primary`, `.danger`), `.pill` (`.warn`, `.danger`, `.info`, `.off`), `.alert` (`.success`, `.warning`, `.error`, `.info`);
  - tables and forms: `.table-wrap`, `.form-grid`, `.form-row`, `.check`.

  Colours come from its CSS variables (`--text`, `--muted`, `--line`, `--brand`, `--ok`/`--warn`/`--danger`/`--info` and their `-soft` backgrounds), never hard-coded dark-theme colours. Icons are `<x-icon name="..." />` (`resources/views/components/icon.blade.php`; add new ones there), never emoji in new work. The design system adapts at `1080px` (fewer columns), `960px` (the sidebar becomes a slide-in menu) and `560px` (full-width buttons, smaller padding). Pages with their own styles use media queries at these same sizes, and push them with `@push('head')`.
- **The sidebar** is the `$navigation` list at the top of `layouts/app.blade.php`. Each link keeps its permission and table check; add new modules there.
- **No sideways scrolling of the page.** A wide table goes in `.table-wrap`, which scrolls by itself. Long values such as URLs, keys and file names must wrap.
- **Touch-friendly controls:** buttons and links must be large enough to tap, and nothing may depend on hover alone.
- **Check every changed page** at phone width (about 390px), tablet width (about 820px) and PC width (1280px or more) before finishing.

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

`php -l path/to/File.php` is a quick syntax check that works without vendor. Tests run on in-memory SQLite (`phpunit.xml`).

```bash
scripts/build-release.sh v3.0.0
```

This builds a release zip into `dist/` locally, as the release workflow does.

## Architecture

- **Routing.** All routes are in `routes/web.php`, with no API routes. The middleware nesting is: `EnsureInstalled` (lock file plus core tables) → `auth` → `ForcePasswordChange` → per-route `CheckPermission::class . ':<permission.slug>'`.
- **Authorization.** Permissions are custom and database-backed (`roles`, `permissions`, `role_user`, `permission_role`, `permission_user`), not Gates or Policies. `User::hasPermission()` merges role permissions with direct user permissions. The `system-administrator` role bypasses every check. Views gate menu items and actions with `$user->hasPermission('...')`.
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
