# ISO Admin Command Framework v2.8.7

## Employee Compliance Overview

### Problem fixed

The Dashboard showed a "Compliance Overview" widget that called `route('employee_compliance.index')`, but that route had never been implemented anywhere in the codebase — only two documentation files (`README_V2_8_0_EMPLOYEE_COMPLIANCE_PHASE_1_2_3.md` and a `CHANGELOG.md` entry) described the feature as shipped in v2.8.0. No controller, route, view, or permission for it was ever committed. Opening the Dashboard (or the widget's "Open Compliance Overview" button) threw:

`Route [employee_compliance.index] not defined.`

### Fix

Rather than re-adding a dead link, this update builds a real Employee Compliance Overview backed by data that already exists — active employees and their uploaded documents (`employee_documents`, present since v1.3). No new database tables are required. This does not attempt to rebuild the full v2.8.0 Phase 1–3 concept (document-type matrix, per-role required documents, approval workflow, policy versioning, medical/licence compliance records) — that documentation described a much larger system that was never actually built; if you still want that full scope, it needs to be scoped and built separately.

### Added

- `GET /employee-compliance` — Employee Compliance Overview page. Shows active employee count, compliant count, employees missing documents entirely, and total documents needing attention (expired + reminder-due), plus a per-employee breakdown table.
- An employee is **compliant** when they have at least one document on file and none of their active documents have expired.
- Sidebar link "Compliance Overview" under Operations (only shown when the `employee_documents` table exists and the user has the new permission).
- Dashboard widget "Employee Compliance" (available in the dashboard widget picker) showing Active Employees / Compliant / Missing Docs with a link to the full overview.
- New permission `employee_compliance.view`, granted by default to Director and Manager roles (System Administrator already has all permissions).

### Install

Upload the included changed/new files, then run:

`/updates/v2-8-7`

This seeds the `employee_compliance.view` permission for existing installs (fresh installs via `/install` already include it). No table changes are made.
