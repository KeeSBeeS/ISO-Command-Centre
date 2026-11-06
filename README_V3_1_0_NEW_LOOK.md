# ISO Admin Command Framework v3.1.0

## New look, part 1

The whole site switches to the new light design approved in the mock-ups. All features work as before: there are no database, permission or settings changes.

## What changes

- **Every page** uses the new design system (`public/css/iso.css`): a light, calm workspace for long days at a PC, with ISO green as the brand colour.
- **New sidebar** in three groups (Workspace, Operations, Admin) with line icons. It shows the same links as before, under the same permissions. On tablets and phones it becomes a slide-in menu.
- **New dashboard:**
  - "Import attendance" is the main button, next to "Attendance" and "Customise".
  - "Needs attention" gathers pending leave, expired and expiring documents, single clock-ins and vehicle reminders. It shows only what you have permission to see.
  - Your own widgets, order and sizes are kept, in the new style. Medium and large attendance widgets list today's clock-ins.
- **Dashboard and shortcut editors:** Move up / Move down buttons, so they work on touch screens.
- **Restyled pages:** login, change password, access denied, installer, "Finishing the update", calendar, vehicles, customers and settings.
- **Fixed:** System settings sorted its groups with MySQL-only SQL.

## Responsive

Designed for PC first, and checked at phone (390px), tablet (820px) and PC (1280px) width. Wide tables scroll inside their own box, and the page never scrolls sideways.

## Changed files

- `public/css/iso.css` (new), `resources/views/components/icon.blade.php` (new)
- `resources/views/layouts/app.blade.php`
- `resources/views/dashboard.blade.php`, `dashboard_edit.blade.php`, `dashboard_quick_actions.blade.php`
- `resources/views/auth/login.blade.php`, `auth/password.blade.php`, `errors/403.blade.php`, `install/index.blade.php`, `platform_updates/finishing.blade.php`
- `resources/views/calendar/index.blade.php`, `vehicles/index.blade.php`, `vehicles/show.blade.php`, `vehicles/fuel_select.blade.php`, `clients/index.blade.php`, `clients/show.blade.php`, `settings/index.blade.php`
- `app/Http/Controllers/DashboardController.php` (today's clock-ins), `app/Http/Controllers/CoreSettingController.php` (portable sorting)
- `tests/Feature/EveryPageTest.php` (new)
- `VERSION`, `CHANGELOG.md`, `CLAUDE.md`

## Apply

Open **System Settings → Updates** and click **Install v3.1.0**. If you don't like something, **Switch, code only** to v3.0.1 puts the old look back instantly. Nothing in the database changes.

## Next

v3.1.x refines one module per release (employees, then attendance), replacing the remaining emoji page icons with line icons.
