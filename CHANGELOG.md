# Changelog

## v2.9.0

WhatsApp Business integration (Meta WhatsApp Cloud API). See `README_V2_9_0_WHATSAPP_BUSINESS.md`.

- Shared WhatsApp inbox with live refresh, delivery/read ticks, media, templates and 24-hour service window enforcement.
- Signed webhook receiver for inbound messages and delivery statuses; STOP/START consent keywords; optional auto-reply.
- Employee opt-in directory with bulk consent recording.
- Template sync from Meta (positional and named parameters).
- Broadcasts to employees, departments or roles (opted-in only), with personalisation and delivery stats.
- Automatic notifications: leave approved/declined, employee document expiry, vehicle document expiry to assigned driver.
- Cron URL for queued sends, retries and reminders; settings page with encrypted credentials and connection test.
- New `WhatsApp` permission module; self-contained provider and routes so existing files are not replaced.
- One-use `public/iso-whatsapp-v290-activate.php` patches the live layout and provider list in place.

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
