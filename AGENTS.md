# AGENTS.md — Lyra Academy Music LMS

## Stack

- Pure PHP 8 + MySQL (PDO). No framework, no Composer, no npm.
- Frontend: Tailwind CSS via CDN + Inter font + Material Symbols.
- No lint/format/typecheck commands exist. "Verify" = open page in browser + run SQL by hand.

## File Patterns

- **Pages**: `NN_<Screen>/index.php` (e.g., `02_Admin_Dashboard/index.php`). Number prefix = UI screen ID, NOT load order. Gaps in numbering (04, 06, 08, 10, …) = modal/sibling screens not in repo. Don't renumber.
- **Admin APIs**: `admin/*.php` (e.g., `admin/instructors.php`). JSON endpoints with `require_once __DIR__ . '/../api/middleware.php';`.
- **Instructor APIs**: `instructor/*.php` (e.g., `instructor/courses.php`). JSON endpoints with `require_once __DIR__ . '/../api/middleware.php';`.
- **Student APIs**: `student/*.php` (e.g., `student/certificates.php`). JSON endpoints with `require_once __DIR__ . '/../api/middleware.php';`.
- **Public APIs**: `api/*.php` (e.g., `api/auth/login.php`, `api/upload.php`). Use `require_once __DIR__ . '/../middleware.php';`.
- **Config**: `config/*.php` (e.g., `config/db.php`, `config/auth_guard.php`, `config/design-system.php`).
- **Database**: `database/*.sql` (e.g., `schema.sql`, `seed.sql`, `migration_v2.sql`).

## Two Auth Guards — Do Not Mix

- `config/auth_guard.php` — page-level. `requireAuth()` redirects to `/auth/login.php`. `requireRole()` renders HTML 403.
- `api/middleware.php` — JSON endpoints. `requireAuth()`/`requireRole()` call `sendJSONError()` and exit.

Same function names, different behavior. Pick by file family:
- HTML page → `config/auth_guard.php` (e.g. `NN_Foo/index.php`, `auth/logout.php`, `api/view_file.php`).
- JSON API → `api/middleware.php` (e.g. `admin/*.php`, `instructor/*.php`, `student/*.php`, `api/auth/*.php`, `api/chat/*.php`, `api/upload.php`).

Include paths (relative to file):
- From `api/auth/*.php` → `require_once __DIR__ . '/../middleware.php';`
- From `api/chat/*.php` → `require_once __DIR__ . '/../middleware.php';`
- From `admin/`, `instructor/`, `student/*.php` → `require_once __DIR__ . '/../api/middleware.php';`

Exception: `api/view_file.php` streams binary (audio/video/PDF), keeps `config/auth_guard.php` so unauthed browser requests get a 302 to login instead of a JSON body the browser can't render.

## Page Bootstrap

Every page (except public ones):
```php
require_once __DIR__ . '/../config/auth_guard.php';  // starts session, provides requireAuth/requireRole
require_once __DIR__ . '/../config/design-system.php'; // tailwind tokens, $LMS_PALETTES, lms_head/sidebar/topbar fns
requireRole('admin'); // or 'instructor', 'student', or array. Calls requireAuth internally.

$pdo = require_once __DIR__ . '/../config/db.php'; // only when DB queries needed
```

Public pages skip auth_guard, call `session_start()` manually, and use `lms_public_navbar($path)` instead of `lms_sidebar`.

`config/design-system.php:5` declares itself "SINGLE SOURCE OF TRUTH" — required at top of every page before HTML output.

## Database Setup

