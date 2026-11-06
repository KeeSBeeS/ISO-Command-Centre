# Updating ISO Command Centre

From v3.0.0, ISO Command Centre updates itself from **System Settings → Updates** (`/settings/updates`). It downloads new versions from GitHub, backs up the database first, and switches back by itself if anything goes wrong. No more uploading changed files or clicking `/updates/vX-Y-Z` pages.

## One time only: moving to v3.0.0

Sites on v2.8.x or older keep Laravel directly in `public_html` and are opened through `/public`. v3.0.0 moves the code and settings into a private folder outside the web folder. This one upgrade uses a setup file. Your data, accounts and uploaded documents are kept.

1. **Back up first.** In DirectAdmin, back up the site files and the database.
2. From the **latest v3.x release on GitHub**, download `iso-setup.php`, `iso-command-centre-vX.Y.Z.zip` and `iso-command-centre-vX.Y.Z.zip.sha256`.
3. In DirectAdmin's **File Manager**, upload all three into `domains/isoadmin.co.za/public_html`, next to the existing files. **Don't extract the zip.**
4. Open `https://isoadmin.co.za/iso-setup.php`. It says which version is installed. Leave the suggested private folder (for example `/home/<user>/iso-command-centre`) unless you have a reason to change it, and click **Move and upgrade**.
5. Click **Log in and finish the update** and log in as a **System Administrator**. The database is backed up, the database changes are applied and the site checks itself. If anything is wrong, it switches back to the old version by itself.

You land on the new **Updates** page.

What the move does:

- The old version becomes `iso-command-centre/releases/v2.8.5/`. The safety nets and the recovery page can still switch back to it.
- `.env` is copied, and uploaded documents (`storage/app`) are moved, to `iso-command-centre/shared/`. Every version uses them from there. The old version's `storage/app` is a link to the same folder.
- The site is now at `https://isoadmin.co.za/` without `/public`. Old `/public/...` links redirect.
- Anything in `public_html` that isn't part of ISO Command Centre (such as `cgi-bin`) is left where it is. The setup page lists it.

### After the move: update your cron jobs

1. **Add** the new every-minute cron job shown on the Updates page, under **Background tasks**:

   ```text
   /usr/local/bin/php /home/<user>/iso-command-centre/cron.php >/dev/null 2>&1
   ```

   It checks for updates every hour, and undoes an update that was never finished.
2. **Change** any existing cron URLs that contain `/public/` to the same address without it, for example `https://isoadmin.co.za/attendance-email-import/<key>`. `curl` doesn't follow the redirect.

## First visit to the Updates page

1. **Save the recovery key** shown at the top. It's shown only once. Keep it in your password manager.
2. **Connect GitHub.** The repository is private, so the site needs a read-only token. The page has the steps:
   - On GitHub, go to **Settings → Developer settings → Personal access tokens → Fine-grained tokens → Generate new token**.
   - **Name:** ISO Command Centre updates. **Expiry:** up to a year. Put a reminder in your calendar to renew it.
   - **Repository access:** Only select repositories → `KeeSBeeS/ISO-Command-Centre`.
   - **Permissions → Repository permissions → Contents:** Read-only.
   - Click **Generate token**, copy it, paste it on the Updates page and click **Save token**.

## Who can update

System Administrators always can. Other people need the **Manage Platform Updates** (`platform_updates.manage`) permission, under Permissions → Roles. Directors get it on fresh installs, because they get every permission.

## Installing an update

The site checks GitHub every hour. When there's a new version, people who can update see a notice at the top of every page. Open **Updates** and click **Install**:

- The database is backed up first.
- The site is unavailable for a few seconds while it switches. You may briefly see "Finishing the update" or "Service Unavailable". Both reload by themselves: the hosting can take up to a minute to start serving the new version. Just wait. If you closed the page, opening **Updates** again finishes the update.
- If the new version has a problem, the site switches back by itself and shows you why. Nothing is lost.

## Switching back (rollback)

Under **Installed versions**:

- **Switch, with database:** go back to that version, with the database exactly as that version left it. Anything changed since then is lost.
- **Switch, code only:** go back to that version but keep the database as it is.

The last 3 versions and the last 5 backups are kept. v2.8.5 and older have no Update Manager, so you can't switch to them from this page (you couldn't update again afterwards). The recovery page can, in an emergency.

## If the site won't open at all

Go to `https://isoadmin.co.za/recovery.php` and enter your recovery key. Choose a version that worked and, if needed, tick **restore the latest database backup**.

After 5 wrong keys, the page locks for 15 minutes.

Lost the key? On the Updates page, click **Create a new recovery key**. The old one stops working.

## Publishing a new version (for whoever builds ISO Command Centre)

1. Bump `VERSION` (for example `3.0.1`), add the `CHANGELOG.md` entry and the release note, and merge to `main`.
2. On GitHub, open **Actions → Release → Run workflow** on `main` and enter the version, for example `v3.0.1`. Alternatively, push the tag `v3.0.1`.
3. The workflow runs the tests, builds `iso-command-centre-v3.0.1.zip` (with `vendor/` included, because the hosting has no Composer) and publishes the GitHub release. Within an hour the site shows the update.
