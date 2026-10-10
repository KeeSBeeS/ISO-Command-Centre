# ISO Admin Command Framework v2.9.0

## Email Signature Builder + Logo Manager

This is a changed-files-only update for the existing Laravel 11 ISO Command Centre platform. It builds on v2.8.5 and does not change any other module.

## Added / Changed

- **Admin → Signature Builder** (`/signature-builder`)
  - Live preview of the ISO-Reliability Partners email signature.
  - "Load from employee" drop-down fills name, job title, mobile and email from the employee record (all fields stay editable).
  - **Copy formatted signature** – paste straight into Outlook / Gmail signature settings.
  - **Download personalised HTML** – saves `IISO_Email_Signature_<Name>.html`.
- **Signature Builder → Manage Logos** (`/signature-builder/logos`)
  - Add a brand logo by uploading an image (PNG, JPG, GIF or WebP, max 2 MB) or by pasting a direct https:// image URL.
  - Set the link each logo opens when clicked.
  - Edit, replace or remove the image, show/hide a logo, move it up/down, or delete it.
  - Logos show four per row in the signature; extra logos start a new row automatically.
  - The eight existing brands (PPL, BioMax, Royal Purple, Bel-Ray, Calumet, Yateks, Filter Focus, Seal Saver) are pre-loaded with their website links. Until an image is added they show as text.

## Files in this package

- `app/Http/Controllers/SignatureBuilderController.php` (new)
- `app/Services/SignatureLogoStore.php` (new)
- `resources/views/signature_builder/index.blade.php` (new)
- `resources/views/signature_builder/logos.blade.php` (new)
- `public/uploads/signature-logos/.htaccess` (new – blocks scripts in the upload folder)
- `routes/web.php` (changed – signature builder routes added)
- `resources/views/layouts/app.blade.php` (changed – Admin menu link added)
- `VERSION`, `CHANGELOG.md`, `README_V2_9_0_SIGNATURE_BUILDER.md`

## Install (no console needed)

1. Back up `routes/web.php` and `resources/views/layouts/app.blade.php` on the server.
2. Upload the ZIP contents into the Laravel root folder (the folder that contains `app`, `public`, `routes`, `resources` …), keeping the folder structure. Overwrite when asked.
   - Hidden file: make sure `public/uploads/signature-logos/.htaccess` is uploaded. In cPanel File Manager, tick *Show Hidden Files (dotfiles)* to see it.
3. Folder permissions (cPanel → File Manager → right-click → Change Permissions):
   - `public/uploads/signature-logos` → **755**
   - `storage/app` → **755** (normally already writable – Laravel uses it)
4. Only if `/signature-builder` shows a 404 or the menu item does not appear: clear the route/view cache. Either
   - open `https://isoadmin.co.za/public/isoadmin-clear-cache-v221.php?key=iso-v221-route-repair` (if that file is still in `public/`), **or**
   - in File Manager delete `bootstrap/cache/routes-v7.php` / `bootstrap/cache/routes.php` and `bootstrap/cache/config.php` (if they exist) and the `.php` files inside `storage/framework/views/`.
5. Log in as System Administrator (or a user with the **settings.manage** permission) and open **Admin → Signature Builder**.
6. Click **Manage Logos** and upload the logo image for each brand.

No database update / updater page is required.

## Logo tips

- Best results: wide PNG, transparent or white background, about **260 × 84 px** (2× the 130 × 42 display box for sharp logos on high-resolution screens).
- SVG is not accepted – most email clients (including Outlook and Gmail) do not display SVG images.
- Uploaded logos are served from `https://isoadmin.co.za/public/uploads/signature-logos/…`. Signatures already pasted into email clients keep pointing at these files, so **do not delete a logo that is in use** – replace its image instead if needed (a replaced image gets a new file name, so re-copy signatures afterwards).
- Recipients' email clients may ask them to "download pictures" before remote logos are shown. This is normal for linked images.

## Access

- Uses the existing `settings.manage` permission. System Administrators always have access.
- To give another role access: **Permissions → edit the role → tick `settings.manage`**.

## Troubleshooting

- **Menu item missing:** the user lacks `settings.manage`, or the view cache still holds the old layout – repeat step 4.
- **404 on `/signature-builder`:** the route cache is stale – repeat step 4.
- **"Unable to create directory" / upload error:** set `public/uploads/signature-logos` to 755 (or 775 on some hosts).
- **Logo uploaded but not displaying:** open the image URL directly; if it returns 403, check the `.htaccess` was uploaded unchanged and that the file is a PNG/JPG/GIF/WebP.
