# Changelog

## v3.1.0

New look, part 1: the whole site switches to the new light design. See `README_V3_1_0_NEW_LOOK.md`.

- New design system in `public/css/iso.css`, with light colours, cards, buttons, status pills, tables and forms. Every page uses it.
- New layout: a dark sidebar grouped into Workspace, Operations and Admin, with line icons (`<x-icon>`), a clean top bar, and a slide-in menu on tablets and phones.
- Rebuilt dashboard. "Import attendance" is the main button, "Needs attention" gathers what's waiting, and the personal widgets look new. Medium and large attendance widgets show today's clock-ins.
- The dashboard and shortcut editors can now be reordered with Move up / Move down buttons, which work on touch screens.
- Restyled login, change password, access denied, installer and "Finishing the update" pages.
- Pages with their own dark colours (calendar, vehicles, customers, settings and others) now read correctly in the light theme.
- Fixed System settings ordering using MySQL-only SQL, which also failed in tests.
- Added a test that opens every page as an administrator, on both SQLite and MySQL.

## v3.0.1

- Fixed the update failing with a bare "503 Service Unavailable" on sites with large tables. The database backup taken before an update loaded each table into memory at once, which shared hosting kills. It now streams rows: a 200,000-row table peaked at 109 MB before and 18 MB now.

## v3.0.0

Update Manager, ported from Jarvis. See `README_V3_0_0_UPDATE_MANAGER.md` and `docs/updates.md`.

- Added System Settings → Updates (`/settings/updates`), with the new `platform_updates.manage` permission. It installs GitHub releases with a database backup first, runs database migrations and a health check, and switches back by itself on failure.
- Added rollback to any of the last 3 installed versions, code only or with the database as that version left it, and an update history kept outside the database.
- Added safety nets outside Laravel: the front door switches back if a new version can't start, `cron.php` undoes an update that was never finished, and `recovery.php` works with a recovery key.
- New server layout: releases, shared settings and storage live in a private folder outside `public_html`, and the site is served without `/public` (old links redirect). `iso-setup.php` moves a v2.8.x site across once.
- Database changes are now Laravel migrations. The stock migrations only create missing tables, and the old `/updates/vX-Y-Z` pages remain.
- Added the release workflow and `scripts/build-release.sh`. Release zips include `vendor/`.
- Fixed `/install` failing on MySQL with "There is no active transaction".
- Fixed `composer.json` scripts and removed the machine-specific `cache-dir`.

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
