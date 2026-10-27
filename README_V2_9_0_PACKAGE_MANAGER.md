# ISO Admin Command Framework v2.9.0

## Package Manager: uploads, installs and rollbacks

All future updates are delivered as a single **`.isopkg`** file and installed from the web interface. Every install creates a rollback point, so any update can be undone with one click.

### Where to upload updates

**Admin → Package Manager** (`/settings/packages`), visible to the System Administrator (permission `packages.manage`).

1. **Upload Package**: choose the `.isopkg` file and press Upload.
2. **Large packages**: if the file is bigger than the server upload limit shown on the page, copy it with FTP or cPanel File Manager into the drop folder
   `storage/app/package-manager/incoming/`, then press **Scan Drop Folder**.
3. Open **Review & Install**. The page runs these checks:
   - the package is newer than the installed version
   - the minimum or maximum installed version it needs
   - the PHP version and extensions it needs
   - its signature, when signing is enabled
   - the SHA-256 checksum of every file
   - write permission on every file it changes
4. Tick the confirmation box and press **Install**.

### What an install does

- Backs up every file it replaces or deletes before touching it.
- Writes each file atomically (temporary file, then rename), so no half-written PHP files are left behind.
- Runs the package's database migrations (`up()`).
- Updates `VERSION` and the `platform_version` setting.
- Clears the view, route, config and event caches and resets OPcache.
- If anything fails, the install is reverted automatically and marked **Failed**, with the error in its log.

### Rolling back

Open **Install History → Details / Rollback** for any install and press **Roll Back**. The rollback:

- restores every replaced and deleted file
- removes the files the install added, and any folders it created
- runs the package migrations' `down()` methods in reverse order
- sets the version back to the version that was installed before.

If you roll back an older install, every newer install is rolled back first, newest first, so the site always returns to a consistent earlier state. You can download the backup of each install as a ZIP from its details page.

### Emergency rescue (site will not load)

If an update stops Laravel from booting, the rollback page cannot be reached. To prepare for that:

1. Add `PACKAGE_RESCUE_KEY=<at least 16 random characters>` to `.env`.
2. If the site breaks, open `https://isoadmin.co.za/public/iso-package-rescue.php?key=<that key>`.
3. Press **Restore files**. This restores the files of the most recent install without loading Laravel.
4. When the site loads again, open Package Manager and press **Roll Back** on the install now marked **Rescued**. This reverts its database migrations and the version number.

### Signed packages (optional)

Set `PACKAGE_SIGNING_KEY=<shared secret>` in `.env`. From then on, only packages built with the same key (`--sign-key` or the `PACKAGE_SIGNING_KEY` environment variable when building) can be installed.

### Protected paths

A package can never write to `.env`, `storage/`, `bootstrap/cache/`, `.git/`, `node_modules/` or `public/storage`. Paths containing `..` and absolute paths are rejected.

---

## Package format (`.isopkg`)

A ZIP archive:

```
manifest.json
files/<path relative to the app root>     files to add or replace
migrations/<name>.php                     optional, run in listed order
```

`manifest.json`:

```json
{
  "format": 1,
  "package": "iso-command-centre",
  "version": "2.9.1",
  "title": "Attendance fixes",
  "description": "Short summary shown before install.",
  "released_at": "2026-11-02",
  "requires": { "min_version": "2.9.0", "max_version": null, "php": "8.2.0", "extensions": ["zip"] },
  "changelog": ["Fixed …", "Added …"],
  "files": [ { "path": "app/Http/Controllers/AttendanceController.php", "sha256": "…", "size": 12345 } ],
  "delete": [ "resources/views/old/page.blade.php" ],
  "migrations": [ { "name": "2026_11_02_000001_add_column", "path": "migrations/2026_11_02_000001_add_column.php", "sha256": "…" } ],
  "signature": "optional HMAC-SHA256 of the manifest without this field"
}
```

A migration is a standard Laravel anonymous migration. Always write a `down()` so the package can be rolled back:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('vehicles', 'colour')) {
            Schema::table('vehicles', fn (Blueprint $table) => $table->string('colour')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vehicles', 'colour')) {
            Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn('colour'));
        }
    }
};
```

## Building a package

Release notes and migrations go in `package-releases/<version>/`. See `package-releases/_example/` for a template. Then run:

```
php tools/build-package.php --version=2.9.1 --base=<git ref of the installed release>
```

This writes `dist/iso-command-centre-2.9.1.isopkg`. The package contains every file changed since `--base`, and files deleted in git are removed on install. Use `--files=a,b` to add individual files from the working tree.

## Installing v2.9.0 itself

v2.9.0 ships the Package Manager, so it is installed once by hand:

1. Upload the contents of `iso-admin-v2.9.0-package-manager.zip` into the application root (the folder that contains `app/`, `routes/` and `public/`), keeping the folder structure. The ZIP only adds new files and `VERSION`. It does **not** replace `routes/web.php`, the layout or any existing module.
2. Open `https://isoadmin.co.za/public/iso-package-manager-setup.php?key=iso-package-manager-setup-2026`. The script:
   - appends `require __DIR__ . '/packages.php';` to the end of `routes/web.php`
   - adds the Package Manager link to the Admin menu, above Logout
   - backs up both files first (`*.bak-<date>`)
   - clears the caches.

   It is safe to run more than once.
3. Open **Admin → Package Manager**. On this first visit, the database tables and the `packages.manage` permission are created and the version is set to 2.9.0.
4. Delete `public/iso-package-manager-setup.php`.

Every update after this one is installed through the Package Manager.
