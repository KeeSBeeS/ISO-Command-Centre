# ISO Admin Command Framework v3.0.1

## Fix: "503 Service Unavailable" while finishing the update

The database backup taken before an update loaded each table into memory at once. On a site with a large table (for example `attendance_raw_records`), shared hosting killed PHP, and the browser showed LiteSpeed's "503 Service Unavailable. The server is temporarily busy" instead of finishing the update.

The backup now streams rows straight into the backup file. Measured on a 200,000-row table, peak memory dropped from 109 MB to 18 MB, and stays the same whatever the table size.

## Changed files

- `app/Updates/DatabaseBackup.php`
- `VERSION`, `CHANGELOG.md`, `docs/updates.md`

## Apply

### If the move to v3.0.0 stopped with the 503

1. In DirectAdmin's **File Manager**, open the private folder chosen during setup, for example `/home/<user>/iso-command-centre`.
2. Edit `current.php` so it reads exactly:

   ```php
   <?php return 'v2.8.5';
   ```

3. Delete `update-pending.php`. The site now runs v2.8.5 again, at `https://isoadmin.co.za/`.
4. Upload `iso-setup.php`, `iso-command-centre-v3.0.1.zip` and `iso-command-centre-v3.0.1.zip.sha256` into `public_html`. Don't extract the zip.
5. Open `https://isoadmin.co.za/iso-setup.php`, click **Upgrade**, then **Log in and finish the update**.

### Otherwise

Install it from System Settings → Updates.
