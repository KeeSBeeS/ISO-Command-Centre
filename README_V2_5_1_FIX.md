# ISO Admin Command Framework v2.5.1 Fix

This is a hotfix for the v2.5 updater error:

`PDOException: There is no active transaction`

## Cause

The v2.5 updater wrapped MySQL schema changes (`CREATE TABLE`, `ALTER TABLE`) inside `DB::transaction()`. MySQL auto-commits DDL statements, so Laravel could try to close a transaction that MySQL had already ended.

## Fix

`UpdateController@applyV25` no longer wraps schema updates in `DB::transaction()`.

The updater remains idempotent and can be safely run again after the failed/partial v2.5 attempt.

## Install

1. Upload this package over the current Laravel files.
2. Replace existing files when asked.
3. Log in as Director/System Administrator.
4. Run:

https://isoadmin.co.za/updates/v2-5

5. Click the apply/update button again.

## Changed file

- `app/Http/Controllers/UpdateController.php`

No database rollback is required.