- Source of truth: `database/schema.sql` (v2: 25 tables — users, instructors, students, instruments, courses, course_classes, instructor_assignments, enrollments, schedules, attendance, materials, assignments, submissions, ratings_feedback, chat_messages, certificates, user_uploads, audit_logs, quizzes, quiz_questions, quiz_question_options, quiz_attempts, quiz_answers, class_sessions, lesson_participation).
- `config/db.php:5-9` defaults: host `127.0.0.1`, db `music_elms`, user `root`, pass `''`.
- Override per-machine: create `config/db.local.php` returning `['host'=>…, 'db'=>…, 'user'=>…, 'pass'=>…]`. Auto-loaded if present (`config/db.php:12-21`). Do not commit this file.
- DB error in API/JSON request → JSON 500 (`config/db.php:33-40`); otherwise `die()`.
- Seed admin from `database/seed.sql`: `admin@lyra.edu` / `admin123`. Hash in seed file may not match `password_hash()` output → run `php database/reseed_admin.php` or re-hash via `auth/register.php` if login fails.
- Migrations: `database/migration_v2.sql` adds quizzes, audit_logs, class_sessions, lesson_participation + FK fixes. `database/rollback_v2.sql` reverses it. Safe to re-run (IF NOT EXISTS guards).

## Certificate System

- `config/pdf_cert.php` — pure PHP PDF certificate generator (no external libraries). Uses raw PDF 1.4 primitives.
- `config/cert_helper.php` — **Single source of truth** for cert salt, hash generation, and eligibility. All certificate code must use this.
  - `cert_get_salt()` — loads salt from `config/cert.local.php` override or defaults to `'LyraAcademySecretSalt2026'`.
  - `cert_generate_hash($studentId, $courseId)` — deterministic HMAC-SHA256 hash (no `time()` component).
  - `cert_get_eligibility($pdo, $studentId, $courseId)` — returns `['progress' => float, 'attendance' => float, 'eligible' => bool]`. Thresholds: progress≥90%, attendance≥80%.
- `config/pdf_cert.php` signature: `generate_certificate_pdf($studentName, $courseName, $instructorName, $date, $certificateHash = '')` — 5th param adds Certificate ID + verification URL to the PDF.
- `api/certificate_verify.php` — **Public** certificate verification page (no auth). Accepts `?hash=XXX`, shows validity, student name, course, instructor, issue date, certificate ID.
- `student/certificates.php` — JSON API endpoint. Student claims certificate. Now generates **real PDF** via `generate_certificate_pdf()` (was placeholder text before).
- `28_Bulk_Certificates/index.php` — Instructor bulk certificate generation. Uses `cert_helper.php` functions.
- `34_Student_Certificates/index.php` — Student certificate view page. Shows QR codes (via `qrcode.js` CDN), verification URLs, and "Verify" buttons.
- Cert hash salt: `'LyraAcademySecretSalt2026'` default. Override via `config/cert.local.php`. Do not commit that file.
- Cert salt is **only** in `config/cert_helper.php` (was duplicated in 3 files before, now deduplicated).
- Eligibility logic is **only** in `cert_get_eligibility()` (was duplicated in 3 files before).

## CSRF Protection

- Token: HMAC-SHA256 of `session_id()`, 2-hour expiry.
- Validation: checks `$_POST['_csrf_token']`, `$_POST['csrf_token']`, `HTTP_X_CSRF-Token` header, or JSON body `csrf_token`.
- Enforced on: all POST API endpoints, `auth/login.php`, `auth/register.php`.
- Not enforced on: GET endpoints, logout.
- Login rate limiting: 5 attempts/15min per IP+session, tracked in `$_SESSION`.

## API Patterns

**API files** (`admin/*.php`, `instructor/*.php`, `student/*.php`, `api/auth/*.php`, `api/chat/*.php`, `api/upload.php`):
```php
require_once __DIR__ . '/../api/middleware.php';
requireRole('admin'); // or 'instructor', 'student'
header('Content-Type: application/json');
// Business logic...
```

**Exception**: `api/view_file.php` streams binary (audio/video/PDF), keeps `config/auth_guard.php` so unauthed browser requests get a 302 to login instead of a JSON body.

## Navigation

Role-based sidebar defined in `config/nav.php` (`$LMS_SIDEBARS`):

- **Admin**: `/02_Admin_Dashboard/index.php`, `/01_Instructor_Management/index.php`, etc.
- **Instructor**: `/17_Instructor_Dashboard/index.php`, `/16_Lesson_Materials/index.php`, etc.
- **Student**: `/40_Student_Dashboard/index.php`, `/33_Student_Messages/index.php`, etc.
- **Guest**: `/43_Public_Homepage/index.php`, `/42_Public_Course_Catalog/index.php`, etc.

