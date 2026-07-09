# ISO Admin Command Framework v2.8.0

## Employee Compliance Management - Phase 1, Phase 2 and Phase 3

This is a changed-files-only update for the existing Laravel 11 ISO Command Centre platform. It does not replace unrelated attendance, vehicle, leave, dashboard, role or permission functionality.

### Phase 1: Compliance Foundation

- Database-driven employee document types.
- Required document matrix by all employees, departments, roles and basic job-title matching.
- Employee compliance overview.
- Employee profile compliance percentage, missing documents, expired documents, expiring soon documents and documents awaiting approval.
- Document approval workflow.
- Sensitive and medical document flags.

### Phase 2: Company Policy Module

- Company policies with version records.
- Policy assignment rules for all employees, departments, roles and job-title matching.
- Employee policy acknowledgement action.
- Policy acknowledgement tracking including IP address and user agent.
- Policy detail reporting for acknowledged and missing employees.

### Phase 3: Medical and Licence Compliance

- Dedicated medical compliance records linked to employee document uploads.
- Fit-for-duty status: fit, restricted, unfit and pending.
- Sensitive medical document access checks.
- Dedicated licence compliance records linked to employee document uploads.
- Licence types include Driver’s Licence, PDP, Forklift Licence, Equipment Operator Certificate, Site Access Certificate and other permits.

### Apply

Upload only the included changed/new files, then run:

`/updates/v2-8-0`

No artisan command is required.
