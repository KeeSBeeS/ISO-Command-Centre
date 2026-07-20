# ISO Admin Command Framework v2.8.7

## Employee Documents Redesign

This is a changed-files-only update for the existing Laravel 11 ISO Command Centre platform. It rebuilds the employee profile document section, upload flow and reminder centre. No database schema changes are required — the existing `employee_documents` table (installed since v1.3) already has every column this update needs.

### Security fix

- Removed `public/iso-employee-policy-hotfix.php` and `public/iso-employee-policy-view-hotfix.php`. Both were one-use hotfix scripts from the 17 July vehicle-policy crash fix. Each exposed a hardcoded-key endpoint in the public web root that could overwrite live application files. The underlying bug they patched is already fixed directly in `EmployeeController` and `employees/show.blade.php`, so the scripts were dead weight left as an open backdoor. Delete them from any existing live install if they were uploaded there.

### Employee document section

- Employee profile "Employee Documents" card now shows stat tiles (Total, Expired, Reminder Due, Valid/No Expiry).
- Documents are sorted by urgency (expired first, then reminder-due, then valid) instead of only by upload date.
- Each document row shows a plain-language expiry summary ("Expires in 12 days", "Expired 3 days ago").
- Expired, reminder-due and valid documents now use distinct status colours (red/amber/green) instead of a single on/off pill.
- Same treatment applied to the "My Documents" section on the personal profile page and the Document Reminders centre.

### Upload and edit

- The upload form and a new edit form share one partial (`employee_documents/_form.blade.php`) so both stay in sync.
- Selecting an expiry date now shows a live preview of the exact reminder date that will appear on the reminders list and calendar.
- Documents can now be edited after upload: type, title, expiry date, reminder lead time and notes can all be changed, and the attached file can optionally be replaced.
- Inactive documents can be reactivated instead of only re-uploaded.
- Documents can be permanently deleted (removes the stored file too), separate from the existing "mark inactive" soft action. Both remain gated behind the `employee_documents.manage` permission.

### Reminders and calendar

- The Document Reminders centre now shows summary counts for each filter (Due Now, Expired, Next 60 Days, Inactive).
- Reminder and calendar behaviour is unchanged and continues to work exactly as before: any document with `has_expiry` set produces a reminder-date and expiry-date entry on `/calendar` and in `/employee-documents/reminders`, computed live from `expires_at` and `remind_days_before`.

### Apply

Upload only the included changed/new files. No `/updates/...` route needs to be run — this release does not add or change any database tables, columns or permissions.

If `public/iso-employee-policy-hotfix.php` or `public/iso-employee-policy-view-hotfix.php` exist on the live server from the 17 July hotfix, delete both manually after uploading this update.