## Verification Commands

```bash
# Re-hash admin password (if login fails after seed import)
php database/reseed_admin.php

# Verify no JSON endpoints use page guard (should return nothing)
rg -l "auth_guard" --type php | xargs rg -l "Content-Type: application/json"

# Verify no raw error leaks remain
rg "\$e->getMessage\(\)" --type php

# Verify cert salt is only in cert_helper.php (should return 1 file)
rg "LyraAcademySecretSalt2026" --type php -l

# Verify no placeholder PDFs remain
rg "file_put_contents.*Placeholder" --type php

# Verify all pages have skip link target
rg -c 'id="lms-main-content"' --type php | wc -l  # should be 33

# Verify aria-hidden on decorative icons
rg -c 'aria-hidden="true"' --type php | wc -l  # should be 100+

# Verify aria-live regions for dynamic content
rg -c 'aria-live="polite"' --type php | wc -l  # should be 18+
```

## Common Tasks

- **Add page**: Create `NN_NewScreen/index.php` mirroring structure of an existing screen in same role group. Add entry to matching role array in `config/nav.php` (`$LMS_SIDEBARS`).
- **Add API**: Create `api/<name>.php`, include `api/middleware.php`, use `sendJSONError` for errors, `header('Content-Type: application/json')` for success.
- **Add DB table**: Append to `database/schema.sql` with `IF NOT EXISTS`. Match existing InnoDB/utf8mb4 conventions.
- **Run migration**: `mysql -u root music_elms < database/migration_v2.sql`.
- **Change role gating**: Edit `config/auth_guard.php` (page) or `api/middleware.php` (api) — both must stay in sync if behavior changes.

## Out of Repo

- No test suite, no linter, no formatter. Don't propose adding them without asking.
- No deployment config (Docker, CI, hosting). Repo is dev-only.
- `config/db.local.php` and `config/cert.local.php` are per-machine, not committed.
- Uploaded files land in `/uploads/` which does not exist in repo. Created on first upload.

## Conventions / gotchas

- Paths in HTML/nav are absolute (`/02_Admin_Dashboard/index.php`). Web root must be the repo root.
- No autoloader. Every include is explicit `require_once` with `__DIR__` paths.
- `config/db.php` `return`s the PDO — assign it: `$pdo = require_once …`.
- Tailwind classes + a few custom `lms-*` classes (see `config/layout.css`). Don't introduce a new CSS framework.
- Inline styles only acceptable inside generated error/403 blocks (`config/auth_guard.php:36-41`).
- No build step. Edit PHP/HTML, refresh browser.
- No `.gitignore` in tree — assume `config/db.local.php`, `config/cert.local.php`, uploaded files, and `.agents/skills/` overrides are locally managed.
- Dual-role screen (`16_Lesson_Materials`): uses `requireAuth()` + manual `$role` check, not `requireRole()`.

## Layout

- `index.php` — root redirect → `43_Public_Homepage/index.php`.
- `NN_<Screen>/index.php` — page entrypoint. Number prefix = UI screen ID, NOT load order. Gaps in numbering (04, 06, 08, 10, …) = modal/sibling screens not in repo. Don't renumber.
- `auth/` — page handlers for login/logout/register UI.
- `admin/`, `instructor/`, `student/` — JSON API handlers for role-specific operations (not page redirects).
- `api/` — public JSON endpoints (`api/auth/*`, `api/chat/*`, `api/upload.php`, `api/view_file.php`, `api/middleware.php`).
- `config/` — `db.php`, `auth_guard.php`, `design-system.php`, `nav.php`, `layout.css`, `csrf.php`, `pdf_cert.php`, `cert_helper.php`.
- `database/` — `schema.sql` (v2, 25 tables), `seed.sql`, `reseed_admin.php`, `migration_v2.sql`, `rollback_v2.sql`.
- `.agents/skills/` — repo-local skill packs, not user data.



