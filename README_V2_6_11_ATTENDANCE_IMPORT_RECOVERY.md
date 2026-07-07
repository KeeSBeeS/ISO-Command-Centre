# ISO Admin Command Framework v2.6.11

Attendance import recovery update.

## What changed

- Restores the morning attendance CSV workflow.
- Supports the old event-log CSV format: `Name`, `Time`, `Attendance Status`.
- Supports the daily summary CSV format: `Person ID`, `Name`, `Date`, `Check-In`, `Check-out`, `Attendance Status`.
- Imports absence / no-punch days so employees still show on the attendance page.
- Keeps the 09:00 clock-in rule.
- Keeps earliest valid check-in and latest different checkout.
- Defaults the attendance page to the latest imported attendance date instead of today's date when no filter is selected.
- Rebuilds existing raw attendance records.
- Re-syncs System Administrator permissions.
- Updates platform version to 2.6.11.

## Apply

Upload the package and run:

`https://isoadmin.co.za/updates/v2-6-11`
