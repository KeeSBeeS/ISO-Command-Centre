# ISO Admin Command Framework v2.8.5

## Employee Attendance History

This is a changed-files-only update for the existing Laravel 11 ISO Command Centre platform. It builds on v2.8.4 and does not replace unrelated attendance, employee, dashboard, compliance, vehicle, leave, role or permission functionality.

## Issue fixed

The employee attendance page was still behaving like a narrow detail page and could appear to show only one day's entry. The uploaded Original Records Report CSV contains a proper history from February 2026 through July 2026, so the employee attendance page now defaults to the full employee history.

## Added / Changed

- `/attendance/{employeeCode}` now defaults to the employee's full raw attendance history.
- Date filters are no longer auto-filled by default on the employee history page.
- Added a clear Attendance History Range card.
- Added a new Punch History by Date section.
- Punch History by Date is built directly from raw imported punch records.
- The raw punch history shows:
  - Date
  - First punch
  - Last punch
  - Total imported records for that date
- The full raw imported records table remains available below the history.
- Search now checks more CSV fields:
  - Person ID
  - Department
  - Attendance Check Point
  - Custom Name
  - Data Source
  - Handling Type
  - Temperature
  - Abnormal
- Employee code matching now handles CSV Person IDs with leading zeros, for example `0001` and `1`.
- The importer now also checks leading-zero and stripped-zero versions of Person ID when matching employees.

## Important apply note

After uploading this update, re-import the Original Records Report CSV if the employee page still has only one day of data. The page can only build a history from raw records that exist in the database.

## No command line required

No artisan command is required.

## Changed files

- `app/Http/Controllers/AttendanceController.php`
- `app/Services/AttendanceCsvImporter.php`
- `resources/views/attendance/show.blade.php`
- `VERSION`

## Apply

Upload only the changed/new files.

Then open:

`/attendance/{employeeCode}`

The page should show the full history by default. Use date filters only when you want to narrow the range.
