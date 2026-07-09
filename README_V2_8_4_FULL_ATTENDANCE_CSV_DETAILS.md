# ISO Admin Command Framework v2.8.4

## Full Attendance CSV Detail Capture

This is a changed-files-only update for the existing Laravel 11 ISO Command Centre platform. It builds on v2.8.3 and does not replace unrelated attendance, employee, dashboard, compliance, vehicle, leave, role or permission functionality.

## CSV reviewed

The uploaded Original Records Report CSV contains these columns:

- Person ID
- Name
- Department
- Time
- Attendance Status
- Attendance Check Point
- Custom Name
- Data Source
- Handling Type
- Temperature
- Abnormal

The previous importer only stored and displayed a limited subset of this data. This update captures and displays the full imported row detail.

## Added / Changed

- Attendance event-log imports now read Person ID / employee code from the CSV.
- Employees are now matched by Person ID / employee code first, then attendance name, then normal name.
- Raw attendance records now support extra CSV detail fields:
  - Person ID
  - Department
  - Attendance Check Point
  - Custom Name
  - Data Source
  - Handling Type
  - Temperature
  - Abnormal
  - Raw Payload
- The importer automatically adds these extra raw-record columns when the next CSV is imported.
- Re-importing the same CSV updates existing raw records with the new detail fields instead of duplicating rows.
- The employee attendance page now displays the full CSV detail in the All Imported Raw Records table.
- The employee page includes a notice explaining that older imports must be re-imported to backfill the extra CSV fields.

## Important apply note

After uploading this update, re-import the Original Records Report CSV. This is required because the older import did not store the extra CSV columns in the database.

## No command line required

No artisan command is required. The importer performs the raw-record column update automatically on the next import.

## Changed files

- `app/Models/AttendanceRawRecord.php`
- `app/Services/AttendanceCsvImporter.php`
- `resources/views/attendance/show.blade.php`
- `VERSION`

## Apply

Upload only the changed/new files.

Then re-import the CSV from:

`/attendance/manual-upload`

or:

`/attendance/upload`

Then open:

`/attendance/{employeeCode}`
