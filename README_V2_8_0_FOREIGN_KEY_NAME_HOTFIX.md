# ISO Admin Command Framework v2.8.0 Foreign Key Name Hotfix

## Problem fixed

MySQL failed during the v2.8.0 update with:

`SQLSTATE[42000]: Syntax error or access violation: 1059 Identifier name 'employee_document_requirement_department_employee_document_requirement_id_foreign' is too long`

Laravel auto-generated a foreign-key constraint name longer than MySQL's 64-character identifier limit.

## Fix

The v2.8.0 UpdateController was corrected to use short explicit foreign-key names for the long compliance pivot tables.

Examples:

- `ed_req_dept_req_fk`
- `ed_req_dept_dept_fk`
- `ed_req_role_req_fk`
- `ed_req_role_role_fk`
- `cp_assign_dept_policy_fk`
- `cp_assign_role_policy_fk`

## Deployment

Upload the hotfix changed files only, then rerun:

`/updates/v2-8-0`

The update is written with Schema checks and can safely continue after the failed partial run.

## Changed file

- `app/Http/Controllers/UpdateController.php`
