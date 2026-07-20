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

## Changed files (upload these to the SAME paths on your server)

- `app/Http/Controllers/EmployeeController.php`
- `resources/views/employees/show.blade.php`
- `VERSION`

The zip mirrors your project's folder layout. Extract it INTO your project root
so `app/...` and `resources/...` merge with your existing folders. Do **not**
upload a wrapper folder — the files must land at, for example,
`app/Http/Controllers/EmployeeController.php`, overwriting the existing file.

## IMPORTANT: clear caches after uploading

This update changes both a controller and a Blade view. If the page "looks
exactly the same" after uploading, the server is almost always serving a
**cached compiled view** or the old file via **OPcache**. Do one of the
following after uploading:

If you have SSH / command-line access, run from the project root:

```
php artisan view:clear
php artisan cache:clear
```

If you do NOT have command-line access (cPanel / FTP only):

1. Delete every file inside `storage/framework/views/` (keep the folder).
   These are auto-generated compiled Blade files and are rebuilt on the next
   page load.
2. If your host uses OPcache, restart PHP (in cPanel: "Restart PHP" / switch
   the PHP version off and on, or wait for the pool to recycle).

## How to confirm it worked

1. Open `/employees/{employee}` for someone who has attendance data.
2. You should now see a **Time & Attendance** card with **Date From / Date To**
   inputs and an **Apply Date Range** button — the old "Late Attendance
   Tracking" panel is gone.
3. The footer version reads `v2.9.3` only if your platform version setting is
   updated by a later update step; the feature itself does not depend on it.

## Verify the upload actually replaced the file

Open `resources/views/employees/show.blade.php` on the server and search for
`Time & Attendance`. If that text is missing, the file was not overwritten
(usually uploaded to the wrong folder) — re-upload to the correct path.
