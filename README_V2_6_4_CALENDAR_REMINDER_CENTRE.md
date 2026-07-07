# ISO Admin Command Framework v2.6.4

## Purpose

This update restores the calendar as a central operational reminder calendar rather than a leave-only calendar.

## Changes

- Calendar week now starts on Sunday.
- Calendar now shows leave, public holidays, attendance exceptions, employee document reminders, vehicle document reminders, vehicle service reminders and vehicle tracking sync reminders.
- Public holidays remain marked as company-closed days.
- ODO-based service reminders and stale tracking sync reminders are shown in the Operational Reminder Centre and on today's calendar cell when viewing the current month.
- Adds `calendar.reminders.view` permission for the central reminder centre.
- Re-syncs System Administrator role to all permissions.
- Footer version updated to v2.6.4.

## Install

Upload the package files over the existing Laravel installation and run:

https://isoadmin.co.za/updates/v2-6-4

Then check:

https://isoadmin.co.za/calendar
