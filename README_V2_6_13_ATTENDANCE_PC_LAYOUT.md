# ISO Admin Command Framework v2.6.13

## Attendance rules, employee documents and PC-first layout

This update is built on v2.6.12.

### Time Attendance

- Office hours now default to 06:00 to 15:00.
- Valid check-in window now defaults to 05:00 to 10:00.
- Valid checkout window now defaults to 10:00 to 16:00.
- The earliest valid check-in is always used as check-in.
- The latest valid checkout is always used as checkout.
- Late time is calculated from 06:00.
- Early-leave time is calculated against 15:00.
- Attendance employee summary headings are sortable.
- Existing raw attendance records are recalculated during the update.

### Employee profile documents

- Employee profile document list is restored and more visible.
- Document totals, active count and expiry-attention count are shown.
- Downloads remain permission-controlled.

### Layout

- Main layout is now PC-first with a wider content shell.
- Mobile support remains through responsive breakpoints.

### Install

Upload the package and run:

`/updates/v2-6-13`
