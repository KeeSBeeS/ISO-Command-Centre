# ISO Admin Command Framework v2.5.2

Visual refresh update.

## Upload
Upload the package contents over the existing Laravel installation and replace files when prompted.

## Run
After uploading, log in as a System Administrator or Director with `settings.manage` and open:

```text
https://isoadmin.co.za/updates/v2-5-2
```

Click **Apply v2.5.2**.

## What changed

- Re-added navigation icons.
- Improved mobile sidebar and backdrop behaviour.
- Refreshed top bar with page icons and user avatar initials.
- Improved shared card, table, button, status pill, form and alert styling.
- Updated dashboard widget icons and visual spacing.
- Updated dashboard editor with clearer drag handles and widget icons.

## Database

No database changes are required for v2.5.2.

## Changed files

```text
resources/views/layouts/app.blade.php
resources/views/dashboard.blade.php
resources/views/dashboard_edit.blade.php
resources/views/updates/v2_5_2.blade.php
routes/web.php
app/Http/Controllers/UpdateController.php
```
