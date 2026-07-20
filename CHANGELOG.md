# Changelog

## v2.9.1

Platform-wide light UI redesign.

- Replaced the dark command-centre theme with a new light theme across every module (sidebar, topbar, cards, tables, forms, pills, alerts, calendar, dashboard widgets, vehicle tracking maps).
- Re-tuned text/background contrast for table headers, pills, labels and buttons so they read correctly on light surfaces.
- Bumped the displayed platform version shown in the footer, including the fallback used before the `system_settings` table exists.

## v2.9.0

Update Manager: web-based platform updates via ZIP upload or GitHub.

- Added the Update Manager under the Admin menu (`/settings/updates`), System Administrator only.
- Added platform update via uploaded deployment ZIP (full or partial changed-files packages).
- Added platform update via GitHub: configurable repository, branch and optional access token for private repositories.
- Added automatic pre-apply code backup ZIPs with download, delete and re-apply support.
- Applies never overwrite `.env`, `storage/`, `node_modules/` or the local database file; unsafe ZIP paths are rejected and compiled caches are cleared after applying.
- Added permissions `platform_updates.view` and `platform_updates.manage`.
- Added Update Manager settings group in System Settings.
- System Administrator permissions are re-synced.

## v2.8.9

Employee profile crash fix (drift correction, not a new bug).

- Fixed `RelationNotFoundException: Call to undefined relationship [documentType] on model [App\Models\EmployeeDocument]` on the employee profile page.
- Same root cause as v2.8.8: a live-only leftover from the abandoned, documentation-only v2.8.0 compliance work. The `EmployeeController.php` in this repository has never referenced `documentType`; the live server's copy had drifted from git.
- Re-ships the correct `app/Http/Controllers/EmployeeController.php` (unchanged from v2.8.7) so it overwrites the stale live copy. No database or permission changes.
- Flagged that this is the second such drift-caused crash in a row; offered to package a full (non-diff) baseline of employee/document/compliance files to prevent further surprises from the same abandoned feature.

## v2.8.8

Employee Compliance Overview (fixes broken dashboard link).

- Fixed `Route [employee_compliance.index] not defined` thrown by the Dashboard's "Compliance Overview" widget. That widget referenced a v2.8.0 "Employee Compliance Management" feature that was only ever documented (README + CHANGELOG entry) and never actually implemented — no controller, route, view or permission existed for it anywhere in history.
- Added a real `employee_compliance.index` page backed by existing employee document data (no new tables): active employee count, compliant count, employees with no documents on file, and documents needing attention (expired + reminder-due), plus a per-employee breakdown.
- Added a matching Dashboard widget and sidebar link.
- Added the `employee_compliance.view` permission (Director and Manager by default). Existing installs need to run `/updates/v2-8-8` once to seed it.
- Did not rebuild the full v2.8.0 Phase 1-3 scope (document-type matrix, approval workflow, policy versioning, medical/licence compliance) — that was never actually built and is a separate, much larger feature if still wanted.

## v2.8.7

Employee documents redesign.

- Removed two obsolete one-use hotfix scripts from `public/` that exposed a hardcoded-key live file-rewrite endpoint (`iso-employee-policy-hotfix.php`, `iso-employee-policy-view-hotfix.php`). The bug they patched is already fixed in the active controller/view.
- Redesigned the employee profile "Employee Documents" card with status stat tiles, urgency-based sorting and red/amber/green expiry status colours.
- Added plain-language expiry summaries ("Expires in 12 days", "Expired 3 days ago") across the employee profile, personal profile and reminders centre.
- Added the ability to edit an existing employee document (type, title, expiry, reminder lead time, notes, optional file replacement) via a shared create/edit form partial with a live reminder-date preview.
- Added the ability to reactivate an inactive document and to permanently delete a document (removes the stored file), alongside the existing mark-inactive action.
- Added summary counts to the Document Reminders centre for each filter.
- No database schema changes; reminders and calendar integration behaviour are unchanged.

## v2.8.6

Customer CRM update.

- Extended Customers with type (customer/prospect/supplier/partner/other), industry, website and an assignable account manager.
- Added customer sites/locations, replacing the never-deployed "Clients" module with a supported one built on the live Customers table.
- Added customer contacts at both the company level and the site level, with role/type, primary flag and status.
- Added a customer interactions/activity log (calls, emails, meetings, site visits, tasks and notes) with optional follow-up dates; overdue follow-ups are highlighted on the customer profile.
- Added permissions `customer_sites.manage`, `customer_contacts.manage` and `customer_interactions.manage`; synced to System Administrator, Director and Manager roles.
- Removed the orphaned `ClientController` and `clients` views, which had no route and were never reachable.
- The update apply step (`/updates/v2-8-6`) also removes the old `ClientController.php` and `resources/views/clients/` from disk if present, so applying it is a single self-contained step.

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
