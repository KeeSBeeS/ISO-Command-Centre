# CLAUDE.md

ISO Admin Command Framework (isoadmin.co.za): a Laravel 11 admin platform for employees, attendance, leave, vehicles, customers and compliance.

## Working rules (mandatory)

1. **Keep this file up to date.** Whenever you add or change a feature, update this CLAUDE.md in the same commit. Record what the feature does, its key files, routes, permissions and tables, and any deployment notes. Remove or correct entries that are no longer true.
2. **Plan first for big features.** Before writing code for a big feature, present a plan and wait for the user's explicit approval. A big feature is any of these:
   - a new module or page
   - new database tables or migrations
   - changes across several areas
   - changes to install, update or deploy behaviour
   - changes to permissions or security.

   The plan covers the approach, the files to add or change, any database changes, how it will be delivered (package or ZIP), and the risks. Small fixes and small, clearly scoped changes do not need a plan.

## Hosting and deployment constraints

- Shared hosting with **no console access**: no Composer, Artisan, NPM or SSH on the server. All setup has to work from the browser.
- The app root sits inside `public_html` and the site is served from `/public`. Folders such as `storage/` can be reached over the web unless they are blocked with `.htaccess`.
- **The live site is newer than parts of this repo.** For example, `routes/web.php` and `resources/views/layouts/app.blade.php` here are from around v2.6.11, while the live site is v2.8.5 or later. Never ship those files wholesale. Add new routes in their own file, or patch the live files with a backup.
- Version marker: the `platform_version` row in `system_settings`, plus the `VERSION` file.

## How updates are delivered

From v2.9.0 on, updates are `.isopkg` packages installed through **Admin → Package Manager**. Build one with:

```
php tools/build-package.php --version=X.Y.Z --base=<git ref of last release>
```

- Release notes and migrations go in `package-releases/<version>/`. See `package-releases/_example/` for the layout.
- Every migration must have a working `down()`, so the update can be rolled back.
- The full format is in `README_V2_9_0_PACKAGE_MANAGER.md`.

## Features

### Package Manager (v2.9.0)

- Upload, review, install and roll back `.isopkg` update packages.
  - Installs back up every file they replace or delete, write files atomically, run the package's migrations and revert themselves if anything fails.
  - Rolling back an older install rolls back every newer install first.
- Code:
  - logic: `app/Services/PackageManager.php` (install / rollback / journal) and `app/Services/PackageArchive.php` (format and validation, no Laravel dependencies)
  - `app/Http/Controllers/PackageManagerController.php`
  - views in `resources/views/packages/`
  - models `PackageUpload` and `PackageInstall`.
- Routes: `routes/packages.php`, loaded by a `require` at the end of `routes/web.php`, under `/settings/packages`.
- Permission: `packages.manage`.
- Tables `package_uploads` and `package_installs` are created automatically on the first visit.
- Data folder: `storage/app/package-manager/`
  - `incoming/` is the upload and drop folder
  - `installs/<key>/` holds `journal.json`, the file backups and the migrations.
- Config: `config/packages.php`
  - `PACKAGE_SIGNING_KEY` (optional): only signed packages can be installed
  - `PACKAGE_RESCUE_KEY`: enables `public/iso-package-rescue.php` for emergency file rollback.
- Tests: `tests/Feature/PackageManagerTest.php`.
