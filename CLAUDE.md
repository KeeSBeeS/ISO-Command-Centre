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
3. **Always deliver a ZIP for the Package Manager.** Whenever you make an update package, also build it as a `.zip` and send that file to the user so they can upload it straight to **Admin → Package Manager**. Build it with:

   ```
   php tools/build-package.php --version=X.Y.Z --base=<last release> --ext=zip
   ```

   This produces `dist/iso-command-centre-X.Y.Z.zip`. Check it with `PackageArchive::open()` before sending, and say in your reply which version it installs and what version it requires.

## Hosting and deployment constraints

- Shared hosting with **no console access**: no Composer, Artisan, NPM or SSH on the server. All setup has to work from the browser.
- The app root sits inside `public_html` and the site is served from `/public`. Folders such as `storage/` can be reached over the web unless they are blocked with `.htaccess`.
- **The live site is newer than parts of this repo.** For example, `routes/web.php` and `resources/views/layouts/app.blade.php` here are from around v2.6.11, while the live site is v2.8.5 or later. Never ship those files wholesale. Add new routes in their own file, or patch the live files with a backup.
- Version marker: the `platform_version` row in `system_settings`, plus the `VERSION` file.

## How updates are delivered

From v2.9.0 on, updates are `.isopkg` packages installed through **Admin → Package Manager**. Build one with:

```
php tools/build-package.php --version=X.Y.Z --base=<git ref of last release> --ext=zip
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

### WhatsApp Business (v2.9.1)

- Meta WhatsApp Cloud API integration:
  - shared inbox with polling refresh, ticks, media, templates and 24-hour service window enforcement
  - signed webhook (`X-Hub-Signature-256`), STOP/START consent keywords, optional auto-reply
  - employee opt-in directory, broadcasts (opted-in only), template sync
  - notifications: leave decision (Eloquent `updated` hook on `LeaveRequest`), employee and vehicle document expiry (cron).
- Code:
  - `app/Providers/WhatsAppServiceProvider.php` loads `routes/whatsapp.php` in the `web` group and registers the public webhook and cron routes outside it (no CSRF or session)
  - services in `app/Services/WhatsApp/`: `WhatsAppCloudApi` (Graph API), `WhatsAppMessenger` (send, queue, retry, window and opt-in rules), `WhatsAppWebhookProcessor`, `WhatsAppNotifier`, `WhatsAppBroadcaster`, `WhatsAppTemplateSync`, `WhatsAppSettings` (system_settings group `WhatsApp`, secrets encrypted with APP_KEY), `WhatsAppInstaller` (tables, settings, permissions; `uninstall()` reverses), `WhatsAppActivation` (patches live `bootstrap/providers.php` and layout between `whatsapp-module` markers), `PhoneNumber`
  - controllers `app/Http/Controllers/WhatsApp*Controller.php`, models `app/Models/WhatsApp*.php`, views `resources/views/whatsapp/`.
- Routes: `/whatsapp` (inbox, contacts, templates, broadcasts), `/settings/whatsapp`, public `/whatsapp/webhook` (GET verify, POST receive) and `/whatsapp/cron/{key}`.
- Permissions: `whatsapp.view`, `whatsapp.send`, `whatsapp.contacts.manage`, `whatsapp.broadcast`, `whatsapp.templates.manage`, `whatsapp.settings.view`, `whatsapp.settings.manage`.
- Tables: `whatsapp_contacts`, `whatsapp_messages`, `whatsapp_templates`, `whatsapp_broadcasts`, `whatsapp_webhook_events`.
- Delivery: package migration `package-releases/2.9.1/migrations/2026_10_27_000001_install_whatsapp_business.php`. `up()` runs the installer and `WhatsAppActivation::add()`; `down()` runs `remove()` and `uninstall()`, which drops the tables and their data.
- The repo's `bootstrap/providers.php` and layout deliberately do not include WhatsApp (the migration patches the live copies). Tests register the provider in `tests/Feature/WhatsApp/InteractsWithWhatsApp.php`.
- Tests: `tests/Feature/WhatsApp/`, `tests/Unit/WhatsAppPhoneNumberTest.php`.

