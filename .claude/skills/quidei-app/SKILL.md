---
name: quidei-app
description: Project guide for the QUIDEI academy administration app (plain PHP + mysqli + Bootstrap). Use for any change to this codebase - new filters, UI changes, payouts, attendance, admissions, schedules, roles - before reading or editing files.
---

# QUIDEI academy administration app

The app runs live in production, and the owner tests changes on the live server.
**Change only what was asked.** Don't refactor, rename, reformat or "improve" nearby code.
Report other problems you notice in the reply instead of fixing them.

## Stack
- Plain PHP pages. There is no framework and no Composer.
  - Live runs **PHP 8** (the code uses `match`).
  - Local development uses Laravel Herd: `C:\Users\User\.config\herd\bin\php84\php.exe`.
  - `php74` also exists locally. Don't lint with it: it rejects `match`.
- MariaDB through `mysqli`. One global `$conn` comes from `includes/db.php`, using the utf8mb4 charset.
  - The local database is `quidei` with user `root` and no password.
  - Never commit or print the production credentials that are commented out in `db.php`.
- Frontend libraries are loaded from CDNs in `includes/header.php` and `includes/footer.php`:
  - Bootstrap 5.3 and Bootstrap Icons.
  - jQuery 3.7, DataTables 1.13 (with Buttons for Excel/PDF), Select2, SweetAlert2 and flatpickr.
  - jQuery and Bootstrap JS load in the **footer**. Page scripts must come after `include 'includes/footer.php'`.
- Theme: emerald (`#10b981`, `#059669`, `#064e3b`) and slate. Hero banners use emerald gradients.

## File conventions
- Each module is `page.php` (HTML plus JS) and `page_action.php` (AJAX endpoint returning JSON).
  - DataTables pages load their rows from the action file's default GET case as `{"data": [...]}`.
  - The other actions use `?action=...` or a POST `action` field and return `{"status": "success"|"error", "message": ...}`.
- A page starts with `ob_start(); include 'includes/header.php';` (which starts the session), then the role guard, then `require 'includes/db.php'`.
- **Every page and every action file has its own role guard**, for example:
  `if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], [...])) { ... exit; }`
  When you give a role access to a module, update the guard in both files **and** the card's `roles` in `dashboard.php`.
- Session keys: `loggedin`, `id` (the `users.id`), `name`, `role`.
- Shared helpers live in `includes/`, for example `includes/attendance_window.php`.
- Files named `* copy.php` and the `copy/` folder are old backups. Don't edit them, and leave them out of searches.

## Roles
The `users.role` enum: `admin`, `sales`, `demoAdmin`, `demoCordinator`, `mentor`, `teacher`, `accounts`, `supervisor`, `hrmanager`, `qualitycontroller`.
- **Supervisor:** a user referenced by `mentors.supervisor_id`. Supervisors only see their own mentors' data and are read-only in `student_schedule_action.php`.
- **Mentor:** has a `mentors` row (`mentors.user_id` points to `users.id`). Mentors are restricted to students where `students.mentor_id` is their `mentors.id`.
- **Known mismatch:** the Expense Audit pages check for the role `'accountant'`, but the enum value is `'accounts'`.

## Business flow and tables
1. **Enquiry** (`enquiries`): created by sales with status `new`.
2. **Demo** (`demo_assignments`): status moves through `demo_in_progress`, `demo_done` and `admission_confirmed`.
3. **Admission** (`admission_action.php` `finalize_admission`):
   - creates the `students` row (`status`: ready, active, break or inactive);
   - creates the first `schedules` row;
   - creates an `admissions` row;
   - sets the enquiry to `admission_finalized`.
4. **Schedule** (`student_schedule.php`): a student has many `schedules`. Each schedule has `total_hours`, `paid_hours` and `fee_amount`, plus:
   - `sessions`: date, `planned_hours`, `teacher_pay` per hour, and status `planned`, `done` or `missed`;
   - `attendance` and `schedule_exams`;
   - per student, `mentor_demos` (independent demos).
5. **Onboarding checklist:** `students.checklist_material`, `checklist_group` (WhatsApp group) and `checklist_teams` (MS Teams), each 0 or 1.

