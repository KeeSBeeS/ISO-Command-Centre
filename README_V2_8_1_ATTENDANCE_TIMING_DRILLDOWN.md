# ISO Admin Command Framework v2.8.1

## Time Attendance Settings and Employee Drill-Down

This is a changed-files-only update for the existing Laravel 11 ISO Command Centre platform. It builds on v2.8.0 and does not replace unrelated attendance, employee, dashboard, compliance, vehicle, leave, role or permission functionality.

## Added

- Core Settings now automatically includes an Attendance group with:
  - Company Start Time
  - Company Close Time
- The settings page renders these values as proper time inputs.
- Attendance dashboard now displays the configured company start and close times.
- Employee names on the attendance dashboard are clickable.
- Clicking an employee name opens that employee's attendance entries on the same attendance dashboard using the existing route.
- When an employee is selected, the dashboard shows:
  - Attendance days in the selected period
  - Late days
  - Total late minutes/hours
  - Early leave days
  - Total early-leave minutes/hours
- CSV import success messaging clarifies that matched raw punch records are retained for drill-down and audit.

## Notes

- No new database tables are required.
- No new route is required for the employee drill-down; it uses the existing attendance dashboard with an `employee_id` filter.
- Attendance start and close times are stored in `system_settings` and are seeded automatically when Core Settings is opened.
- Existing attendance records remain compatible.

## Changed files

- `app/Http/Controllers/CoreSettingController.php`
- `resources/views/settings/core/index.blade.php`
- `app/Http/Controllers/AttendanceController.php`
- `resources/views/attendance/index.blade.php`
- `VERSION`

## Apply

Upload only the changed/new files.

Then open:

`/settings/core`

Confirm or update the Attendance settings:

- Company Start Time
- Company Close Time

No artisan command is required.
