# ISO Admin Command Framework v2.5.3

## Core Settings Update

This update adds the missing **Core Settings** area under **System Settings**.

## Access Control

Core Settings is restricted to the **System Administrator** role only.

Even if another user is manually given the `core_settings.view` or `core_settings.manage` permission, the controller still blocks access unless the logged-in user has the `system-administrator` role.

## New Permissions

```text
core_settings.view
core_settings.manage
```

These permissions are assigned only to the System Administrator role during the updater.
They are removed from Director, Manager and Employee roles if they were previously attached.

## New Route

```text
/settings/core
```

## Update URL

Run this after uploading the files:

```text
https://isoadmin.co.za/updates/v2-5-3
```

## New Files

```text
app/Http/Controllers/CoreSettingController.php
app/Models/SystemSetting.php
resources/views/settings/core/index.blade.php
resources/views/updates/v2_5_3.blade.php
```

## Updated Files

```text
app/Http/Controllers/UpdateController.php
resources/views/layouts/app.blade.php
routes/web.php
```
