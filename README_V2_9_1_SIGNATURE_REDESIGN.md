# ISO Admin Command Framework v2.9.1

## Email Signature Redesign – branded background, official company logo slot

This is a changed-files-only update for the Signature Builder added in v2.9.0. It also works if v2.9.0 was **never installed**, because the package contains every signature-builder file.

## What changed

- **New signature design.** A deep-teal brand band with filtration artwork behind the name, title and contact details. The artwork shows a hexagonal filter mesh capturing amber contaminant particles, with clean oil flowing past. The band is followed by a white partner-logo section, an amber-to-green "contaminated → clean oil" accent stripe, the service line and the company address.
- **All text is live HTML** (name, title, mobile, office, email, web, tagline, address). Nothing is baked into the image, so it stays sharp, searchable and clickable.
- **The wrong "ISO." text logo is gone.** The signature now has a white **company-logo tab** for the official ISO-Reliability Partners logo, which you upload under **Manage Logos**. Until one is uploaded the tab shows "ISO-Reliability Partners" as plain text.
- **Background image with a safe fallback.** Gmail and the Outlook signature editors strip background images. When that happens the band shows as solid teal (#0D4B4A) with the same layout, and all text stays readable. Tick **"Preview without background image"** in the builder to see exactly how that looks.
- **Custom background (optional).** Manage Logos → *Signature background* lets you upload your own band artwork (1280 × 264 px JPG) or reset to the default.
- **Long names, titles and emails adapt automatically.** Long names get a smaller font, long titles wrap, and long email addresses move to their own row, so text never runs into the bright part of the artwork.
- **Gmail size check.** The builder shows the signature size against Gmail's 10,000-character limit and warns you if a signature would be too long. With 8 partner logos a signature is about 8,500 characters.
- **Two outputs:**
  - **Copy formatted signature**: a lean version for pasting into Gmail, Outlook on the web / new Outlook, Apple Mail and other signature editors.
  - **Download personalised HTML**: a full version that also contains the Outlook-for-Windows (VML) background code. Use it as a classic Outlook signature file (see below).

## Files in this package

| File | Status |
|---|---|
| `app/Http/Controllers/SignatureBuilderController.php` | changed (company logo + background actions) |
| `app/Services/SignatureLogoStore.php` | changed (company logo + background storage) |
| `resources/views/signature_builder/index.blade.php` | changed (new signature design + builder options) |
| `resources/views/signature_builder/logos.blade.php` | changed (Company logo and Signature background cards) |
| `routes/web.php` | changed (4 new signature-builder routes) |
| `resources/views/layouts/app.blade.php` | unchanged since v2.9.0 (included in case v2.9.0 was not installed) |
| `public/images/signature/iiso-signature-bg.jpg` | **new** – default band artwork (30 KB) |
| `public/uploads/signature-logos/.htaccess` | unchanged since v2.9.0 (included in case v2.9.0 was not installed) |
| `VERSION`, `CHANGELOG.md`, `README_V2_9_0_SIGNATURE_BUILDER.md`, `README_V2_9_1_SIGNATURE_REDESIGN.md` | docs |

## Install (no console needed)

1. **Back up** `routes/web.php`, `resources/views/layouts/app.blade.php` and the `resources/views/signature_builder/` folder on the server (if it exists).
2. **Upload** the ZIP contents into the Laravel root folder (the folder that contains `app`, `public`, `routes`, `resources` …). Keep the folder structure, and overwrite when asked.
   - In cPanel File Manager, turn on **Settings → Show Hidden Files (dotfiles)** and check that `public/uploads/signature-logos/.htaccess` is there.
3. **Check folder permissions** (cPanel → File Manager → right-click → Change Permissions):
   - `public/uploads/signature-logos` → **755** (use 775 if uploads fail)
   - `public/images/signature` → **755**
   - `storage/app` → **755** (normally already writable)
4. **Only if `/signature-builder` returns a 404 or the menu item is missing**, clear the cache: open `https://isoadmin.co.za/public/isoadmin-clear-cache-v221.php?key=iso-v221-route-repair` (if that file is still in `public/`), or delete `bootstrap/cache/routes-v7.php`, `bootstrap/cache/routes.php`, `bootstrap/cache/config.php` (whichever exist) and the `.php` files inside `storage/framework/views/`.
5. **Upload the official company logo.** Log in → **Admin → Signature Builder → Manage Logos → Company logo** → choose the logo file → **Upload Company Logo**.
   - Best file: the normal full-colour ISO-Reliability Partners logo as a **PNG with a transparent background**, about **400 × 128 px**. It is shown at up to 200 × 64 px on a white tab.
   - Use PNG or JPG. Outlook for Windows cannot display WebP, and SVG is not shown by most email clients.
6. **Upload the partner logos** in the same page (PPL, BioMax, Royal Purple, Bel-Ray, Calumet, Yateks, Filter Focus, Seal Saver). Until they are uploaded they show as text tiles.
7. Open **Signature Builder**, pick an employee (or type the details), and check the preview with and without the background.

No database update, updater page or console command is required.

## Putting the signature into each email program

**Gmail (web):** Settings ⚙ → *See all settings* → *General* → *Signature* → *Create new*. In the builder click **Copy formatted signature**, then paste into the signature box and **Save changes** at the bottom of the page. Gmail removes background images, so the band shows as solid teal. This is expected and was designed for.

**Outlook on the web / Microsoft 365 / new Outlook for Windows:** Settings → *Account* → *Signatures* → *New signature* → paste → *Save*.

**Classic Outlook for Windows (desktop), keeping the background artwork:**
1. In the builder click **Download personalised HTML**.
2. Rename the downloaded file from `.html` to `.htm` (for example `IISO_Jane_Smith.htm`).
3. Press **Windows + R**, type `%APPDATA%\Microsoft\Signatures` and press Enter.
4. Copy the `.htm` file into that folder.
5. In Outlook: File → Options → Mail → *Signatures…* → select the new signature as the default for new messages and replies.

(Pasting into classic Outlook's own signature editor also works, but that editor drops the background, so the band shows as solid teal.)

**Apple Mail (Mac):** Mail → Settings → *Signatures* → **+** → untick *Always match my default message font* → paste. Apple Mail shows the background artwork.

**iPhone / iPad Mail:** Settings → Mail → *Signature* → paste. iOS may simplify the layout. For a full-fidelity signature on mobile, use the Outlook or Gmail app with the signature synced from the web.

## Good to know

- Signatures load all images (company logo, partner logos, background) from **isoadmin.co.za**. Some recipients' email programs ask them to "download pictures" first. This is normal for every image-based signature.
- **Don't delete an image that signatures already use.** Emails already sent keep pointing to it. If you must replace a logo, replace it, then re-copy the signatures. A replaced image gets a new file name.
- If the builder shows the size line in amber (over 10,000 characters), Gmail will refuse the signature. Hide one or two partner logos under Manage Logos, or shorten the partner logo links.
- The default artwork can be re-rendered or changed from `resources/signature/` in the source repository (see `resources/signature/README.md`). The server does not need those files.
