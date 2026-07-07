# ISO Admin Command Framework v2.6.7

Repair update for the specific vehicle page.

## Fixed

- `resources/views/vehicles/show.blade.php` Blade parse error: `Unclosed '[' on line 259 does not match ')'`.
- Replaced multi-line `@json([...])` directives with pre-built Blade variables and safe `json_encode(...)` output.
- Applied the same safe JSON output pattern to the main vehicles fleet map page.
- Updated footer fallback version to v2.6.7.
- Added `/updates/v2-6-7` to update the stored platform version and re-sync System Administrator permissions.

## Run after upload

```text
https://isoadmin.co.za/updates/v2-6-7
```

No database schema changes are included.
