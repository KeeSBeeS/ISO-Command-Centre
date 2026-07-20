# ISO Admin Command Framework v2.8.6

## Employee Time & Attendance Date-Range Register

This is a changed-files-only update for the existing Laravel 11 ISO Command Centre platform. It builds on v2.8.5 and does not replace unrelated attendance, employee, dashboard, compliance, vehicle, leave, role or permission functionality.

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

## No command line required

No artisan command or database migration is required. The register is built from the attendance data that is already imported.

## Changed files

- `app/Http/Controllers/EmployeeController.php`
- `resources/views/employees/show.blade.php`
- `VERSION`

## Apply

Upload only the changed/new files.

Then open:

`/employees/{employee}`

Scroll to **Time & Attendance** and pick a Date From / Date To to view that period.
