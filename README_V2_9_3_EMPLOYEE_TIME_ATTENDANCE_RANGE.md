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

## IMPORTANT: this is an ADDITIVE install — do NOT overwrite whole files

This package is designed to add the feature WITHOUT overwriting any existing
file that your live site may have a newer version of. Overwriting whole files
such as `routes/web.php` from an older package is what removes routes like
`employee_compliance.index` and takes the site down. So:

- **Copy in the NEW files** (they overwrite nothing):
  - `app/Support/EmployeeAttendanceOverview.php`
  - `resources/views/employees/_time_attendance.blade.php`
  - `resources/views/updates/v2_9_3.blade.php`
- **Make three small ADDITIVE edits** to existing files (see `SNIPPETS.txt`).

Do NOT upload the zip's copies of `routes/web.php`, `UpdateController.php`,
`EmployeeController.php` or `show.blade.php` over your live files.

### Step 1 — Copy the three new files

Upload the three new files above to the same paths on your server. They are
brand new, so nothing is overwritten.

### Step 2 — Add one line to the employee profile view

In `resources/views/employees/show.blade.php`, add this line where you want the
section to appear (a good spot is just after the top profile / departments /
roles block):

```blade
@include('employees._time_attendance')
```

That is the only change the feature itself needs — no controller change. The
section computes its own data. (Optional: you may delete your old "Late
Attendance Tracking" block from the same file so it is not shown twice.)

### Step 3 (optional but recommended) — wire up the Update Manager page

So you can apply future cache-clears / version bumps from the UI, add the two
route lines and the controller methods shown in `SNIPPETS.txt` to your live
`routes/web.php` and `app/Http/Controllers/UpdateController.php`. These are
ADD-ONLY — they insert new lines and remove nothing. Then open `/updates/v2-9-3`
and click **Apply v2.9.3**.

## After installing — clear the view cache once

Because a Blade view changed, clear the compiled views so the new section shows:

- With SSH: `php artisan view:clear`
- cPanel / FTP only: delete every file inside `storage/framework/views/`
  (keep the folder), then reload the page.

(Applying `/updates/v2-9-3` does this for you.)

## How to confirm it worked

1. Open `/employees/{employee}` for someone who has attendance data.
2. You should see a **Time & Attendance** card with **Date From / Date To**
   inputs and an **Apply Date Range** button.
3. Every other page (including Compliance Overview) still works, because no
   existing file was overwritten.
