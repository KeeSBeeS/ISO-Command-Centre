# Changelog

## v2.8.6

Employee documents redesign.

- Removed two obsolete one-use hotfix scripts from `public/` that exposed a hardcoded-key live file-rewrite endpoint (`iso-employee-policy-hotfix.php`, `iso-employee-policy-view-hotfix.php`). The bug they patched is already fixed in the active controller/view.
- Redesigned the employee profile "Employee Documents" card with status stat tiles, urgency-based sorting and red/amber/green expiry status colours.
- Added plain-language expiry summaries ("Expires in 12 days", "Expired 3 days ago") across the employee profile, personal profile and reminders centre.
- Added the ability to edit an existing employee document (type, title, expiry, reminder lead time, notes, optional file replacement) via a shared create/edit form partial with a live reminder-date preview.
- Added the ability to reactivate an inactive document and to permanently delete a document (removes the stored file), alongside the existing mark-inactive action.
- Added summary counts to the Document Reminders centre for each filter.
- No database schema changes; reminders and calendar integration behaviour are unchanged.

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
