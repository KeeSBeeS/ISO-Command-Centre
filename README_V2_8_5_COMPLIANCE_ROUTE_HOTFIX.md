# ISO Admin Command Framework v2.8.5 - Employee Compliance Route Hotfix

## Problem

After logging in, the dashboard fails with:

```
Symfony\Component\Routing\Exception\RouteNotFoundException
Route [employee_compliance.index] not defined.
```

The dashboard compliance widget (added in the v2.8.0 Employee Compliance update)
links to the named route `employee_compliance.index`. If `routes/web.php` on the
server no longer contains the employee compliance routes - for example because a
later changed-files-only update replaced `routes/web.php` with a version built
without the compliance module - the dashboard can no longer render and login is
blocked for every user.

## Fix

This hotfix registers a safety-net route from `app/Providers/AppServiceProvider.php`,
after all route files have loaded:

- If `employee_compliance.index` is already defined by `routes/web.php`, the
  hotfix does nothing.
- If the route is missing, it is registered at `/employee-compliance` with the
  same middleware stack as other authenticated pages (auth, forced password
  change, permission check `employee_compliance.view`).
- When the route is hit, the v2.8.0 `EmployeeComplianceController@index` is used
  when its file is present on the server, so the full compliance overview keeps
  working. If the controller file is not present, a simple notice page is shown
  instead of an error.
- The safety net also works when a stale route cache
  (`bootstrap/cache/routes-v7.php`) is active on the server.

Login and the dashboard work again in all cases.

## Changed / added files

```
app/Providers/AppServiceProvider.php                             (changed)
app/Http/Controllers/EmployeeComplianceFallbackController.php    (new)
resources/views/employee_compliance/fallback.blade.php           (new)
README_V2_8_5_COMPLIANCE_ROUTE_HOTFIX.md                         (new)
```

No database changes. No artisan command is required.

## Apply

1. Upload the files above into the same folder structure on the server,
   overwriting `app/Providers/AppServiceProvider.php`. Do not upload or replace
   any other files.
2. Recommended: if the file `bootstrap/cache/routes-v7.php` exists on the
   server, delete it (it is a stale route cache and is rebuilt automatically).
   If you have shell access you can run `php artisan route:clear` instead. The
   hotfix works even if you skip this step, but a stale cache can hide other
   routes from later updates.
3. Log in again. The dashboard should load, and the compliance widget "Open"
   link should work.

## Note

If, after login works again, other pages report a different missing route
beginning with `employee_compliance.` or a policy-related route name, the
server's `routes/web.php` lost more of the v2.8.0 compliance routes than the
dashboard link. In that case re-apply the v2.8.0 Employee Compliance update
package (its `routes/web.php` and module files), then re-apply the latest
update on top.
