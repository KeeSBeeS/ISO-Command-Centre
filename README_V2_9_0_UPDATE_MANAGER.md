# ISO Admin Command Framework v2.9.0

## Update Manager (ZIP Upload and GitHub Updates)

Adds a complete Update Manager under the Admin menu, for System Administrators only.

### What it does

- **Update via ZIP upload**: upload a deployment package (full or partial changed-files package) and apply it from the web interface.
- **Update via GitHub**: save a repository (owner/repository), branch and optional access token, then download the latest branch ZIP straight from GitHub and apply it.
- **Automatic code backups**: a backup ZIP of the platform code (excluding vendor/, storage/ and .git) is created before every apply while the backup setting is enabled. Backups can be downloaded, deleted or re-applied.
- **Safe apply**: `.env`, `storage/` (uploads, logs, backups), `node_modules/` and the local database file are never overwritten. ZIP entries with unsafe paths are rejected. Compiled view/config/route caches are cleared after applying.
- **Package validation**: only ZIPs that contain recognisable platform folders (app/, resources/, routes/, ...) at the package root — or inside one wrapper folder, as GitHub archives are — can be applied.

### Where to find it

- Admin menu → **Update Manager** (`/settings/updates`)
- Settings are stored in System Settings under the **Update Manager** group.

### New permissions

- `platform_updates.view` — View Update Manager (System Administrator only)
- `platform_updates.manage` — Manage Platform Updates (System Administrator only)

### GitHub token

For private repositories, create a fine-grained personal access token with read-only **Contents** access to the repository and save it in the Update Manager settings. The token is stored server-side only and is never displayed again.

### After applying an update package

If the applied release includes a database change, run its `/updates/...` route to finish the update, as with every previous release.

### Install

Upload the package and run:

`/updates/v2-9-0`
