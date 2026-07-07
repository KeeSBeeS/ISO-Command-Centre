# ISO Admin v2.2.1 Route Fix

This repair fixes the missing `/updates/v2-2-1` route.

## Upload

Upload the package contents over the current Laravel installation.

## If `/updates/v2-2-1` still returns 404

Your Laravel route cache is probably still holding the old route list. Open:

```text
https://isoadmin.co.za/isoadmin-clear-cache-v221.php?key=iso-v221-route-repair
```

Then delete:

```text
public/isoadmin-clear-cache-v221.php
```

Then open:

```text
https://isoadmin.co.za/updates/v2-2-1
```

## Notes

- Version 2.2 already ran successfully, so this update only repairs the v2.2.1 web updater route and cache clearing path.
- The v2.2.1 controller/view already existed in the previous package; the route was missing.