## Business rules (keep these consistent everywhere)
- **Completed hours:** `SUM(planned_hours)` over sessions with `status = 'done'`.
- **Fee status:**
  - total hours = 0: No Schedule;
  - paid ≥ total: Paid;
  - paid > 0: Partial;
  - otherwise: Pending.
- **Hours progress:**
  - total = 0: No Schedule;
  - completed ≥ total: Fully Completed;
  - otherwise: In Progress.
- **Exam status:** Completed when the schedule has at least one `schedule_exams` row.
- **Teacher pay:**
  - Sessions pay `planned_hours × sessions.teacher_pay`. The rate defaults to `grades.payment_per_hour`, matched with `grades.grade_name = students.class`.
  - Only admins, or users with `users.can_edit_teacher_payout = 1`, can change a session's pay.
- **Demo teacher pay:** `duration_hours × COALESCE(mentor_demos.teacher_pay, grades.payment_per_hour)`. Mentors enter the rate per hour, and NULL falls back to the grade rate.
- **Mentor fee:** `done sessions × mentors.rate_per_session + exams × mentors.rate_per_exam`.
  - `quidei_payouts.php` also counts `mentor_demos` as sessions.
  - `master_view.php` does not count demos.
- **Session hour limits:**
  - a schedule with paid hours can't go over its paid hours;
  - an unpaid first schedule allows 0 hours;
  - an unpaid later schedule allows 2.0 grace hours.
- **Month locks for non-admins:**
  - Sessions can't be added or deleted in past months.
  - Attendance can be marked for the current month. The previous month also stays open on day 1 of a new month: see `ATTENDANCE_GRACE_DAYS` in `includes/attendance_window.php`.
- **Master View:**
  - the "Academic year" filter is the **student admission year** (`students.created_at`), not the session date;
  - enquiries (`students.status = 'enquiry'`) are excluded;
  - there is one row per schedule, and student details appear only on the first row.

## Coding rules
- Use **prepared statements** with `bind_param` for anything from the request. Validate GET and POST values against allowlists. Escape output with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Keep the style of the file you're editing. In particular, match how its filters work:
  - **client-side:** a DataTables `$.fn.dataTable.ext.search` hook (Admissions, My Students);
  - **server-side:** GET parameters (Master View, Mentor Activity).
- Filter UI pattern: inline `form-select` controls that auto-submit, and/or an "Advanced Filters" Bootstrap modal with Clear All and Apply buttons.
- When you add a filter to a page with a CSV/Excel export, the export must keep the filter. Master View builds `$downloadUrl` with `http_build_query`.
- Schema changes:
  - Prefer additive, nullable columns, so existing rows keep their old behaviour.
  - Give the exact `ALTER TABLE` SQL in your reply. The owner must run it on live **before** uploading code that depends on it.
  - Apply the change to the local database too, so you can test.

## Verifying a change
1. Lint every changed file: `& "C:\Users\User\.config\herd\bin\php84\php.exe" -l <file>`.
2. Test new SQL against the local database with a script in the scratchpad that runs `chdir('d:/HERD/quidei'); require 'includes/db.php';`. Wrap test writes in a transaction and roll it back.
3. To call an action endpoint without a browser:
   - In a scratchpad script, call `session_start()`, set `$_SESSION = ['loggedin'=>true,'id'=>1,'name'=>'Test','role'=>'admin']`, set `$_SERVER['REQUEST_METHOD']` and `$_GET`, then `include` the endpoint.
   - Decode the output buffer from a shutdown function. Piping through PowerShell corrupts JSON.
   - Write scripts with `[IO.File]::WriteAllText` and no BOM. PowerShell 5.1's `Set-Content -Encoding utf8` adds one.
4. Say plainly what you tested and what you didn't, for example "not clicked through in a browser".

## Reply checklist
- What changed, per page, in plain language for a non-technical admin.
- The list of files to upload, including any new file under `includes/`.
- Any SQL to run first on live.
- Behaviour notes or caveats, such as the server timezone for date rules or which date a filter uses.
