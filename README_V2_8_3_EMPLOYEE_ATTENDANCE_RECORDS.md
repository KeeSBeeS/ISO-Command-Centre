# ISO Admin Command Framework v2.8.3

## Employee Attendance Imported Records Page

This is a changed-files-only update for the existing Laravel 11 ISO Command Centre platform. It builds on v2.8.2 and does not replace unrelated attendance, employee, dashboard, compliance, vehicle, leave, role or permission functionality.

## Added / Changed

- Completely redesigned the dynamic employee attendance page at `/attendance/{employeeCode}`.
- The old single-day attendance detail page is now replaced with an employee-level imported-record view.
- Attendance dashboard employee names now link directly to `/attendance/{employeeCode}`.
- Attendance dashboard View buttons now open the employee imported-record page.
- If an employee has no employee code, the route falls back to the user ID.
- The employee attendance page now shows:
  - Employee code, attendance name and email.
  - Office start and close time.
  - Date, status and search filters.
  - Imported raw record count.
  - Days with imported records.
  - Import batch count.
  - Late-day and early-leave summaries.
  - First and latest imported punch timeline.
  - Status breakdown.
  - Latest import sources.
  - Daily rebuilt attendance summaries.
  - All imported raw CSV punch records for the employee.
- Raw imported records are paginated at 100 records per page.
- Daily summaries are paginated separately at 31 records per page.

## Notes

- No new database tables are required.
- No artisan command is required.
- The route remains `/attendance/{employeeCode}` using the existing `attendance.show` route name.
- Existing static attendance routes such as `/attendance/upload`, `/attendance/manual-upload` and `/attendance/imports` remain before the dynamic route and are not affected.

## Changed files

- `app/Http/Controllers/AttendanceController.php`
- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/show.blade.php`
- `VERSION`

## Apply

Upload only the changed/new files.

Then open an employee attendance page using:

`/attendance/{employeeCode}`

Example:

`/attendance/EMP001`
