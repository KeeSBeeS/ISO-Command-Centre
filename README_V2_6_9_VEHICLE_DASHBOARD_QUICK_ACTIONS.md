# ISO Admin Command Framework v2.6.9

## Update purpose

This update improves vehicle operations and dashboard shortcut control.

## Added / changed

- `/vehicles` is now a fleet dashboard instead of only a vehicle list.
- Vehicle dashboard includes all-vehicle fuel tracking:
  - fuel-ups this month
  - litres this month
  - cost this month
  - average KM/L
  - top vehicles by fuel spend
  - recent fuel-ups across the fleet
- Add Fuel now opens a vehicle-selection page first.
- New route: `/vehicles/fuel/create`.
- Homepage Quick Actions are editable by users with `dashboard.quick_actions.manage`.
- Users can reorder and show/hide their own Quick Action shortcuts.
- System Administrator permissions are re-synced.
- Footer version fallback updated to v2.6.9.

## New permission

- `dashboard.quick_actions.manage`

Default role assignment:

- System Administrator
- Director
- Manager

Employees can receive it manually through the permission matrix if required.

## Database changes

Adds table:

- `quick_action_preferences`

This update is idempotent and safe to re-run.

## Apply update

Upload the package and run:

`https://isoadmin.co.za/updates/v2-6-9`
