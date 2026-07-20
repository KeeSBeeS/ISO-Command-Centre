# ISO Admin Command Framework v2.9.0

## Time Attendance Redesign, Leave Allocations & Sick Leave Cycles

This is a changed-files-only update for the existing Laravel 11 ISO Command Centre platform. It builds on v2.8.5 and does not replace unrelated employee, dashboard, compliance, vehicle, calendar, role or permission functionality. It reworks the Time Attendance module into an exception-focused overview, and adds two new director tools: annual paid-leave allocations and a 36-month paid sick-leave cycle tracker.

---

## 1. What the attendance equipment gives us

The attendance hardware exports an **Original Records Report** CSV. Each row is a single card-reader event (a "punch"). The only fields that matter for attendance are:

- **Name** (and Person ID, used to match the employee)
- **Time** (the date and time of the punch)

Everything else in the export (department, check point, custom name, data source, handling type, temperature, abnormal) is retained for audit but is not used to calculate attendance. A single employee produces many punches per day because every door/reader logs an event.

### The rule we apply per employee per day

- The **earliest punch of the day is the check-in.**
- The **latest punch of the day is the checkout.**
- If there is only one punch, the check-in is recorded and the checkout is left blank (flagged as *missing checkout*).

This matches the request exactly: "The earliest time on the system will be check-in. The last time will be checkout."

---

## 2. Best-practice research summary

Before implementing, we looked at how established time-and-attendance systems (BambooHR, Deputy, Jibble, Kelio and similar) model this problem, and at the South African statutory context (BCEA) since the platform is ZA-based. The relevant best practices we adopted:

1. **Separate raw events from a derived daily summary.**
   Never overwrite the equipment's raw punches. Keep them immutable for audit, and compute a *daily summary* (check-in, checkout, late, early-leave) on top. The platform already had `attendance_raw_records` (raw) and `attendance_days` (derived); v2.9.0 keeps that separation and only enriches the derived layer. This means the day rules can be recalculated at any time (e.g. if office hours change) by rebuilding from the untouched raw records.

2. **Model the *work schedule* explicitly, don't hard-code it in reports.**
   A "scheduled workday" is a weekday that is not a company-closed public holiday. Weekends and public holidays are non-working days on which no attendance is expected. Late/early/absent are only ever evaluated against scheduled workdays. This is why the platform already carries a `public_holidays` table (with `is_company_closed`); v2.9.0 reuses it and adds weekend awareness.

3. **Derive absence, don't store it.**
   Absent = a scheduled workday with no check-in **and** no approved leave cover. Storing "absent" rows is fragile (they go stale when leave is later approved, or when a late import arrives). Instead we compute absence on read, clamped to the latest imported attendance date so future / not-yet-imported days are never flagged as absent. This is the single most important correctness decision and mirrors how payroll-grade systems reconcile attendance with leave.

4. **Lead with exceptions, not with raw logs.**
   Managers do not want to scroll thousands of punches. The primary view is a per-employee rollup ranked by exception count (most problems first): late arrivals, early departures, missing checkouts, absences. The raw punch history is still one click away for audits but is collapsed by default.

5. **Anchor statutory leave cycles to the employee, and count in working days.**
   BCEA sick leave is "the number of days the employee would normally work in six weeks, over a 36-month cycle." On a five-day week that is 30 working days per 36 months, and the cycle runs from the employee's start date — not a fixed calendar window. v2.9.0 implements exactly this, counting sick days on working days only (weekends and public holidays excluded) and anchoring the cycle to each employee's start date.

6. **Keep a long history and make retention configurable.**
   The request is "a year history or even longer." Default retention is *keep forever*; an optional setting can cap it, but never below 12 months, so at least a full year is always retained.

---

## 3. Attendance day calculation (rewritten)

`App\Services\AttendanceCsvImporter::rebuildDay()` now:

- Uses the **earliest punch as check-in** and the **latest punch as checkout** (no more 09:00 cut-off heuristic).
- Reads office hours from settings: **start 06:00, close 15:00** (`attendance_company_start_time`, `attendance_company_close_time`).
- Flags **late** when the check-in is after the office start time (weekdays only), recording the exact minutes late.
- Flags **early leave** when the checkout is before the office close time (weekdays only), recording the exact minutes early.
- Marks **weekends** and **company-closed public holidays** as non-working days: punches are retained for audit, but the day is never counted as late/early/worked.
- Flags **missing checkout** when there is a check-in but no distinct later punch.

New `attendance_days` columns: `is_early_leave`, `early_leave_minutes`, `is_weekend`.

---

