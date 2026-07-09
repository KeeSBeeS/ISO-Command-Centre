# ISO Admin Command Framework v2.8.2

## Core Settings Cleanup and Attendance Office Times

This is a changed-files-only update for the existing Laravel 11 ISO Command Centre platform. It builds on v2.8.1 and does not replace unrelated attendance, employee, dashboard, compliance, vehicle, leave, role or permission functionality.

## Added / Changed

- Core Settings now loads only true core settings.
- Technical/API settings are no longer shown on `/settings/core`.
- Core Settings are now grouped into expandable/collapsible panels to reduce scrolling.
- The first settings group opens automatically; all other groups are collapsed by default.
- Attendance settings labels changed to:
  - Office Start Time
  - Office Close Time
- Attendance default office times changed to:
  - Office Start Time: `06:00`
  - Office Close Time: `15:00`
- Existing v2.8.1 default values are corrected automatically:
  - `08:00` becomes `06:00`
  - `17:00` becomes `15:00`
- Attendance dashboard fallback timing now also uses `06:00` and `15:00`.

## Notes

- No new database tables are required.
- No artisan command is required.
- Hidden settings are not deleted from the database; they are simply removed from the Core Settings screen if they are not marked as core.
- Google API and Vehicle Tracking API settings remain excluded from Core Settings and should stay on their dedicated settings pages.

## Changed files

- `app/Http/Controllers/CoreSettingController.php`
- `resources/views/settings/core/index.blade.php`
- `app/Http/Controllers/AttendanceController.php`
- `VERSION`

## Apply

Upload only the changed/new files.

Then open:

`/settings/core`

Confirm the Attendance group shows:

- Office Start Time: `06:00`
- Office Close Time: `15:00`
