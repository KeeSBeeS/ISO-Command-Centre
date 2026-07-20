# ISO Admin Command Framework v2.8.5 - Missing Route Safety-Net Hotfix (Revision 2)

## Problem

After logging in, the dashboard fails with errors such as:

```
Symfony\Component\Routing\Exception\RouteNotFoundException
Route [employee_compliance.index] not defined.

Symfony\Component\Routing\Exception\RouteNotFoundException
Route [platform_updates.index] not defined.
```

Views installed by overlay update packages (the v2.8.0 Employee Compliance
dashboard widget, the v2.7.0 Update Manager navigation, ...) link to named
routes. If `routes/web.php` on the server no longer contains those routes -
for example because a later changed-files-only update replaced `routes/web.php`
with a version built without those modules - any page that renders such a link
throws `RouteNotFoundException`, and login is blocked for every user.

## Fix

This hotfix adds a safety net in `app/Providers/AppServiceProvider.php`,
applied after all route files have loaded:

- Fallback routes are registered for known module pages, currently
  `employee_compliance.index` (`/employee-compliance`) and
  `platform_updates.index` (`/platform-updates`). If `routes/web.php` already
  defines the route, the fallback is skipped and the real route wins.
- When a fallback route is opened, the real module controller is used if its
  file is present on the server, so the page keeps working fully. If the
  module files are not present, a friendly "page unavailable" notice is shown
  instead of an error.
- Any OTHER missing named route no longer throws while rendering: the link is
  generated to a generic notice page (`/module-unavailable`) instead. This
  means future missing-route problems show a notice when the link is clicked
  rather than blocking login.
- The safety net also works when a stale route cache
  (`bootstrap/cache/routes-v7.php`) is active on the server.

## Changed / added files

```
app/Providers/AppServiceProvider.php                             (changed)
app/Http/Controllers/ModuleFallbackController.php                (new)
app/Http/Controllers/EmployeeComplianceFallbackController.php    (new, kept for compatibility with revision 1)
resources/views/system/module_unavailable.blade.php              (new)
resources/views/employee_compliance/fallback.blade.php           (new)
README_V2_8_5_COMPLIANCE_ROUTE_HOTFIX.md                         (new)
```

No database changes. No artisan command is required.

## Apply

1. Upload the files above into the same folder structure on the server,
   overwriting `app/Providers/AppServiceProvider.php` (and the revision 1
   files if you applied that earlier). Do not delete or replace any other
   files.
2. Recommended: if the file `bootstrap/cache/routes-v7.php` exists on the
   server, delete it (it is a stale route cache and is rebuilt automatically).
   If you have shell access you can run `php artisan route:clear` instead. The
   hotfix works even if you skip this step, but a stale cache can hide other
   routes from later updates.
3. Log in again. The dashboard should load. The compliance widget "Open" link
   and the Update Manager link either open their real module page (when the
   module files are installed) or show a notice page.

## Root cause and long-term fix

The v2.7.0 Update Manager and v2.8.0 Employee Compliance module files were
distributed as changed-files-only packages but were never added to the source
repository. Every later update package built from the repository therefore
ships a `routes/web.php` without those modules' routes, and applying one
removes the routes from the server again. The long-term fix is to re-apply the
v2.7.0 and v2.8.0 packages' module files (including their route definitions)
and commit them to the repository so future packages include them.