## 4. New attendance UI

### Employee Overview (default) — `/attendance`
- Per-employee rollup for the selected period, one row each, **ranked most-exceptions-first**.
- Columns: attendance rate, days worked, late count (+ total time), left-early count (+ total time), missing checkouts, on-leave days, absent days.
- Month navigation (previous / current / next month) and a free-text employee search.
- Company-wide KPI cards across the top (late arrivals, early departures, missing checkouts, absent days, on-leave days, days worked).

### Daily Log — `/attendance?view=daily`
- Every day's check-in / checkout / hours / status, with filters for late-only, early-leave-only, missing-checkout-only and public-holidays-only.

### Employee Attendance Profile — `/attendance/{employeeCode}`
- KPI cards for the period (days worked, late, early, missing checkout, absent, on leave).
- **Sick Leave Cycle** card and **Paid Leave** card (see below).
- **Day-by-day attendance**: every scheduled workday in range, including absences and on-leave days, plus any weekend/holiday on which the employee badged in.
- **Monthly summary (last 12 months)** so patterns are easy to spot.
- **Audit Data** section (collapsed): raw punches, punch-history-by-date, status breakdown and import sources — the full detail retained from v2.8.x.

---

## 5. Leave allocations (director-controlled)

New page **Leave Allocations** — `/leave/allocations`:

- The paid-leave year runs **1 January to 31 December**.
- A **director** sets how many paid leave days each employee receives per year (with an optional carried-over amount). Managers get view-only access.
- Shows allocated / carried-over / used / remaining per employee, plus the current sick-leave cycle at a glance.
- Backed by the existing `employee_leave_allocations` table and `LeaveBalanceService`; used days come from approved, deductible leave requests counted on working days.

Permissions: `leave_allocations.view` (director + manager), `leave_allocations.manage` (director only).

---

## 6. Sick leave cycle tracker

New page **Sick Leave** — `/leave/sick`:

- Every employee receives **6 weeks (30 working days) of paid sick leave per 36-month cycle**.
- The cycle is **anchored to the employee's start date** and renews automatically every 36 months.
- Per-employee balances: current cycle window, days used, days remaining, usage bar, and an over-entitlement warning.
- Directors/managers can **record** a sick-leave period and **remove** an entry (removed entries are kept for audit but no longer count against the cycle).
- Sick days are counted on **working days only** — weekends and public holidays are excluded — from both director-recorded sick records and approved sick-type leave requests.

Cycle length and days-per-cycle are configurable (`sick_leave_cycle_months` = 36, `sick_leave_cycle_days` = 30) should the statutory basis change.

Permissions: `sick_leave.view` and `sick_leave.manage` (director + manager).

---

## 7. Data retention

- Default: **keep attendance history forever**.
- Optional `attendance_history_retention_months` setting caps history; any value below 12 is treated as 12, so at least a full year is always retained.
- Pruning runs automatically after each import.

---

## 8. Applying the update

This platform is updated through the browser, not the command line. After uploading the changed files:

1. Sign in as a user with `settings.manage`.
2. Open **`/updates/v2-9-0`**.
3. Click **Apply v2.9.0 Update**.

The update step:
- ensures the attendance tables and public-holiday table exist,
- adds the new `attendance_days` columns (`is_early_leave`, `early_leave_minutes`, `is_weekend`),
- creates `employee_leave_allocations` and `employee_sick_records` if missing,
- seeds the four new leave/sick permissions (director + manager) and the three new settings,
- **rebuilds every existing attendance day with the new rules**,
- bumps the platform version to 2.9.0.

No artisan command is required. If the attendance page still shows old figures, re-run the update — it is safe to apply repeatedly.

---

## 9. Changed / new files

**New**
- `app/Services/AttendanceOverviewService.php`
- `app/Services/SickLeaveCycleService.php`
- `app/Http/Controllers/LeaveAllocationController.php`
- `app/Http/Controllers/SickLeaveController.php`
- `resources/views/leave/allocations.blade.php`
- `resources/views/leave/sick.blade.php`
- `resources/views/updates/v2_9_0.blade.php`

**Changed**
- `app/Services/AttendanceCsvImporter.php` (day rules, retention)
- `app/Http/Controllers/AttendanceController.php` (overview + profile)
- `app/Http/Controllers/UpdateController.php` (v2.9.0 update step)
- `app/Models/AttendanceDay.php` (new columns, labels, scopes)
- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/show.blade.php`
- `resources/views/layouts/app.blade.php` (navigation)
- `routes/web.php`
- `VERSION`, `CHANGELOG.md`
