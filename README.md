# EduSphere School Management System

A production-oriented school management system built with **Laravel 12**, **MySQL 8**, **Blade**, **Tailwind CSS**, **Alpine.js** and **Chart.js**. It covers admissions, student records, fees and receipts, attendance, examinations, results, finance, payroll, notices, reports, settings and role-based access control.

The interface uses a dark-purple design system, responsive layouts, printable documents and database-driven dashboards. All financial data is stored as decimals and financial history is preserved through reversals and soft deletion.

---

## Contents

1. [Feature overview](#feature-overview)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Environment configuration](#environment-configuration)
5. [Database setup and seeding](#database-setup-and-seeding)
6. [First administrator](#first-administrator)
7. [Demo data](#demo-data)
8. [Development server](#development-server)
9. [Teacher users, assignments and marks](#teacher-users-assignments-and-marks)
10. [Student and parent portals](#student-and-parent-portals)
11. [Result schemes, publication and corrections](#result-schemes-publication-and-corrections)
12. [Partial fee payments](#partial-fee-payments)
13. [Payroll installments](#payroll-installments)
14. [Public website and CMS](#public-website-and-cms)
15. [Tests](#tests)
16. [Deployment](#deployment)
17. [Backup and restore](#backup-and-restore)
18. [WhatsApp reminders](#whatsapp-reminders)
19. [Roles and permissions](#roles-and-permissions)
20. [Financial and academic rules](#financial-and-academic-rules)
21. [Project structure](#project-structure)
22. [Troubleshooting](#troubleshooting)

---

## Feature overview

### Students and admissions
- Admission enquiries with follow-up notes and conversion to admission.
- Full admission form: student identity, guardian, class/section enrolment and optional fee assignment.
- Every admitted student receives a permanent, unique Student ID in the format `year + class + roll` (for example `202601001`). `admission_no` remains a separate document reference.
- Admission automatically provisions the student portal account, associates the admission photo and links the parent/guardian account. Siblings with the same guardian mobile share one parent account.
- Student directory, profile, edit form and passed-out/archived register.
- Session promotion with per-student decisions.
- Session report with CSV export.
- Student status changes and soft deletion; historical records remain available.

### Fees
- Fee categories and fee structures with monthly, one-time, annual and exam frequencies.
- Per-student fee assignments; monthly items generate one due row per month.
- Optional fee items and structure assignment for a class or one student.
- Fee collection with receipt numbers, split allocations and multiple payment methods.
- Partial payments for any amount up to the outstanding balance, with explicit oldest-first allocation.
- Derived fee-row statuses: `pending`, `partial`, `paid`, `waived` and overdue detection by due date.
- Quick collect with automatic oldest-first allocation.
- Waivers that preserve the original amount for audit.
- Reversals (cancellation or refund) that keep the original payment and create an audit record.
- Printable A4 receipts, student collection ledger and fee deletion/reversal log.
- Collection, dues, partial-student, category-wise and collector/receipt reports.
- WhatsApp reminders through a real configured provider.

### Academics
- Attendance register by class, section and date, with present/absent/late/excused states.
- Attendance reports and CSV export.
- Subjects, class-subject mapping and configurable grade rules.
- Reusable grading schemes with session, exam-type and default scoping, including seeded SSC and HSC schemes.
- Optional per-paper assessment components (theory, practical, MCQ, etc.) whose marks total into the subject result.
- Teacher assignments per session, class, section and subject, with conflict detection and expiry.
- Teacher dashboard restricted to the signed-in teacher's own assignments.
- Exams, exam schedules, marks entry and absent handling.
- Special result statuses: absent, exempt, withheld, incomplete and disqualified.
- Marks workflow `draft` → `submitted` → `approved`, with reasoned corrections and audit metadata.
- Server-side marks authorization for entry, submission, approval, correction and every API/form path.
- Explicit result publication with approved-mark checks, locking, immutable student snapshots and audited reopening.
- Class merit lists, printable/PDF result sheets and UTF-8 CSV result exports.
- Session exam options and result reports.

### Finance and payroll
- Expense categories and expense register with receipt uploads.
- Expense filters, category breakdown and soft deletion.
- Staff directory, departments, designations and versioned salary structures.
- Monthly payroll generation, processing and salary payment recording.
- Multiple salary installments per payroll with `partially_paid` and fully `paid` states.
- Overpayment prevention enforced by row locks, summed installments and a positive-amount CHECK constraint.
- Reversal of a specific installment while preserving the original payment; corrected payments can follow.
- Cancellation of a payroll that has no installments, retaining the row for audit and excluding it from totals.
- Printable salary slips and yearly salary reports with CSV export.
- Income vs expense reporting that excludes reversed payments.

### Communication and administration
- Draft, scheduled, published and archived notices with audience targeting.
- Academic sessions, classes, sections and user accounts.
- Role and permission management, audit log, school settings and password change.
- Automatic staff and administrator accounts with unique usernames, optional email, normalized mobile numbers and the configured default password.
- CSV exports for reports, salary, attendance and session summaries.

### Student and parent portals
- Dedicated `/student` and `/parent` portals using the same Laravel user, session and role system.
- Student accounts are explicitly linked to one student profile; parent accounts may be linked to several children.
- Students sign in with their Student ID, username or email. Parents sign in with their guardian mobile number or username.
- Record ownership is checked on the server for every request. Parents receive `403` for a child they are not linked to.
- Students see only their own profile, attendance, fees, published results and downloads.
- Parents select between linked children and see the same read-only academic and fee information.
- Portal logins reject incompatible roles, inactive accounts and unlinked/invalid accounts, and failed attempts are audit-logged.
- Every portal has a **Change password** page so the default password can be replaced after first sign-in.
- Fees show charges, discounts, completed payments and reversals from the existing ledger; no portal route can create a payment.

### Result publication and integrity
- Results are computed from approved exam marks and an explicit grading scheme.
- Publishing is a separate, audited action. It refuses to run when marks are missing, draft or submitted.
- Every published student result is stored as an immutable `result_snapshots` row containing the result data and a copy of the grade boundaries in force at publication.
- Portals read snapshots only; changing a mark or grading scheme later does not silently rewrite a published result sheet.
- Corrections to finalized/published marks require a reason, create a `result_corrections` row and are audit-logged.
- A correction on a published result keeps the mark approved and regenerates the snapshot while reusing the original grading rules.
- Reopening (unpublishing) requires a reason, immediately hides results from portals and preserves snapshots for audit.
- Result sheets print to PDF through the browser; the class result page also exports a UTF-8 CSV.

### Public website and CMS
- Responsive Bengali public website at `/` with the original `school-web` visual identity.
- Pages for home, about, administration/principal/president messages, faculty, academics, admission information, admission application, notices, news, events, academic calendar, gallery, downloads, contact and privacy.
- A dedicated **Admin → Website** area with an individual function for each homepage section: slider, institution introduction, administration message, principal message, president message, academic corner, news, events and gallery.
- Editable public identity: uploaded logo, school names/tagline, topbar text, header call-to-action, footer introduction, copyright and social links.
- Four header layouts, four footer layouts and four colour themes, selectable without editing code.
- Database-backed published notices, news, events, gallery items and downloads with draft/archived states.
- Admission applications enter the staff enquiry workflow; contact messages are stored in an inbox.
- Admin content management for website content and contact messages, protected by dedicated permissions.
- Immutable content revisions with restore, optional deletion, SEO/meta fields and page templates.
- Server-side HTML sanitisation for rich content; legacy plain-text content stays escaped.
- Media library with validated uploads, metadata editing, safe delete guards and preview/copy helpers.
- Database-managed header/footer/drawer menus with active/inactive items and a built-in fallback.
- Gallery lightbox with keyboard/previous/next/close controls.
- `sitemap.xml` and `robots.txt` for search engines.
- Public and administrative authentication remain separate: the public site is read-only for guests, and every CMS action requires an authenticated, permitted user.

---

## Requirements

| Component | Minimum |
|---|---|
| PHP | 8.2 (8.3 recommended) |
| Composer | 2.x |
| MySQL | 8.0 |
| Node.js | 18 LTS or newer |
| Web server | Nginx or Apache with `public/` as document root |

Required PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `ctype`, `json`, `tokenizer`, `xml`, `curl`, `gd` or `imagick` (for image handling).

On Windows with Laragon, PHP, Composer and MySQL are usually available under `C:\laragon\bin`. Add PHP to `PATH` or call the binaries with full paths.

---

## Installation

```bash
git clone <your-repository-url> school
cd school

composer install
cp .env.example .env
php artisan key:generate
```

Create the database (MySQL 8, `utf8mb4`):

```sql
CREATE DATABASE school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Configure `.env`, then run:

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
npm install
npm run build
```

`php artisan storage:link` is required for student photos, staff photos, expense receipts and website uploads.

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
npm.cmd install
npm.cmd run build
```

---

## Environment configuration

At minimum, set:

```dotenv
APP_NAME="EduSphere School"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Dhaka

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=school
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public

DEFAULT_USER_PASSWORD=School@12345
```

For production, use `APP_ENV=production`, `APP_DEBUG=false`, an HTTPS `APP_URL`, a dedicated database user and a strong `APP_KEY`. Never commit `.env`.

### Administrator seeding variables

```dotenv
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=use-a-strong-password
```

`CoreDataSeeder` refuses to seed a production administrator with a blank password.

### Automatic account password

`DEFAULT_USER_PASSWORD` is the initial password assigned to accounts created automatically by admission, staff creation or user creation. It is read through `config('school.default_password')` and falls back to `School@12345`.

- Change it before creating real accounts. Existing passwords are not rewritten when the setting changes.
- Tell students, parents and staff to use **Change password** after their first sign-in.
- The temporary password is shown only in the one-time admission success message; it is never stored in readable form.
- Manual portal-account linking under **Admin → Portal Accounts** can still set an explicit initial password (minimum 10 characters).

### Demo data flag

```dotenv
SEED_DEMO=false
DEMO_PASSWORD=
```

`SEED_DEMO=true` loads the large demo dataset. **Never enable demo seeding in production.**

---

## Database setup and seeding

Run migrations and seed the canonical data:

```bash
php artisan migrate --seed
```

The default `DatabaseSeeder` runs:

1. `RolePermissionSeeder` — permissions and roles from `config/school.php`.
2. `SettingSeeder` — school identity, currency and locale defaults.
3. `CoreDataSeeder` — administrator, academic sessions, classes, sections, subjects, fee categories/structures, grade rules, reusable grading schemes, default website menus, departments, designations and expense categories.

Optional demo data:

```bash
php artisan db:seed --class=DemoDataSeeder
```

Or set `SEED_DEMO=true` and run `php artisan migrate:fresh --seed`.

To rebuild from scratch **in development only**:

```bash
php artisan migrate:fresh --seed
```

This destroys all data in the configured database.

### MySQL features used

- **InnoDB row locks.** Payment, salary-installment, reversal and payroll-cancellation paths use `SELECT ... FOR UPDATE` inside transactions. Keep the default InnoDB engine.
- **CHECK constraints.** `salary_payments.amount > 0` relies on MySQL 8.0.16+ enforcing CHECK constraints. Test the constraint after provisioning a new server.
- **Native enums.** Payroll status includes `pending`, `processed`, `partially_paid`, `paid` and `cancelled`. The later migration only widens the enum, so existing rows are preserved.
- **Generated-column replacement.** The old single-payment unique guard was removed on purpose. The replacement is documented in [Payroll installments](#payroll-installments).
- **Additive migrations.** New tables and columns use foreign keys, indexes and unique keys. No production migration truncates or resets data. Backfills should be separate, reviewed commands.
- **Rollback caveat.** Rolling back `2026_01_02_000001_support_salary_installments` re-adds the old one-completed-payment-per-payroll unique guard. On a database that already contains multiple installments, that rollback will fail by design. Restore from backup or reconcile the data first; do not force it on production. The status-enum rollback converts `cancelled` rows back to `processed` before narrowing the enum.

---

## First administrator

1. Set `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env` before seeding.
2. Run `php artisan migrate --seed`.
3. Sign in with those credentials.
4. Open **Account → Password Change** and rotate the password if the account was seeded with a shared value.
5. Create role-specific users under **Admin → Users**.
6. Disable or change any demo accounts before going live.

The seeded administrator receives the `super-admin` role and bypasses individual permission checks. All other users receive permissions through their role.

---

## Demo data

When `SEED_DEMO=true`, `DemoDataSeeder` creates:

- 50 active students across classes and sections, plus archived students.
- Guardians, enrolments and per-student fee assignments.
- Completed payments, attendance, exams, finalized/published marks, result snapshots, expenses, payroll and notices.
- Teacher, accountant and registrar accounts.
- One linked student portal account and one parent account linked to two children.

`DemoPortalSeeder` is safe to run repeatedly. It adds the portal accounts and published snapshots to an existing demo database without duplicating students or financial records:

```bash
php artisan db:seed --class=DemoPortalSeeder
```

Default local demo credentials:

| Role | Email | Password |
|---|---|---|
| Super administrator | `admin@edusphere.test` | `Admin@12345` |
| Teacher | `teacher@edusphere.test` | `Admin@12345` |
| Accountant | `accountant@edusphere.test` | `Admin@12345` |
| Registrar | `registrar@edusphere.test` | `Admin@12345` |
| Student portal | `student@edusphere.test` | `Admin@12345` |
| Parent portal | `parent@edusphere.test` | `Admin@12345` |

These are **local demo credentials only**. Override `DEMO_PASSWORD` and `ADMIN_PASSWORD`, or delete demo users, before production.

---

## Development server

```bash
php artisan serve
```

In a second terminal:

```bash
npm run dev
```

Open `http://127.0.0.1:8000`.

A combined development command is also available:

```bash
composer dev
```

It runs the PHP server, queue listener, log viewer and Vite. On Windows, use `npm.cmd` if `npm` resolves to the wrong executable.

---

## Teacher users, assignments and marks

### Link a teacher account to a staff profile

Assignments and marks are authorised against a **staff profile**, not just a user role. A teacher user must be linked to an active staff record:

1. Create the staff profile under **Staff → Add staff member**.
2. Create or edit the account under **Admin → Users**, choose the `Teacher` role, and select the profile in **Linked staff profile**.
3. The link is one-to-one. A profile already linked to another account is rejected, and editing the account can release the link without deleting the staff record.

The demo teacher is linked by `DemoDataSeeder`. If a teacher can sign in but sees no assignments, check that the user is linked to an active staff member and that the staff status is `active`.

### Assignments

Open **Teacher Assignments** (`/teacher-assignments`) as an administrator:

- Each assignment records academic session, class, section, subject, teacher, start/end dates and an active flag.
- Identical active assignments are rejected.
- A second teacher on the same class/section/subject is rejected unless **Admin → Settings → Allow co-teaching** is enabled.
- Deactivating an assignment keeps the historical row. Assignments are never deleted, so past marks remain attributable.
- The admin list supports session, class, teacher and active-state filters, and staff profiles show their assignments.

The teacher dashboard at `/teacher` only lists the signed-in teacher's own effective assignments: classes, subjects, students, exam schedule, marks state and class results. A teacher without a link or active assignment sees an empty state, not school-wide data.

### Marks authorization and workflow

Marks are protected on the server in `MarksAuthorizationService`; hiding buttons in the UI is not the control. Every entry, save, update, submit, approve, correction, delete and bulk path checks:

- the user is linked to an active staff record;
- an effective assignment exists for the exam paper's session, class, section and subject;
- the exam belongs to the current session and the paper matches the selected class/subject;
- the student is enrolled in the relevant session, class and section;
- the mark is within `0..full_marks` and follows the absent rules;
- the actor holds the required permission (`marks.enter`, `marks.submit`, `marks.approve` or `marks.correct`);
- draft marks can be edited; submitted or approved marks need an authorised correction;
- a published or locked exam cannot be changed unless the actor has correction rights.

A tampered request for another teacher's paper is rejected with HTTP 403 **before** any mark is written or updated. Feature tests cover this with manipulated `examination_subject_id` values.

Workflow states:

| State | Meaning |
|---|---|
| `draft` | Entered by a teacher or administrator; editable by the owner. |
| `submitted` | Teacher has submitted the paper; further teacher edits are blocked. |
| `approved` | Administrator approved the paper; only `marks.correct` can reopen it. |

Marks store `entered_by`, `updated_by`, `submitted_by`, `approved_by` and the corresponding timestamps. Submission, approval and corrections are written to the audit log.

A correction to submitted/approved marks on an unpublished exam resets the mark to `draft` so it must be resubmitted and approved again. A correction to an already published result remains approved and refreshes the published snapshot using the original grading boundaries; the reason is stored in `result_corrections` and the audit log.

---

## Student and parent portals

### Login pages

| Portal | Sign in with | Login | Dashboard |
|---|---|---|---|
| Student | Student ID, username or email | `/student/login` | `/student/dashboard` |
| Parent | Guardian mobile, username or email | `/parent/login` | `/parent/dashboard` |
| Teacher | Username or email | `/teacher/login` | `/teacher` |
| Administration | Username or email | `/login` | `/dashboard` |

The login field is labelled for each area but still posts as `email` for backward compatibility. Mobile numbers are normalized before matching, so `+880 1712-345678`, `8801712345678` and `01712345678` resolve to the same parent account. Portal login lookup is role-scoped, so a phone number shared with a staff record cannot open a parent portal.

Portal accounts use the normal `users` table but have dedicated `student` or `parent` roles. The `portal` middleware rejects any other role, and every controller re-checks record ownership.

### Automatic accounts

Admission creates the portal identities in the same database transaction as the student record:

1. A unique Student ID is generated from the academic-session year, class number and roll, for example `202601001`.
2. The student account uses the Student ID as its username and receives the admission photo as its avatar.
3. The parent account uses the normalized guardian mobile number as its username and is linked to the new student.
4. If a sibling already has a parent account with the same normalized mobile number, that account is reused and the new child is linked to it.
5. Newly created accounts receive `DEFAULT_USER_PASSWORD` and are shown once in the admission success message. A reused sibling parent account keeps its existing password, and the message says so.

Staff and user creation follow the same password rule. Staff can be given a username and optional email; a unique username is generated when it is left blank. Administrators can leave the password blank during user creation to use the default password.

### Linking existing accounts

Most accounts are created automatically. Use **Admin → Portal Accounts** with `portals.manage` to attach an existing, unlinked student or guardian:

1. Choose **Student** or **Parent / Guardian**.
2. Select the student profile. Parents may link the same account to several children later.
3. Set name, email and an initial password. A minimum of 10 characters with confirmation is required for new accounts created here.
4. Sign in at the matching portal login page.

Student accounts are one-to-one with a student profile. Parent accounts are one-to-many but only explicitly linked children are visible. Requesting another student's URL returns `403`, not a blank page or another child's data.

### Password changes

Every student, parent and teacher portal includes **Password** in its navigation. The page verifies the current password and requires a confirmed new password of at least eight characters. The session remains active after the change; a later sign-in must use the new password. Administrators can use **Change password** from the account menu or sidebar.

### What each portal shows

- The student's official Student ID, admission number, class/section/roll and guardian contact.
- The portal login username and mobile number on the account/profile page.
- Published results only, read from `result_snapshots`.
- Attendance records and percentages for the current session.
- Fee assignments, balances, completed payments, reversals and printable receipts.
- Published notices/downloads and exam schedules.

Portals are read-only. They never create payments, marks, results or admissions, and they never display draft or archived website content.

---

## Result schemes, publication and corrections

### Grading schemes

Open **Academics → Grading schemes**. Each scheme has:

- Name, optional examination type (`ssc`, `hsc`, `term`, etc.) and optional academic session.
- Active/default flags and a description.
- Contiguous grade boundaries from `0%` to `100%` with grade, label, grade point and pass/fail flag.

The first 0–100 boundary must start at `0.00` and the last must end at `100.00`; gaps and overlaps are rejected. Schemes can be copied from the global grade rules or another scheme.

The seeded schemes are `Institution Standard 2026`, `SSC Standard 2026` and `HSC Standard 2026`. Assign a scheme on the exam form. If none is assigned, the result service falls back to a matching active scheme and then the global grade rules.

### Assessment components

A paper can optionally be split into components such as Theory 70 + Practical 30. Add the components on the examination page: their full marks must add up to the paper’s full marks, and each component has its own pass mark.

- Marks entry shows one input per component and derives the subject total server-side.
- A component above its own maximum is rejected; a partially filled component set is rejected unless the student is absent or has a special status.
- Component breakdowns are preserved in result snapshots and printed on result sheets.
- Papers without components keep the existing single-total marks-entry workflow.

### Special statuses

Marks entry supports absent, exempt, withheld, incomplete and disqualified:

- **Exempt** is excluded from totals and grade-point averaging.
- **Absent** receives zero points and fails that subject.
- **Withheld**, **Incomplete** and **Disqualified** force a non-pass overall result and are recorded on the sheet.
- Missing mark rows produce an **Incomplete** result, not a false pass.

### Publishing

1. Enter every mark and move each paper through `draft` → `submitted` → `approved`.
2. Open the examination and confirm there are no pending marks.
3. Click **Publish & lock results**.

Publication fails if there are no subjects, no marks or any unapproved mark. On success the exam records `result_published_at`, `result_locked_at`, publisher/locker and a snapshot count. One immutable snapshot is created per active student, containing the result data and the grade-boundary copy.

### Corrections and reopening

- Correcting a finalized mark requires a reason. The actor needs `marks.correct`.
- A correction creates a `result_corrections` row and an audit entry.
- Published results are refreshed automatically with the original snapshot rules.
- **Unpublish and reopen** requires a reason, hides results from portals immediately and keeps the snapshots for audit.
- Re-publishing after a correction reuses the original snapshot rules, even if the live scheme has since changed.

Result sheets print cleanly to PDF. The class result page includes a **CSV export** with rank, student identity, subject totals, GPA, grade, result and special statuses.

---

## Partial fee payments

Use **Fees → Collection**, choose a student, and enter any amount from `0.01` up to the outstanding balance. The payment:

- is stored as a completed payment with a unique receipt number, date, method (`cash`, `bank` or MFS), reference, collector and notes;
- is allocated explicitly to outstanding fee rows; quick collection uses the order configured under **Admin → Settings → Fees** (oldest due first by default);
- can cover several fee rows in one receipt;
- updates each row to `partial` or `paid` and leaves the rest outstanding;
- is rejected if the amount is zero, negative, non-numeric or greater than the outstanding balance.

Receipts are printable. The student ledger shows the original charge, discounts, every payment and reversal, and the current balance. Rows become `overdue` when the due date has passed and a balance remains.

Reversing a payment never deletes it. A reversal row is added, the original is marked `reversed`, allocations are released and row statuses are recalculated. Reversed amounts are excluded from collection totals and listed separately.

Relevant reports:

| Report | Purpose |
|---|---|
| Collection | Completed receipts, today's collection, reversals and a receipt list for the period. |
| Outstanding Dues | Pending/partial balances, oldest first, with overdue counts. |
| Partial Payment Students | Students with partly paid rows, paid and outstanding totals. |
| Category-wise Collection | Completed allocations grouped by fee category. |
| Collector & Receipt Report | Receipts grouped by collecting staff member, including reversed totals. |

> The collection form relies on server-side balance checks, CSRF protection and unique receipt numbers. It does not implement a separate idempotency token, so an identical double submission is processed as a second payment if balance remains. Review the ledger after a network retry and reverse a duplicate through the normal reversal workflow.

---

## Payroll installments

A processed payroll can be paid in several installments until the net salary is fully settled:

1. Generate and process the payroll.
2. Open the payroll and record an installment amount (defaults to the outstanding balance).
3. Repeat for later installments. Each one is a separate `salary_payments` row with its own date, method, reference and note.
4. The payroll status becomes `partially_paid` while a balance remains and `paid` only when the sum of completed installments equals the net salary.

Safety rules:

- The payroll row is locked with `SELECT ... FOR UPDATE` while paying.
- The sum of completed installments is checked against the net salary inside the transaction; overpayment is rejected.
- A MySQL `CHECK (amount > 0)` constraint rejects non-positive installments at the database level.
- Reversing one installment preserves the original row, marks it `reversed`, recalculates the payroll status and allows a corrected payment.
- Reversing the same installment twice is rejected. Reversal records the actor, reason and timestamp.
- A pending or processed payroll with no completed installment can be cancelled with a reason. The row remains for audit (`cancelled`), is excluded from payable and salary-report totals, and cannot be processed or paid afterwards. Payrolls with installments must be reversed first.
- Printed slips and payroll reports show paid and outstanding amounts, and reversed installments are excluded from totals.

The previous generated-column guard that allowed only one completed payment per payroll was intentionally replaced by the lock + summed-installments + CHECK design, because installments require multiple completed rows. The replacement is covered by feature tests for partial payment, overpayment and reversal.

---

## Public website and CMS

The original static site was supplied in `school-web`. It has **not** been overwritten: a byte-for-byte copy is kept at `public/website/`. The live public site is a Blade application that reuses the same visual language and assets, so content can come from the database while the design stays faithful.

### Public routes

| URL | Page |
|---|---|
| `/` | Home |
| `/about` | About the school |
| `/administration-message` | Administration message |
| `/principal-message` | Principal's message |
| `/president-message` | President's message |
| `/faculty` | Teachers and staff |
| `/academics` | Academic programs |
| `/admission` | Admission information |
| `/admission/apply` | Admission application form |
| `/notice-board` | Published notices |
| `/notice-board/{notice}` | Notice detail |
| `/news` | News and events articles |
| `/news/{slug}` | Article detail |
| `/events` | Events |
| `/calendar` | Academic calendar |
| `/gallery` | Gallery |
| `/downloads` | Downloads |
| `/contact` | Contact form |
| `/privacy` | Privacy policy |
| `/sitemap.xml` | Sitemap |
| `/robots.txt` | Crawler rules |

`public/robots.txt` is a static file and takes precedence over the route on a real web server. Keep it aligned with the intended public/private rules when changing the route output.

### Website administration

**Admin → Website** is the content dashboard. Each public section has its own function and route, so editors do not have to hunt through a generic content list:

| Admin function | Public result |
|---|---|
| Slider | Homepage hero slides, images, calls-to-action and ordering |
| Institution introduction | About page introduction and homepage introduction block |
| Administration message | `/administration-message` and homepage message card |
| Principal message | `/principal-message` and homepage message card |
| President message | `/president-message` and homepage message card |
| Academic corner | Homepage academic highlights and programme links |
| News | Published news list and article detail pages |
| Events | Published event list and homepage cards |
| Gallery | Gallery page, homepage gallery strip and lightbox |
| Pages | Additional public pages with revisions and templates |
| Downloads | Public download list |
| Faculty | Teacher and staff profiles |

Content is `draft`, `published` or `archived`; only published content is public. Drafts, archived items and staff-only notices are never exposed by public routes, including direct detail URLs. Changes to rich text are sanitized on the server and keep revisions for restore.

### Design, header and footer

Open **Admin → Website → Design & logo** to edit the public identity without changing code:

- School name in Bangla and English, tagline, initials/logo mark, topbar text and header call-to-action.
- Logo upload with safe image validation; removing it falls back to the initials mark.
- Header layouts: **Classic blue**, **Centered crest**, **Split banner** and **Minimal compact**.
- Footer layouts: **Classic four columns**, **Wide columns**, **Compact bar** and **Accent panel**.
- Colour themes: **Navy & gold**, **Emerald & sand**, **Maroon & cream** and **Royal purple**.
- Footer introduction, copyright line (`:year` is replaced with the current year), footer note and Facebook/X/YouTube links.

Header, footer and theme choices are stored in `school_settings` and rendered as CSS classes on the public layout. Menu structure is managed separately under **Website → Menu builder**. These public branding values are separate from the `school_name` used on receipts and reports, so the website can differ without rewriting financial documents.

### Other public behaviour

- Contact address, phone and email reuse the main school settings. Replace demo placeholders with the school's real contact details before going live; nothing on the public site should advertise invented data.
- Admission applications create an enquiry (`source = Website`) in the normal **Admission Enquiries** workflow for staff follow-up. They do not create a student directly.
- Contact submissions are stored in **Admin → Messages**, validated, rate-limited (`10` per minute per IP) and protected by a honeypot. No student, guardian, staff, salary or unpublished record is exposed to the public site.
- Images and files uploaded through the CMS are validated by type and size and stored under `storage/app/public`; run `php artisan storage:link` and back up that directory.
- The gallery opens from its card and each image opens in a keyboard-accessible lightbox with previous/next/close controls.
- Header CTA and content links accept relative paths or `http(s)`, `mailto` and `tel` URLs only.
- Only real school data should be entered. Demo names, prices and statistics must not be published as if they were official.

Permissions: `website.view`, `website.manage`, `website.publish`, `website.media` and `website.menus`. The Teacher and Staff roles do not receive them by default; use an administrator or Website Administrator account for publishing.

---

## Tests

Feature tests use a **separate MySQL database** and `RefreshDatabase`, which drops and recreates tables. Never point the test database at production or at the live `school` database.

### Create the test database

```sql
CREATE DATABASE school_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`phpunit.xml` sets `DB_DATABASE=school_test`. Override it there if your environment uses a different test database name.

### Run tests

```bash
php artisan test
```

Coverage includes:

- Guest redirects, login, and role-based authorization.
- Admission creation with guardian and enrolment.
- Fee collection, receipt access, partial payment, reversal and audit preservation.
- Salary installments, overpayment rejection, double-reversal protection and corrected payments.
- Attendance upsert per student and day.
- Merit-list ranking, pass/fail and GPA handling.
- Dashboard metrics from live financial data.
- Report pages and CSV exports, including partial, category-wise and collector reports.
- Fee structure discount validation.
- Teacher assignment duplicate/conflict detection and deactivation instead of deletion.
- Negative marks-authorization tests: a teacher cannot write to another teacher's paper with tampered IDs.
- Marks workflow `draft` → `submitted` → `approved`, locking and reasoned correction.
- Teacher dashboard isolation and staff–user linking.
- Portal role boundaries, guest redirects, explicit parent–child links and rejection of unlinked/inactive accounts.
- Result publication refusal for pending marks, immutable snapshots, assessment components, special statuses, reasoned correction with snapshot refresh, audited unpublishing/republication, portal result visibility and UTF-8 CSV export.
- Public website pages, draft/archived content hiding, contact/admission submission and CMS permission checks.
- CMS HTML sanitisation, legacy plain-text escaping, revisions/restore, media upload/safe delete, custom menus with fallback and gallery lightbox/delete.
- Automatic admission accounts with permanent Student IDs, sibling parent-account reuse, Student-ID and mobile login, staff/user default accounts, portal password changes, website section routes, design persistence and editable header/footer rendering.

`tests/Feature/PageSmokeTest.php` renders every authenticated page as the administrator using seeded demo data. It is slower than the focused workflow tests but catches view/controller mismatches.

The last verified run on this codebase produced **42 passing tests and 642 assertions**. Pint, `view:cache`, `route:cache`, `config:cache` and the production Vite build also completed successfully. A zero-exit full run is still required before deployment; do not treat this document as a substitute for running the suite in your environment.

Run a single suite:

```bash
php artisan test --filter=CoreWorkflowTest
php artisan test --filter=PageSmokeTest
```

### Build before testing views

```bash
npm run build
```

If Vite assets are missing, Blade views that use `@vite` will fail with a manifest error.

---

## Deployment

### 1. Prepare the server

- Install PHP 8.2+/8.3 with the extensions listed above.
- Install MySQL 8 and create a database with `utf8mb4`.
- Install Composer and Node.js.
- Point the web server document root at `public/`, never at the project root.

### 2. Deploy the code

```bash
git clone <your-repository-url> /var/www/school
cd /var/www/school

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Set production values in `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://school.example.com
LOG_LEVEL=error
```

### 3. Build and migrate

```bash
npm ci
npm run build

php artisan migrate --force
php artisan db:seed --force
php artisan storage:link

php artisan optimize
```

`php artisan optimize` caches configuration, routes, events and views. Re-run it after configuration changes. To clear caches:

```bash
php artisan optimize:clear
```

### 4. File permissions

The web server user needs write access to:

```text
storage/
bootstrap/cache/
```

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

### 5. Queue and scheduler

The application uses the database queue and session/cache drivers. Start a queue worker if you enable queued jobs:

```bash
php artisan queue:work --tries=3 --timeout=90
```

Run Laravel's scheduler every minute if scheduled tasks are added:

```cron
* * * * * cd /var/www/school && php artisan schedule:run >> /dev/null 2>&1
```

### 6. Web server

Nginx example:

```nginx
server {
    listen 443 ssl http2;
    server_name school.example.com;
    root /var/www/school/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable HTTPS, HSTS and secure cookies. Ensure `.env`, `storage/logs` and database dumps are not web-accessible.

### 7. Post-deployment checks

- Sign in as the administrator.
- Open every sidebar module.
- Print a fee receipt, result sheet and salary slip.
- Export a report and salary CSV.
- Mark attendance and verify the dashboard.
- Confirm WhatsApp provider status if reminders are required.
- Verify database and `storage/app/public` backups run.

---

## Backup and restore

Financial and academic history is only as safe as its backups. Back up **both** the database and uploaded files.

### MySQL backup

```bash
mysqldump -u root -p \
  --single-transaction \
  --routines \
  --triggers \
  --default-character-set=utf8mb4 \
  school > school-$(date +%F-%H%M).sql
```

Windows:

```powershell
& "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe" -u root --single-transaction --routines --triggers --default-character-set=utf8mb4 school > school-backup.sql
```

### Uploaded files

```bash
tar -czf storage-$(date +%F).tar.gz storage/app/public
```

Windows:

```powershell
Compress-Archive -Path storage\app\public -DestinationPath storage-public-backup.zip
```

### Restore

1. Put the application in maintenance mode:

   ```bash
   php artisan down
   ```

2. Restore the database into an empty database:

   ```bash
   mysql -u root -p school < school-2026-10-23.sql
   ```

3. Restore `storage/app/public` and run:

   ```bash
   php artisan storage:link
   php artisan optimize:clear
   ```

4. Bring the application back:

   ```bash
   php artisan up
   ```

5. Sign in and verify fee totals, receipts, payroll and reports.

Test restores regularly on a staging database. A backup that has never been restored is unverified.

---

## WhatsApp reminders

WhatsApp reminders use a real provider and never report a fake success. If no credentials are configured, sending throws a validation error and no message is recorded.

Configure under **Admin → Settings → WhatsApp Reminders**:

| Setting | Purpose |
|---|---|
| Provider | `Disabled`, `Meta WhatsApp Cloud API` or `Generic HTTP gateway` |
| API base URL | Meta: `https://graph.facebook.com/v20.0`; Generic: full HTTPS endpoint |
| Phone number ID | Meta Cloud API sender phone number ID |
| Template name | Meta approved template name, or a label for a generic gateway |
| Template language | Meta template language code, for example `en` |
| Access token | Meta/system user token or gateway bearer token |

The token is stored in the database, is never displayed again after saving, and is only replaced when a new value is submitted.

### Meta Cloud API

1. Create a Meta app and WhatsApp Business account.
2. Add a phone number and obtain its **phone number ID**.
3. Create and get approval for a fee-reminder template with four body parameters in this order:
   1. Guardian name
   2. Student name
   3. Outstanding amount (formatted with currency)
   4. Academic session name
4. Save the provider, base URL, phone number ID, template name, language and access token.
5. Use **Fees → WhatsApp Reminder** to send to students with outstanding dues.

The service posts to:

```text
{base_url}/{phone_number_id}/messages
```

with a template payload and `Authorization: Bearer <token>`. A non-2xx response surfaces the provider error and does not create a success audit entry.

### Generic HTTP gateway

The gateway receives JSON with:

```json
{
  "to": "8801700000000",
  "message": "Dear guardian, this is a fee reminder...",
  "template": "fee_reminder",
  "student_id": 12,
  "amount_due": 3500,
  "session": "2026"
}
```

and an `Authorization: Bearer <token>` header. It must return a 2xx response. Successful sends are written to the audit log with the provider message ID when supplied.

### Behaviour when unconfigured

- `isConfigured()` returns false.
- The reminder page shows the missing configuration state.
- Sending fails with a clear validation message.
- No audit entry claims a message was sent.

---

## Roles and permissions

Permissions are defined once in `config/school.php` and registered as Laravel gates. Roles are stored in the database and can be edited under **Admin → Roles**.

| Role | Scope |
|---|---|
| Super Administrator | Every permission, including settings, users and audit. |
| Administrator | Day-to-day academic, fee, finance, staff, payroll and reporting operations. |
| Accountant | Fees, expenses, payroll, reports and read-only students/notices. |
| Teacher | Attendance, marks, results, exams and read-only student/academic data. |
| Registrar | Admissions, student records, promotion, enquiries and reports. |
| Staff | Dashboard and notices only. |

Users have a single role. Inactive users cannot authenticate and fail every permission check.

All administrative controllers enforce permissions through middleware, and views use matching `@can` checks. Financial actions are additionally protected by request-level authorization.

---

## Financial and academic rules

### Money
- Amounts use MySQL `decimal(12,2)` columns; no floating-point storage for money.
- Currency symbol is configurable in settings; amounts are never rewritten when the symbol changes.

### Payments and reversals
- Only payments with status `completed` count as collected revenue.
- A payment allocates to one or more outstanding fee assignments.
- Overpayment beyond an assignment balance is rejected.
- Reversal creates a `payment_reversals` row, marks the original payment `reversed`, and recalculates assignment status.
- Original payments and receipts are never deleted; re-reversing is rejected.

### Expenses
- Expenses are soft-deleted. They disappear from active reporting but remain recoverable and auditable.
- Paid salaries are reported separately from manual expenses to avoid double counting.

### Payroll and salary reversals
- Payroll moves `pending` → `processed` → `partially_paid` → `paid`; only processed or partially paid payrolls can receive installments.
- A payroll is `paid` only when completed installments sum to the net salary; `partially_paid` means a balance remains.
- A payroll with no completed installments can move to `cancelled` with a reason. Cancelled rows are retained, excluded from totals and cannot be paid.
- Reversing a salary installment creates a `salary_payment_reversals` row, marks that installment `reversed`, and recalculates the payroll status.
- The original salary payment is never deleted, and the payroll can then be paid again with a corrected installment. Reversing the same installment twice is rejected.

### Fee assignments
- Unique key: student + fee structure item + academic session + period.
- Monthly structures create one row per month in the effective range.
- Status is derived from paid allocations: `pending`, `partial`, `paid` or `waived`.
- Waivers reduce the net amount while retaining the original amount and waiver reason.

### Payroll
- Payroll uses the latest salary structure effective by month end.
- Existing payroll rows are never duplicated.
- Only pending payroll can be processed; only processed/partially paid payroll can receive salary installments. Cancelled payrolls are terminal and excluded from payable totals.
- Salary installments are summed per payroll, capped at net salary, and produce a printable slip.
- Payroll row locks and a positive-amount CHECK constraint replace the former single-payment database guard.

### Marks
- A teacher can only act on papers matching an effective assignment for their active staff profile.
- Marks are `draft`, `submitted` or `approved`; submitted/approved marks cannot be overwritten without correction permission.
- Published or locked exams are read-only without correction rights.
- Entry, submission, approval and corrections record the actor and timestamp in marks and the audit log.

### Attendance and results
- Attendance is unique per student, session and date; resubmission updates the existing record.
- Grades are calculated from configurable grade rules.
- A failed subject forces the overall result to `Fail` and GPA `0.00`.
- Merit lists rank by GPA, then total marks, then student name.

### Audit trail
Sensitive actions write to the audit log: logins, admissions, fee changes, payments, reversals, waivers, attendance, payroll, expenses, notices, settings and user/role changes.

---

## Project structure

```text
app/
  Http/Controllers/        Module controllers (Student, Fee, Academic, Finance, Staff, Communication, Report, Admin, Website)
  Http/Requests/           Validation and request authorization
  Http/Middleware/         Academic session resolution and permission checks
  Models/                  Eloquent models and money/date casts
  Services/                Fee assignment, payments, attendance, results, payroll, marks authorization, dashboard, accounts, Student IDs, login identifiers, WhatsApp, audit, document numbers
  Support/helpers.php      money(), current_session(), school_name(), normalize_phone(), portal_default_password()
config/
  school.php               Permissions, roles, currency defaults and the automatic-account password
database/
  migrations/              Schema, indexes, foreign keys and CHECK constraints
  seeders/                 Roles, settings, core data and demo data
public/
  website/                 Preserved original static school-web copy
  robots.txt               Static crawler rules (takes precedence over the route)
resources/
  css/app.css              Dark-purple admin design system
  js/app.js                Alpine.js and Chart.js bootstrap
  views/
    layouts/public.blade.php   Public website layout
    website/                   Public pages
    admin/website/             Website CMS
    admin/messages/            Contact inbox
    payroll/                   Payroll installments and slips
routes/                    web.php, website.php and module route files
tests/
  Feature/                 Workflow, website, marks/authorization and smoke tests
  Concerns/                Shared test data builders
```

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| `Vite manifest not found` | Run `npm install && npm run build`, or `npm run dev`. |
| Images or receipts return 404 | Run `php artisan storage:link` and check `FILESYSTEM_DISK=public`. |
| `419 Page Expired` | Clear cookies, verify the session driver/table exists, and confirm the CSRF token is present. |
| Login says credentials do not match | Check the user is active, not soft-deleted, and has a valid role. |
| `SQLSTATE[HY000] [1045]` | Correct `DB_USERNAME` / `DB_PASSWORD` in `.env`. |
| Character or currency rendering issues | Ensure the database and tables use `utf8mb4` and the browser is UTF-8. |
| Dashboard shows zero figures | Confirm an academic session is marked current and is selected in the top bar. |
| Permission denied for a valid user | Check the role's permissions, clear the permission cache (`php artisan cache:clear`), and confirm the account is active. |
| Tests drop the wrong database | Verify `phpunit.xml` points `DB_DATABASE` at `school_test`, never at `school`. |
| WhatsApp sends fail | Confirm provider, base URL, token and identifiers; check the audit log and the provider response. |
| Report totals differ from expectations | Reversed payments and archived expenses are intentionally excluded from revenue and expense totals. |
| Public site shows the old static design or an admin page | Confirm the web server document root is `public/` and `routes/website.php` is loaded; the static reference copy lives under `/website/` and is not the live site. |
| Website change is not visible | The item must be `published` (not draft/archived), and the date must not be in the future. Clear the view cache if views were cached during deployment. |
| Teacher sees no assignments or gets 403 on marks | Link the user to an active staff profile, create an active assignment for the current session/class/section/subject, and confirm the staff status. |
| Payroll says `partially_paid` after payment | The installments do not yet sum to the net salary; record the remaining amount or review a reversed installment. |

---

## License

This application is provided for the school it was built for. Review third-party dependency licenses before redistribution.
#   m y s c h o o l  
 