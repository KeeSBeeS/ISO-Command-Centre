# ISO Admin Command Framework v2.6.12

## Time Attendance reporting update

This update restructures Time Attendance into a proper attendance exception report.

### Added

- 20th-to-20th default attendance reporting period.
- Dashboard view for who came late and who left early.
- Employee-level period summaries for late days, total late minutes, early-leave days and total early-leave minutes.
- Employee profile attendance summary for the current 20th-to-20th period.
- Early-leave detection using a configurable expected checkout time.
- Email option for detailed attendance exception report.
- CSV export now includes early-leave fields.
- Core Attendance settings for cut-off times and default report recipient.

### Install

Upload the deployment package and run `/updates/v2-6-12`.
