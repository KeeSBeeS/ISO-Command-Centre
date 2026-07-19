# Changelog

## v2.8.6

Customer CRM update.

- Extended Customers with type (customer/prospect/supplier/partner/other), industry, website and an assignable account manager.
- Added customer sites/locations, replacing the never-deployed "Clients" module with a supported one built on the live Customers table.
- Added customer contacts at both the company level and the site level, with role/type, primary flag and status.
- Added a customer interactions/activity log (calls, emails, meetings, site visits, tasks and notes) with optional follow-up dates; overdue follow-ups are highlighted on the customer profile.
- Added permissions `customer_sites.manage`, `customer_contacts.manage` and `customer_interactions.manage`; synced to System Administrator, Director and Manager roles.
- Removed the orphaned `ClientController` and `clients` views, which had no route and were never reachable.

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
