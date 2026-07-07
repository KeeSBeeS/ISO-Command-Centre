# ISO Admin Command Framework v2.6.5

## Update scope

This update adds calendar type filters and improves the Cartrack vehicle tracking API sync.

## Added

- Calendar item type filters.
- Users can select which calendar/reminder types are shown.
- Filter applies to the calendar grid and Operational Reminder Centre.
- Cartrack sync now requests `/rest/vehicles/status` first for current location/status/ODO data.
- Cartrack sync falls back to `/rest/vehicles` and merges vehicle-list details where possible.
- Improved nested Cartrack payload parsing.
- Improved local vehicle matching by:
  - Cartrack vehicle ID
  - registration number
  - Cartrack registration
  - external key
  - tracking device number
  - vehicle name/key
- Unmatched remote vehicles are shown in the sync result to help link local vehicles correctly.
- Last sync diagnostics are shown under Vehicle Tracking Settings.
- Footer/platform version updates to `2.6.5`.
- System Administrator permissions are re-synced to all permissions.

## Install

Upload these files over the existing Laravel installation, then run:

```text
https://isoadmin.co.za/updates/v2-6-5
```

Then check:

```text
https://isoadmin.co.za/calendar
https://isoadmin.co.za/settings/vehicle-tracking
https://isoadmin.co.za/vehicle-tracking
```

## Cartrack note

If the API returns remote vehicles but matched vehicles remain `0`, open the local vehicle profile and set the exact Cartrack Registration or Cartrack ID shown in the sync diagnostics.
