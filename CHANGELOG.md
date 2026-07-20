# Changelog

## v2.9.0

Time Attendance redesign, director leave allocations and 36-month sick leave cycle tracking.

- Reworked the attendance day calculation: earliest punch of the day is the check-in, latest punch is the checkout.
- Late arrivals are measured against the 06:00 office start; early departures against the 15:00 office close (both configurable).
- Weekends and company-closed public holidays are treated as non-working days; punches on them are retained for audit only.
- Added early-leave and missing-checkout detection with exact minutes, plus new `attendance_days` columns `is_early_leave`, `early_leave_minutes` and `is_weekend`.
- Rebuilt the attendance dashboard as an exception-focused Employee Overview ranked most-problems-first, with a Daily Log view and month navigation.
- Absences are derived (a scheduled workday with no check-in and no approved leave cover), clamped to the latest imported date so future days are never flagged.
- Rebuilt the employee attendance profile with period KPIs, a day-by-day log, a 12-month trend, and a collapsed audit section for raw punches and imports.
- Added director-controlled annual paid leave allocations (1 January – 31 December leave year) at `/leave/allocations`.
- Added a 36-month paid sick leave cycle tracker (6 weeks / 30 working days per cycle, anchored to the employee start date) at `/leave/sick`.
- Added configurable attendance history retention (default: keep forever, never less than 12 months) with automatic pruning after import.
- Added permissions `leave_allocations.view`, `leave_allocations.manage`, `sick_leave.view`, `sick_leave.manage`.
- Added the `/updates/v2-9-0` browser update step that installs the new columns, tables, permissions and settings and rebuilds existing attendance days.

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
