# ISO Admin Command Framework v2.8.9

## Employee profile crash: undefined `documentType` relationship

### Problem fixed

Opening an employee profile threw:

```
Illuminate\Database\Eloquent\RelationNotFoundException
Call to undefined relationship [documentType] on model [App\Models\EmployeeDocument].
$employee->load($relations);
```

This is the same root cause as the v2.8.8 "Compliance Overview" crash: the live server is running a version of `app/Http/Controllers/EmployeeController.php` that eager-loads a `documentType` relationship on `EmployeeDocument` (left over from the abandoned, documentation-only v2.8.0 "Employee Compliance Management Phase 1-3" work). No `documentType()` relationship, `document_type_id` column, or `DocumentType` model exists anywhere in this codebase — it was only ever described in `README_V2_8_0_EMPLOYEE_COMPLIANCE_PHASE_1_2_3.md`, never built.

This is not something introduced by the v2.8.7/v2.8.8 updates. The version of `EmployeeController.php` in this repository/branch has never referenced `documentType` — it only eager-loads `documents.uploader`, which is a real relationship. The live server's copy of this one file has simply drifted from what's in git.

### Fix

Re-ships the correct `app/Http/Controllers/EmployeeController.php` so it overwrites whatever copy is currently live. No logic changed on top of what v2.8.7 already shipped for this file — this is a drift-correction, not a new feature.

### A pattern worth flagging

This is the second time in a row that a live-only leftover from the abandoned v2.8.0 compliance work has caused a crash (first the Dashboard widget, now the employee profile page). If other files were hand-edited on the live server during that same abandoned attempt and never reconciled back into git, more of these could surface elsewhere (e.g. other controllers or views touching `EmployeeDocument`). If you'd like, I can package a full (not diff-only) copy of every file that touches employees/documents/compliance so you can do one clean overwrite instead of chasing individual crashes — just ask.

### Install

Upload the included file only. No `/updates/...` route needs to be run — no database or permission changes.
