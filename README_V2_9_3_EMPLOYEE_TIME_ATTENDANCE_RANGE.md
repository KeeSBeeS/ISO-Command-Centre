# ISO Admin Command Framework v2.9.3

## Employee Time & Attendance Date-Range Register

This is a changed-files-only update for the existing Laravel 11 ISO Command Centre platform. It does not replace unrelated attendance, employee, dashboard, compliance, vehicle, leave, role or permission functionality.

## What changed

The employee profile page (`/employees/{employee}`) previously showed only a fixed "Late Attendance Tracking" panel with the last 30/90/all-time late counts and the ten most recent late clock-ins. There was no way to choose a period.

It now has a full **Time & Attendance** register with a selectable date range, modelled on the biometric "Start/End Work Time" and "Late" reports so the key figures can be read at a glance.

## Added / Changed

- Added a **date range filter** (Date From / Date To) directly on the employee profile time-attendance section.
- The range defaults to the most recent four weeks of available data and can be reset with one click.
- Added at-a-glance summary tiles for the selected range:
  - Days Present
  - Days Absent
  - Late Days
  - Total Late Time
  - Early Leave Days
  - Total Early Leave
  - Working Days
  - Public Holidays
- Added a **daily register table** for the selected range showing, per day:
  - Date and weekday
  - Shift / timetable window
  - Check In time
  - Check Out time
  - Late By (minutes/hours the clock-in was after the shift start)
  - Status (On time, Late, Absent or Public Holiday, plus early-leave note)
- The register lists every working day (Mon–Fri) in the range, so absent days are shown with `-` just like the printed reports. Weekends are hidden unless a clock-in was recorded on them.
- Late and early-leave figures are measured against the company start and close times configured in Core Settings (default `06:00`–`15:00`), matching the biometric "Late Come" figures.
- Public-holiday days are detected from the Public Holidays table and marked so they are not counted as late or absent.
- Kept the link through to the full raw attendance history (`Open Full History`).

## Permissions

The section is visible to users with `attendance.view` or `attendance.late.view`, unchanged from before.

## No database migration required

No database migration is required. The register is built from the attendance data that is already imported.

## Changed / new files (upload these to the SAME paths on your server)

- `app/Http/Controllers/EmployeeController.php` (changed — the feature)
- `resources/views/employees/show.blade.php` (changed — the feature)
- `app/Http/Controllers/UpdateController.php` (changed — adds the v2.9.3 apply step)
- `routes/web.php` (changed — adds the `/updates/v2-9-3` route)
- `resources/views/updates/v2_9_3.blade.php` (new — the Update Manager page)
- `VERSION`

The zip mirrors your project's folder layout. Extract it INTO your project root
so `app/...`, `routes/...` and `resources/...` merge with your existing folders.
Do **not** upload a wrapper folder — the files must land at, for example,
`app/Http/Controllers/EmployeeController.php`, overwriting the existing file.

## Apply with the Update Manager (recommended)

After the files are uploaded, open:

`/updates/v2-9-3`

and click **Apply v2.9.3**. This runs the same way as the other versioned
updates in this platform. Applying it will:

- Clear the compiled views and application cache (so the new employee page
  shows immediately instead of the old cached one).
- Update the stored platform version to `2.9.3`.
- Re-sync System Administrator permissions.

You need the `settings.manage` permission to open and apply the update.

## If the page still "looks exactly the same"

That symptom means the old file is still being served — either the new files
were uploaded to the wrong folder, or a compiled view / OPcache is stale.

1. Confirm the upload landed: open `resources/views/employees/show.blade.php`
   on the server and search for `Time & Attendance`. If that text is missing,
   the file was not overwritten — re-upload it to the correct path.
2. Apply `/updates/v2-9-3` (it clears the caches for you), or manually:
   - With SSH: `php artisan view:clear && php artisan cache:clear`
   - cPanel / FTP only: delete every file inside `storage/framework/views/`
     (keep the folder), then restart PHP / OPcache.

## How to confirm it worked

1. Open `/employees/{employee}` for someone who has attendance data.
2. You should now see a **Time & Attendance** card with **Date From / Date To**
   inputs and an **Apply Date Range** button — the old "Late Attendance
   Tracking" panel is gone.
3. The footer version reads `v2.9.3` after the Update Manager step above.
