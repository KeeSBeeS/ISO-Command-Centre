# Changelog

## v2.9.1

Email signature redesign.

- New branded signature design: deep-teal band with filtration artwork (hex filter mesh catching contaminant particles, clean oil flowing past) behind live HTML name, title and contact details; white partner-logo grid; amber-to-green "contaminated → clean oil" accent stripe.
- Replaced the incorrect "ISO." text logo with a company-logo tab for the official logo, uploaded under Manage Logos (falls back to the text "ISO-Reliability Partners").
- Added optional custom signature background upload with reset to the default artwork (`public/images/signature/iiso-signature-bg.jpg`).
- Background degrades to a solid #0D4B4A band in Gmail and the Outlook signature editors; "Preview without background image" shows that view.
- Copy produces a lean Gmail-safe version; Download produces a full version with Outlook (VML) background code for classic Outlook .htm signature files.
- Signature size indicator against Gmail's 10,000-character limit, counted as Gmail stores the markup, with a warning from 9,700 characters; long names, titles and emails adapt automatically.
- Empty job title, mobile or email lines are left out of the signature instead of showing placeholders; Copy and Download need the full name.
- Phone links drop a "(0)" trunk prefix and convert 0xx numbers to +27; invalid email addresses are shown unlinked and flagged instead of linking to info@.
- Replacing the company logo, background or a partner logo keeps the old file, so emails already sent keep their images. Data files are written atomically, and a damaged data file is refused instead of being overwritten.
- Custom backgrounds must match the band shape (about 4.85 : 1, at least 640 × 132 px).
- Builder preview scales to fit narrow screens and comes first on tablets and phones; Manage Logos shows one card per logo on narrow screens; a failed logo edit stays in its own form.
- Editable artwork source in `resources/signature/`.

## v2.9.0

Email signature builder and signature logo manager.

- Added **Signature Builder** under the Admin menu (`/signature-builder`), based on the ISO-Reliability Partners signature template.
- Employee details can be loaded from an existing employee record or typed manually.
- Copy the formatted signature to the clipboard or download a personalised HTML file.
- Added **Manage Logos** (`/signature-builder/logos`) to add, edit, reorder, hide and delete the brand logos in the signature.
- Logos can be uploaded (PNG, JPG, GIF, WebP, max 2 MB) or linked from an existing https:// image URL. Logos without an image show the brand name as text.
- Logos are laid out four per row and scaled to fit the logo box with exact width/height attributes for Outlook.
- No database update is required. Logo records are stored in `storage/app/signature-builder/logos.json` and uploads in `public/uploads/signature-logos/`.
- Access uses the existing `settings.manage` permission (System Administrator always has access).

## v2.8.0 Foreign Key Hotfix

- Fixed MySQL error `1059 Identifier name is too long` during the v2.8.0 update.
- Replaced long auto-generated Laravel foreign-key names with short explicit constraint names in the v2.8.0 compliance update tables.
- No functional modules were changed; this only repairs the database update step.

## v2.8.0

Employee Compliance Management Phase 1, Phase 2 and Phase 3.

- Added database-driven employee document types.
- Added required document rules/matrix by all employees, departments, roles and basic job-title matching.
- Extended employee documents with document type linkage, issue date, approval status, approval/rejection audit fields, sensitivity, medical flags and replaced-document link.
- Added document approval workflow with pending, approve and reject states.
- Added employee compliance overview with missing, expired, expiring soon, awaiting approval and compliance percentage.
- Added company policy module with versions, assignments and acknowledgement tracking.
- Added policy acknowledgement reporting for acknowledged and missing employees.
- Added medical compliance records with fit/restricted/unfit/pending status and sensitive access rules.
- Added licence compliance records for Driver’s Licence, PDP, Forklift Licence, Equipment Operator Certificate, Site Access Certificate and other permits.
- Added dashboard compliance widget and employee profile compliance sections.
- Added sensitive document download permission checks.
- Added permissions for document types, employee compliance, document approval, policies, medical compliance and licence compliance.

## v2.7.1

Customer site contact people and delete-control update.

- Added contact people under Customer Site / Location.
- Added contact types: Accounts, Stores, Foreman, Engineer and Other.
- Contact details include name, position/title, phone, mobile/WhatsApp, email, status and notes.
- Added ability to delete contact people under a site/location.
- Added visible delete controls for client sites/locations.
- Added visible delete controls for customers.
- Added permissions `site_contacts.view` and `site_contacts.manage`.
- System Administrator permissions are re-synced.
