# ISO Admin Command Framework v2.6.2

## Update focus

This update restores and hardens attendance rules and public holiday handling.

## Added

- Clock-in cut-off rule: 09:00.
- If more than one clock-in exists before 09:00, only the earliest is used as the check-in.
- If no clock-in exists before 09:00, the earliest available timestamp becomes the check-in and the day is marked late.
- Latest different timestamp is used as checkout.
- Clock-in and checkout cannot be the same. If only one timestamp exists, checkout is left blank and the day is flagged.
- Late attendance tracking:
  - Company-wide on the Time Attendance screen.
  - Per employee on the employee profile.
- South African public holidays for 2026 and 2027.
- Public holidays are marked on the calendar.
- Public holidays are treated as company-closed days for time attendance.
- Attendance records imported on public holidays are retained for audit but excluded from late tracking.
- Footer version updated to v2.6.2.
- System Administrator permissions are re-synced to all permissions during the update.

## New permissions

```text
attendance.late.view
public_holidays.view
```

## Run update

After uploading the files, log in as System Administrator or a user with `settings.manage` and run:

```text
https://isoadmin.co.za/updates/v2-6-2
```
