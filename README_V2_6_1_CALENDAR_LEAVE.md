# ISO Admin Command Framework v2.6.1

This update restores Calendar and Leave into the platform and hardens System Administrator access.

## Upload

Upload the package contents over the existing Laravel installation.

## Run update

Open:

https://isoadmin.co.za/updates/v2-6-1

Then click **Run v2.6.1 Update**.

## Added / restored

- Calendar module
- Leave request module
- Leave approvals for managers/directors/system admins
- Leave menu item
- Calendar menu item
- Dashboard leave/calendar widget
- Version number in footer
- System Administrator permission hardening

## New permissions

- calendar.view
- leave.view
- leave.create
- leave.manage

## Access defaults

System Administrator:
- Always has full access to every permission.

Director and Manager:
- calendar.view
- leave.view
- leave.create
- leave.manage
- leave_types.view

Employee:
- calendar.view
- leave.view
- leave.create

## Notes

The update is idempotent and safe to re-run. It does not remove existing data or functionality.
