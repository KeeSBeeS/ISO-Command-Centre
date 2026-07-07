# ISO Admin Command Framework v2.6.6

This update restores Google API settings and adds Google Maps tracking views to the fleet module.

## Added

- Google API settings under System Settings.
- System Administrator-only Google API permissions.
- Fleet map on the main Vehicles page.
- Vehicle-specific route/history map on each vehicle profile.
- Route polyline from stored tracking snapshots.
- Fallback to standard Google markers when no Google Map ID is configured.
- Longitude parser now also accepts `LONG` / `long` fields from tracking API responses.
- Footer/platform version updated to `2.6.6`.
- System Administrator permissions re-synced to all permissions.

## Run update

After uploading the package, log in as System Administrator and open:

```text
https://isoadmin.co.za/updates/v2-6-6
```

Then configure Google Maps:

```text
https://isoadmin.co.za/settings/google-api
```

## Google Cloud requirements

Enable Maps JavaScript API in Google Cloud and use a restricted browser API key. Restrict the key by HTTP referrer to the isoadmin.co.za domain.
