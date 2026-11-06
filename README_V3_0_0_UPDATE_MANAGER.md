# ISO Admin Command Framework v3.0.0

## Update Manager

ISO Command Centre now installs its own updates, the same way the Jarvis platform does. New versions are published as GitHub releases. **System Settings → Updates** (`/settings/updates`) downloads them, backs up the database, switches over, applies the database changes and checks itself. If anything fails, it switches back by itself.

The full guide, including the one-time move from v2.8.x, is in `docs/updates.md`.

## What's new

- **Updates page** (`/settings/updates`), for System Administrators and the new `platform_updates.manage` permission:
  - shows when a new version is available and installs it;
  - switches back to any of the last 3 versions, either code only or with the database as that version left it;
  - keeps a history of updates and rollbacks;
  - stores the GitHub token used to read the private repository.
- **"Update available" notice** at the top of every page, for people who can update. The check runs every hour.
- **Automatic database backups** (plain PHP, no `mysqldump` needed) before every update and rollback. The last 5 are kept.
- **Safety nets that work even when the site can't start:**
  - the front door switches back if a new version crashes while starting;
  - `cron.php` undoes an update that was never finished;
  - `recovery.php` switches versions and restores the latest backup with a one-time recovery key.
- **Database changes are now Laravel migrations,** run automatically during an update. The old `/updates/vX-Y-Z` pages stay for sites that are older still.
- **Release workflow** (`.github/workflows/release.yml`): tests, then builds a ready-to-run zip with `vendor/` included, then publishes the GitHub release.

## Server layout

The code now lives outside the web folder:

```
/home/<user>/iso-command-centre/
  releases/<version>/   one folder per installed version
  shared/.env           settings, kept across versions
  shared/storage/       uploaded documents, logs, sessions, cache
  backups/              database backups
  cron.php              run every minute by DirectAdmin
public_html/
  index.php             front door: loads the active version
  recovery.php          emergency recovery page
```

The site is served from `https://isoadmin.co.za/` without `/public`. Old `/public/...` links redirect.

## Apply (one time)

This release can't be uploaded as changed files. Use the setup file from the v3.0.0 GitHub release, as described in `docs/updates.md` under "One time only: moving to v3.0.0". After that, every update is one click on the Updates page.

## Also changed

- `/install` no longer fails on MySQL with "There is no active transaction". MySQL commits on every table change, so the steps no longer run in one `DB::transaction`. The old `/updates/vX-Y-Z` pages still use transactions and are unchanged.
- `composer.json`: fixed the `@php` script lines, and removed the `cache-dir` that pointed into the live `public_html`.
- The footer shows the running version.
- The tests run on in-memory SQLite.
- The one-off `public/` hotfix scripts are left out of release zips.

## Replaces

This replaces the earlier Update Manager in KeeSBeeS/ISO-Command-Centre#2 and the Package Manager branch.
