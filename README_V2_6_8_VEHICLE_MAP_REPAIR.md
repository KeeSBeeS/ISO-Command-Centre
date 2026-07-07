# ISO Admin Command Framework v2.6.8

Vehicle map Blade repair update.

## Fixes

- Fixes `resources/views/vehicles/index.blade.php` parse error: `syntax error, unexpected end of file`.
- Fixes `resources/views/vehicles/show.blade.php` error: `Undefined variable $isoVehicleMapConfig`.
- Moves Google Maps client config into `VehicleController` so both index and show views receive map config from the controller.
- Removes fragile Blade-side PHP array generation from the vehicle map scripts.
- Escapes Blade CSS media directives correctly.
- Adds singular `/vehicle` and `/vehicle/{vehicle}` aliases for compatibility with the URLs reported by the live site.
- Updates footer fallback version to `v2.6.8`.
- Re-syncs System Administrator permissions when the update is applied.

## Apply

Upload the package over the existing Laravel files, then run:

```text
https://isoadmin.co.za/updates/v2-6-8
```

Then test:

```text
https://isoadmin.co.za/vehicles
https://isoadmin.co.za/vehicles/1
https://isoadmin.co.za/vehicle
https://isoadmin.co.za/vehicle/1
```
