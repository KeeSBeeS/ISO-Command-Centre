# Changelog

## v2.6.14

Employees page sorting update.

- Added clickable sort headings to the Employees page.
- Employee heading sorts by employee name.
- Department heading sorts by first assigned department name.
- Role heading sorts by first assigned role name.
- Sort direction toggles between ascending and descending.
- Search, status filters and pagination preserve the selected sort order.

## v2.6.13

Time Attendance, employee document profile restore and PC-first layout update.

- Attendance office hours now default to 06:00 to 15:00.
- Valid check-in window now defaults to 05:00 to 10:00.
- Valid checkout window now defaults to 10:00 to 16:00.
- Attendance calculations now use the earliest valid check-in and latest valid checkout.
- Late minutes are calculated from 06:00 and early-leave minutes from 15:00.
- Attendance employee summary headings are sortable.
- Employee profile documents are shown again with document counts and expiry attention.
- Layout shell widened for PC-first use while retaining mobile responsiveness.
- Changelog format extended for easier rollback/reference.

## v2.6.12

Time Attendance exception reporting update.

- Added 20th-to-20th default attendance reporting period.
- Added late-coming and early-leave exception dashboard.
- Added per-employee period totals for late days, late minutes, early-leave days and early-leave minutes.
- Added employee profile attendance exception summary.
- Added email option for the detailed attendance exception report.
- Added Core Attendance settings for late cut-off, expected checkout time, report period day and default report recipient.
- Added permissions `attendance.reports.view` and `attendance.reports.email`.

## v2.6.11

Baseline loaded into GitHub from the ISO Admin Command Framework v2.6.11 package.

Main focus:

- Restored attendance CSV import support.
- Added support for the newer daily attendance CSV format.
- Preserved older event-log attendance CSV format support.
- Repaired employee attendance display.
- Re-synced System Administrator permissions.
