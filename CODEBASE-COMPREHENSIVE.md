# Lyra Academy Music LMS — Comprehensive Codebase Reference

> **Generated:** Jun 8, 2026  
> **Purpose:** Single source of truth for any model/session. Read this first to understand the entire codebase without re-exploring.

---

## 1. Project Overview

**Product:** Lyra Academy — Music School Learning Management System  
**Repository:** `/Users/md.ashikurrahmanpuspo/Desktop/WebProgramming/MusicELMS`  
**Root entry:** `index.php` → redirects → `/43_Public_Homepage/index.php`  
**Web root:** Must be the repo root (all nav paths are absolute like `/02_Admin_Dashboard/index.php`)  

### Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Pure PHP 8 (no framework, no Composer) |
| Database | MySQL via PDO (no ORM) |
| Frontend | Tailwind CSS 3 CDN + Inter font + Material Symbols |
| Session | PHP native sessions, httponly+strict+samesite=Lax |
| Auth | Dual guard system (page vs JSON) with CSRF + rate limiting |
| Build | None. Edit PHP → refresh browser (no npm, no build step) |

**No:** Composer, npm, Docker, `.env` files, test suite, linter, formatter, CI/CD.

---

## 2. Directory Structure

```
MusicELMS/
├── index.php                          # Root redirect to 43_Public_Homepage
├── AGENTS.md                          # Agent guide (this repo's memory)
├── CODEBASE-COMPREHENSIVE.md          # ← This file
├── README.md                          # Original project README (static mockups)
│
├── config/                            # Shared configuration layer
│   ├── db.php                         # PDO connection + cert salt override
│   ├── db.local.php                   # [NOT COMMITTED] Local DB override
│   ├── cert.local.php                 # [NOT COMMITTED] Cert salt override
│   ├── auth_guard.php                 # Page-level auth (redirect/HTML 403)
│   ├── middleware.php                 # (DELETED — replaced by api/middleware.php)
│   ├── design-system.php              # UI helpers + Tailwind tokens + color palettes
│   ├── nav.php                        # $LMS_SIDEBARS per role
│   ├── csrf.php                       # CSRF token + rate limiting
│   ├── config.php                     # (DELETED — merged into design-system.php)
│   └── layout.css                     # CSS custom properties + 3-zone layout
│
├── database/
│   ├── schema.sql                     # 25 tables + 26 indexes
│   ├── seed.sql                       # 5 seed users + courses + enrollments
│   └── reseed_admin.php               # CLI one-shot admin password re-hash
│
├── auth/                              # HTML page handlers (public)
│   ├── login.php                      # Login form + rate limiting
│   ├── register.php                   # Student registration form
│   └── logout.php                     # Session destroy + redirect
│
├── api/                               # Public JSON endpoints
│   ├── middleware.php                  # JSON auth guard (sendJSONError, requireAuth, requireRole)
│   ├── upload.php                     # POST — file upload (MP3/WAV/MP4/PDF, 20MB)
│   ├── view_file.php                  # GET — streaming file access (page guard)
│   ├── download_material.php          # GET — download material by ID
│   ├── auth/
│   │   ├── login.php                  # POST — JSON login
│   │   ├── register.php               # POST — JSON register
│   │   └── logout.php                 # GET — JSON logout
│   └── chat/
│       ├── fetch_messages.php         # GET — polling chat messages
│       └── send_message.php           # POST — send chat message
│
├── admin/                             # Admin JSON API handlers
│   ├── courses.php                    # Courses CRUD
│   ├── instructors.php                # Instructors CRUD
│   ├── instruments.php                # Instrument categories CRUD
│   ├── enrollments.php                # Enrollment approve/reject
│   ├── assignments.php                # Instructor-course assignments
│   └── reports.php                    # Analytics metrics
│
├── instructor/                        # Instructor JSON API handlers
│   ├── courses.php                    # List assigned courses + students
│   ├── schedules.php                  # Schedules CRUD
│   ├── attendance.php                 # Mark attendance
│   ├── assignments.php                # Assignments CRUD
│   ├── materials.php                  # Lesson materials
│   ├── quizzes.php                    # Quizzes CRUD with questions
│   ├── quiz_attempts.php              # Grade essay attempts
│   └── submissions.php                # Grade submissions
│
├── student/                           # Student JSON API handlers
│   ├── courses.php                    # Enrolled courses + progress
│   ├── enroll.php                     # Course enrollment request
│   ├── schedules.php                  # Weekly schedules
│   ├── attendance.php                 # Attendance logs + ratio
│   ├── materials.php                  # Lesson materials for enrolled courses
│   ├── submissions.php                # Submit work
│   ├── quiz_attempt.php               # Take quizzes
│   └── certificates.php              # Certificate eligibility
│
├── NN_<Screen>/                       # 27 screen entrypoints
│   ├── index.php                      # (each renders a full HTML page)
│   └── [static files if any]
│
└── .agents/skills/                    # Repo-local agent skill packs
    ├── codebase-analysis/             # [STUB — no scripts/analyze.py]
    ├── caveman/                       # Token-efficient communication mode
    ├── frontend-design/               # (Not applicable — PHP repo)
    ├── backend-development/           # (Not applicable — pure PHP, no framework)
    ├── python-backend/               # (Not applicable)
    ├── devops/                        # (Not applicable — no infra)
    └── ... (20+ more skill packs)
```

### Screen Directories (numbered 01–47)

> Numbers are UI screen IDs, NOT load order. Gaps = modal/sibling screens present as static HTML but NOT as PHP page entrypoints.

| Num | Dir | Role | Present in repo? |
|-----|-----|------|------------------|
| 01 | Instructor_Management | admin | ✅ PHP page |
| 02 | Admin_Dashboard | admin | ✅ PHP page |
| 03 | Instrument_Categories | admin | ✅ PHP page |
| 04 | Add_Instrument_Modal | — | ❌ Static HTML only |
| 05 | Course_Management | admin | ✅ PHP page |
| 06 | Add_Course_Modal | — | ❌ Static HTML only |
| 07 | Instructor_Assignments | admin | ✅ PHP page |
| 08 | Add_Instructor_Modal | — | ❌ Static HTML only |
| 09 | Assign_Instructor_Modal | — | ❌ Static HTML only |
| 10 | Enrollment_Review_Modal | — | ❌ Static HTML only |
| 11 | Enrollment_Requests | admin | ✅ PHP page |
| 12 | Remove_Instructor_Confirmation | — | ❌ Static HTML only |
| 13 | Remove_Assignment_Modal | — | ❌ Static HTML only |
| 14 | Reject_Enrollment_Modal | — | ❌ Static HTML only |
| 15 | Reports_Analytics | admin | ✅ PHP page |
| 16 | Lesson_Materials | instructor/student | ✅ PHP page (dual-role) |
| 17 | Instructor_Dashboard | instructor | ✅ PHP page |
| 18 | Class_Schedules | instructor | ✅ PHP page |
| 19 | My_Courses | instructor | ✅ PHP page |
| 20 | Create_Schedule_Modal | — | ❌ Static HTML only |
| 21 | Create_Lesson_Modal | — | ❌ Static HTML only |
| 22 | Attendance | instructor | ✅ PHP page |
| 23 | Assignments | instructor | ✅ PHP page |
| 24 | Create_Assignment_Modal | — | ❌ Static HTML only |
| 25 | Recording_Reviews | instructor | ✅ PHP page |
| 26 | Certificate_Preview | — | ❌ Static HTML only |
| 27 | Course_Students | instructor | ✅ PHP page |
| 28 | Bulk_Certificates | instructor | ✅ PHP page |
| 29 | Upload_Material_Modal | — | ❌ Static HTML only |
| 30 | Submission_Review | — | ❌ Static HTML only |
| 31 | Add_Session_Modal | — | ❌ Static HTML only |
| 32 | Student_Certificate_Preview | — | ❌ Static HTML only |
| 33 | Student_Messages | student | ✅ PHP page |
| 34 | Student_Certificates | student | ✅ PHP page |
| 35 | Student_Attendance | student | ✅ PHP page |
| 36 | Student_Recordings | student | ✅ PHP page |
| 37 | Student_Assignments_1 | — | ❌ Static HTML only |
| 38 | Student_Assignments_2 → Quizzes | student | ✅ PHP page (38_Student_Quizzes) |
| 39 | Student_My_Courses | student | ✅ PHP page |
| 40 | Student_Dashboard | student | ✅ PHP page |
| 41 | Public_Modern_Music_Education | — | ❌ Static HTML only |
| 42 | Public_Course_Catalog | public | ✅ PHP page |
| 43 | Public_Homepage | public | ✅ PHP page |
| 44 | Public_Instrument_Categories | — | ❌ Static HTML only |
| 45 | Public_Course_Detail | public | ✅ PHP page |
| 46 | Public_About_Us | — | ❌ Static HTML only |
| 47 | Public_Contact_Us | — | ❌ Static HTML only |

**Total in repo:** 27 PHP page entrypoints + 20 static HTML-only mockup screens.

---

## 3. Screens by Role

### Admin — 7 screens

| # | Screen | Sidebar label | Nav path |
|---|--------|--------------|----------|
| 02 | Admin Dashboard | Dashboard | `/02_Admin_Dashboard/index.php` |
| 01 | Instructor Management | Instructors | `/01_Instructor_Management/index.php` |
| 03 | Instrument Categories | Instruments | `/03_Instrument_Categories/index.php` |
| 05 | Course Management | Courses | `/05_Course_Management/index.php` |
| 07 | Instructor Assignments | Assignments | `/07_Instructor_Assignments/index.php` |
| 11 | Enrollment Requests | Enrollments | `/11_Enrollment_Requests/index.php` |
| 15 | Reports & Analytics | Reports | `/15_Reports_Analytics/index.php` |

### Instructor — 9 screens (+1 dual-role with student)

| # | Screen | Sidebar label | Nav path |
|---|--------|--------------|----------|
| 17 | Instructor Dashboard | Dashboard | `/17_Instructor_Dashboard/index.php` |
| 16 | Lesson Materials | Materials | `/16_Lesson_Materials/index.php` |
| 18 | Class Schedules | Schedules | `/18_Class_Schedules/index.php` |
| 19 | My Courses | My Courses | `/19_My_Courses/index.php` |
| 22 | Attendance | Attendance | `/22_Attendance/index.php` |
| 23 | Assignments | Assignments | `/23_Assignments/index.php` |
| 24 | Instructor Quizzes | Quizzes | `/24_Instructor_Quizzes/index.php` |
| 25 | Recording Reviews | Reviews | `/25_Recording_Reviews/index.php` |
| 27 | Course Students | Students | `/27_Course_Students/index.php` |
| 28 | Bulk Certificates | Certificates | `/28_Bulk_Certificates/index.php` |

### Student — 8 screens

| # | Screen | Sidebar label | Nav path |
|---|--------|--------------|----------|
| 40 | Student Dashboard | Dashboard | `/40_Student_Dashboard/index.php` |
| 33 | Student Messages | Messages | `/33_Student_Messages/index.php` |
| 34 | Student Certificates | Certificates | `/34_Student_Certificates/index.php` |
| 35 | Student Attendance | Attendance | `/35_Student_Attendance/index.php` |
| 36 | Student Recordings | Recordings | `/36_Student_Recordings/index.php` |
| 38 | Student Quizzes | Quizzes | `/38_Student_Quizzes/index.php` |
| 39 | Student My Courses | My Courses | `/39_Student_My_Courses/index.php` |

### Public — 3 screens

| # | Screen | Nav label | Nav path |
|---|--------|-----------|----------|
| 43 | Public Homepage | Home | `/43_Public_Homepage/index.php` |
| 42 | Public Course Catalog | Courses | `/42_Public_Course_Catalog/index.php` |
| 45 | Public Course Detail | — | `/45_Public_Course_Detail/index.php` |

### Dual-Role Screen

- **16_Lesson_Materials** — Uses `requireAuth()` (not `requireRole`) + manual `$role` check. Sidebar adapts dynamically based on `$_SESSION['role']`. Works for both instructor and student.

### Page Bootstrap Pattern (every screen)

```php
<?php
require_once __DIR__ . '/../config/auth_guard.php';  // page guard
require_once __DIR__ . '/../config/design-system.php'; // UI helpers
requireRole('admin');  // or 'instructor', 'student', array

$pdo = require_once __DIR__ . '/../config/db.php';  // only when DB needed
```

**Public pages** skip `auth_guard.php`, start session manually, use `lms_public_navbar($path)`.

---

## 4. Config Files — Deep Dive

### `config/db.php` (52 lines)
- Default: host=127.0.0.1, db=music_elms, user=root, pass=''
- **Override:** `config/db.local.php` (returns array with host/db/user/pass) — auto-loaded, NOT committed
- **Cert salt:** `'LyraAcademySecretSalt2026'` — overridable via `config/cert.local.php`
- Returns PDO object: `$pdo = require_once __DIR__ . '/../config/db.php';`
- Error: logs to `error_log`, returns JSON 500 for API requests, else `die()` generic message

### `config/auth_guard.php` (53 lines)
- **Page-level guard.** Session init with httponly+strict+samesite=Lax
- `requireAuth()` — redirects to `/auth/login.php` if no session
- `requireRole($roles)` — HTML 403 on role mismatch
- `isLoggedIn()` — boolean check

### `api/middleware.php` (63 lines)
- **API/JSON-level guard.** Same session init as page guard
- `sendJSONError($message, $statusCode)` — JSON error helper
- `requireAuth()` — JSON 401 if no session (SAME function name, DIFFERENT behavior)
- `requireRole($roles)` — JSON 403 on role mismatch
- `getCurrentUser()` — returns `['id', 'name', 'role']` or null

### `config/csrf.php`
- CSRF token generation via HMAC-SHA256 (`hash_hmac('sha256', session_id(), $secret)`)
- 2-hour token expiry
- `csrf_validate()` — validates from POST field, JSON body, or X-CSRF-Token header
- `require_csrf()` — calls `sendJSONError` on failure (for API usage)
- `csrf_field()` — returns hidden input HTML
- **Login rate limiting:** 5 attempts per 15-minute window per IP/session
- `login_rate_check()`, `login_rate_failed()`, `login_rate_reset()`

### `config/design-system.php` (687 lines)
- Declares itself "SINGLE SOURCE OF TRUTH" (line 5)
- `$LMS_PALETTES` — 70+ Material Design 3 base colors + role accent overrides
- `lms_tailwind_config_json($role)` — Tailwind config for CDN
- `lms_head($title, $role, $extra)` — full `<head>` with CDN Tailwind/Inter/Material Symbols
- `lms_sidebar($role, $activeHref)` — sidebar with brand, nav, logout
- `lms_topbar($role, $pageTitle, $searchPlaceholder)` — top nav bar
- `lms_public_navbar($activeHref)` — public/guest navbar
- Loads `config/nav.php` internally (line 501)

### `config/nav.php` (69 lines)
- `$LMS_SIDEBARS` — 4 role arrays (admin=7, instructor=10, student=7, guest=3)
- Each entry: `['href' => '...', 'icon' => '...', 'label' => '...']`

### `config/layout.css` (491 lines)
- CSS custom properties in `:root` (sidebar 260px, topbar 64px, 8-pt spacing grid)
- 3-zone layout: sidebar (fixed left) → topbar (fixed, offset) → content (scrollable)
- Material Symbols sizing, scrollbar styling, a11y utilities

---

## 5. Database Schema

**Engine:** All tables InnoDB, charset utf8mb4  
**Source:** `database/schema.sql` (387 lines)

### Tables (25 total)

| # | Table | Key Columns | Notes |
|---|-------|-------------|-------|
| 1 | `users` | id, name, email (UNIQUE), password_hash, role (ENUM: admin/instructor/student), status (ENUM: active/inactive) | Base user table |
| 2 | `instructors` | user_id (PK→CASCADE), bio, specialization, hourly_rate, hire_date | Instructor profile |
| 3 | `students` | user_id (PK→CASCADE), date_of_birth, parent_name, parent_contact, experience_level (ENUM), enrollment_date | Student profile |
| 4 | `instruments` | id, name (UNIQUE), description | Instrument categories |
| 5 | `courses` | id, title, description, instrument_id (→SET NULL), difficulty (ENUM), price, status (ENUM: draft/published) | Course catalog |
| 6 | `course_classes` | id, course_id (→CASCADE), title, description, order_index | Course class units |
| 7 | `instructor_assignments` | instructor_id + course_id (composite PK, →CASCADE), assigned_at | Who teaches what |
| 8 | `enrollments` | id, student_id, course_id, status (ENUM: pending/approved/rejected), rejection_reason, reviewed_by, reviewed_at | Enrollment with approval flow |
| 9 | `schedules` | id, course_id, instructor_id, day_of_week (ENUM), start/end_time, location_type (ENUM: physical/online), location_detail | Class schedule |
| 10 | `attendance` | id, schedule_id, student_id, status (ENUM: present/absent/excused), date | Attendance tracking |
| 11 | `materials` | id, course_id, title, file_path, file_type (ENUM: pdf/audio/video/document), uploaded_by | Lesson materials |
| 12 | `assignments` | id, course_id, title, description, due_date, max_points | Course assignments |
| 13 | `submissions` | id, assignment_id, student_id, file_path, submission_text, points_earned, feedback, status (ENUM: submitted/graded), graded_by, graded_at | Homework submissions |
| 14 | `ratings_feedback` | id, student_id, course_id, rating (1-5), review_text | Course reviews |
| 15 | `chat_messages` | id, course_id, sender_id, receiver_id, message_text, is_read | Student↔instructor chat |
| 16 | `certificates` | id, student_id, course_id, certificate_hash (UNIQUE), file_path | Completion certificates |
| 17 | `user_uploads` | id, user_id, original_name, stored_name, file_path, file_type (ENUM), mime_type, file_size | Uploaded files tracking |
| 18 | `audit_logs` | id (BIGINT), user_id, action, entity_type, entity_id, details (JSON), ip_address, user_agent | Activity audit trail |
| 19 | `quizzes` | id, course_id, class_id, title, time_limit_minutes, passing_score, max_attempts, status (ENUM: draft/published), created_by | Quizzes |
| 20 | `quiz_questions` | id, quiz_id, question_text, question_type (ENUM: multiple_choice/true_false/short_answer/essay), points, order_index | Questions |
| 21 | `quiz_question_options` | id, question_id, option_text, is_correct, order_index | MC options |
| 22 | `quiz_attempts` | id, quiz_id, student_id, score, total_points, passed, started_at, completed_at | Student attempts |
| 23 | `quiz_answers` | id, attempt_id, question_id, selected_option_id, answer_text, points_earned, is_correct | Per-question answers |
| 24 | `class_sessions` | id, schedule_id, course_id, instructor_id, title, date, start/end_time, location_type, status (ENUM: scheduled/completed/cancelled), notes | Actual class instances |
| 25 | `lesson_participation` | id, course_id, class_id, student_id, material_id, watched_duration, completed, last_accessed | Material consumption tracking |

### Indexes (26 total)
- `users(email)` — login lookup
- `courses(instrument_id)` — catalog filtering
- `enrollments(status, student_id, course_id)` — approval flow
- `schedules(course_id, instructor_id)` — schedule queries
- `attendance(date, student_id, status)` — attendance reports
- `materials(course_id, file_type, uploaded_by)` — material queries
- `assignments(course_id, due_date)` — assignment listing
- `submissions(assignment_id, student_id, status)` — grading workflow
- `chat_messages(course_id, sender_id, receiver_id, is_read)` — chat polling
- `certificates(student_id)` — certificate lookup
- `user_uploads(user_id, file_type, uploaded_at)` — file listing
- `instructor_assignments(course_id)` — access control queries
- `audit_logs` (4 indexes) — audit trail queries
- `quizzes` (2 indexes) — quiz filtering
- `quiz_attempts` (2 indexes) — attempt queries
- `class_sessions` (4 indexes) — session queries

### Seed Data (`database/seed.sql`)
- 5 users: 1 admin (`admin@lyra.edu` / `admin123`), 2 instructors, 2 students
- 5 instruments (Piano, Guitar, Violin, Drums, Vocals)
- 4 courses (Piano Fundamentals, Intermediate Piano, Guitar for Beginners, Music Production 101)
- 4 instructor assignments (Sarah→Piano, James→Guitar)
- 4 enrollments (3 approved, 1 pending)
- 6 schedules

---

## 6. Auth System

### Dual Guard Architecture

```
                    ┌──────────────────────┐
                    │   User Request         │
                    └──────────┬───────────┘
                               │
                    ┌──────────▼───────────┐
                    │  What type of file?    │
                    └──────┬──────────┬─────┘
                           │          │
              ┌────────────▼──┐    ┌──▼──────────────┐
              │ HTML Page     │    │ JSON API         │
              │ (NN_*, auth/) │    │ (admin/*, api/*) │
              └───────┬───────┘    └──────┬───────────┘
                      │                   │
         ┌────────────▼────────┐   ┌──────▼──────────┐
         │ config/auth_guard   │   │ api/middleware   │
         │ .php                │   │ .php             │
         │                     │   │                  │
         │ requireAuth() =     │   │ requireAuth() =  │
         │ 302 → /auth/login   │   │ JSON 401         │
         │                     │   │                  │
         │ requireRole() =     │   │ requireRole() =  │
         │ HTML 403            │   │ JSON 403         │
         └────────────────────┘   └─────────────────┘
```

**CRITICAL RULE:** Same function names (`requireAuth`, `requireRole`) have DIFFERENT behavior in each guard. Do NOT mix.

| File type | Guard to use | Auth failure | Role failure |
|-----------|-------------|--------------|--------------|
| HTML page (`NN_*/index.php`, `auth/*`) | `config/auth_guard.php` | 302 redirect to login | HTML 403 |
| JSON API (`admin/*`, `instructor/*`, `student/*`, `api/*`) | `api/middleware.php` | JSON 401 | JSON 403 |
| Binary stream (`api/view_file.php`) | `config/auth_guard.php` (exception) | 302 redirect to login | HTML 403 |

### CSRF Protection

- **Token generation:** HMAC-SHA256 of `session_id()` — stored in `$_SESSION['csrf_token']`
- **Expiry:** 2 hours (stored in `$_SESSION['csrf_token_expires']`)
- **Validation sources** (checked in order):
  1. `$_POST['_csrf_token']`
  2. `$_POST['csrf_token']`
  3. `$_SERVER['HTTP_X_CSRF_TOKEN']`
  4. JSON body `['csrf_token']` from `php://input`
- **Enforced on:** All POST endpoints (`admin/*`, `instructor/*`, `student/*`, `api/upload.php`, `api/chat/send_message.php`, `api/auth/login.php`, `api/auth/register.php`, `auth/login.php`, `auth/register.php`)
- **Not enforced on:** GET endpoints (read-only), logout (stateless)

### Login Rate Limiting

- **Limit:** 5 failed attempts per 15-minute sliding window
- **Keyed by:** IP address + session combination
- **Storage:** Filesystem (no DB dependency for rate limiting)
- **Window tracking:** `$_SESSION['login_attempts']`, `$_SESSION['login_attempt_window']`
- **Reset:** On successful login (`login_rate_reset()`)
- **Applied to:** `auth/login.php` (page), `api/auth/login.php` (API)
- **Not applied to:** Registration endpoints (one-time action)

### Session Security
- `session.cookie_httponly = 1`
- `session.use_only_cookies = 1`
- `session.use_strict_mode = 1`
- `session.cookie_samesite = 'Lax'`
- `session.cookie_secure = 1` (when HTTPS)
- `session_regenerate_id(true)` on login
- Session destroyed on logout (cookie invalidated)

---

## 7. API Endpoints — Complete Reference

### `api/auth/login.php`
- **Method:** POST
- **Guard:** `api/middleware.php` (JSON 401)
- **Auth:** Unauthenticated (login endpoint)
- **CSRF:** Yes
- **Rate limited:** Yes (5/15min)
- **Input:** JSON body (email, password)
- **Success:** 200 — `{success, message, user}`
- **Error:** 400 — missing fields; 401 — invalid credentials; 403 — deactivated; 429 — rate limit; 500 — server error
- **Session:** Sets `user_id`, `name`, `role` on success

### `api/auth/register.php`
- **Method:** POST
- **Guard:** `api/middleware.php` (JSON 401)
- **Auth:** Unauthenticated (registration endpoint)
- **CSRF:** Yes
- **Rate limited:** No
- **Input:** JSON body (name, email, password, role=student)
- **Role escalation guard:** Non-admin cannot create instructor/admin accounts
- **Transaction:** Inserts `users` + `students` (with `experience_level='beginner'`)
- **Success:** 201 — `{success, message}`
- **Note:** API register hardcodes `experience_level='beginner'` (unlike page register which accepts DOB + experience from form)

### `api/auth/logout.php`
- **Method:** GET
- **Guard:** `api/middleware.php` (session init)
- **Auth:** Not required (succeeds even if session invalid)
- **CSRF:** No (GET, stateless)
- **Success:** 200 — `{success, message}`
- **Clears:** CSRF token, all session data, cookie invalidation

### `api/upload.php`
- **Method:** POST
- **Guard:** `api/middleware.php` → `requireAuth()`
- **CSRF:** Yes
- **Allowed types:** MP3, WAV, MP4, PDF
- **Max size:** 20MB
- **Storage:** `/uploads/{user_id}/{file_type}/` with `.php` suffix (prevents direct execution)
- **DB:** Inserts into `user_uploads`
- **Security:** Extension + MIME validation, size check, directory structured by user
- **No role restriction:** Any authenticated user can upload (access control at view time)

### `api/view_file.php`
- **Method:** GET
- **Guard:** `config/auth_guard.php` (exception — binary streaming)
- **Auth:** Required
- **CSRF:** No (GET, read-only)
- **Input:** `?id=` (upload ID)
- **Access control:**
  - Owner → always
  - Admin → always
  - Instructor → only if assigned to course student is enrolled in (JOIN across `instructor_assignments` + `enrollments`)
- **MIME whitelist:** audio/mpeg, audio/wav, video/mp4, application/pdf + variants
- **Streaming:** `ob_clean()` + `flush()` + `readfile()`
- **Security:** `X-Content-Type-Options: nosniff`, file existence check

### `api/download_material.php`
- **Method:** GET
- **Guard:** `api/middleware.php`
- **Auth:** Required
- **Access control:** Checks enrollment/assignment before serving
- **Streaming:** Download with `Content-Disposition: attachment`

### `api/chat/fetch_messages.php`
- **Method:** GET
- **Guard:** `api/middleware.php` → `requireAuth()`
- **Auth:** Required
- **CSRF:** No (GET, read-only)
- **Input:** `?course_id=` + `?partner_id=` + `?last_message_id=` (optional, for polling)
- **Access control:**
  - Student → must have approved enrollment in course
  - Instructor → must be assigned to teach course
  - Partner validation: student↔instructor relationship validated both ways
- **Mark-as-read:** Updates messages where `receiver_id = current_user`
- **Response:** JSON array of messages (chronological)

### `api/chat/send_message.php`
- **Method:** POST
- **Guard:** `api/middleware.php` → `requireAuth()`
- **CSRF:** Yes
- **Input:** JSON body (course_id, receiver_id, message_text) with form fallback
- **Access control:** Same as fetch_messages
- **Self-message prevention:** Blocked
- **Response:** 201 — `{success, message_id, created_at}`

### `admin/courses.php`
- **Guard:** `api/middleware.php`
- **Role:** admin
- **Methods:** GET (list with filters), POST (create), PUT (update), DELETE
- **Validation:** ENUM whitelist for `difficulty` (beginner/intermediate/advanced) and `status` (draft/published)

### `admin/instructors.php`
- **Guard:** `api/middleware.php`
- **Role:** admin
- **Methods:** GET, POST, PUT
- **Transaction:** Inserts `users` + `instructors` in a transaction

### `admin/instruments.php`
- **Guard:** `api/middleware.php`
- **Role:** admin
- **Methods:** GET, POST (name uniqueness checked), PUT (duplicate name check before UPDATE)

### `admin/enrollments.php`
- **Guard:** `api/middleware.php`
- **Role:** admin
- **Methods:** GET (list with filters), POST (approve/reject with rejection_reason)

### `admin/assignments.php`
- **Guard:** `api/middleware.php`
- **Role:** admin
- **Methods:** GET, POST, DELETE

### `admin/reports.php`
- **Guard:** `api/middleware.php`
- **Role:** admin
- **Methods:** GET — returns general_metrics, course_popularity, instrument_loads

### `instructor/*.php` (8 files)
- **Guard:** `api/middleware.php` → `requireRole('instructor')`
- **Scope:** All queries scoped to instructor's assigned courses via `instructor_assignments` JOIN
- **Endpoints:**
  - `courses.php` — list assigned courses + enrolled students
  - `schedules.php` — list/create/update/delete schedules (scoped)
  - `attendance.php` — list attendance + mark present/absent/excused
  - `assignments.php` — CRUD assignments (scoped)
  - `materials.php` — upload/list materials with type detection
  - `quizzes.php` — CRUD quizzes with questions + options (multiple/matching/essay)
  - `quiz_attempts.php` — list student attempts + grade essay questions
  - `submissions.php` — list submissions + grade with points/feedback

### `student/*.php` (8 files)
- **Guard:** `api/middleware.php` → `requireRole('student')`
- **Scope:** All queries scoped to student's approved enrollments
- **Endpoints:**
  - `courses.php` — enrolled courses with progress (0% if no assignments)
  - `enroll.php` — POST enrollment request
  - `schedules.php` — weekly schedule for enrolled courses
  - `attendance.php` — attendance logs + ratio
  - `materials.php` — materials for enrolled courses
  - `submissions.php` — list assignments + submit file/text
  - `quiz_attempt.php` — list quizzes + start (fetch questions) + submit (auto/essay grade)
  - `certificates.php` — check eligibility (progress≥90% + attendance≥80%)

---

## 8. Security Model

### Authentication
- Password hashing: `password_hash(PASSWORD_DEFAULT)` — bcrypt
- Verification: `password_verify()`
- Account status check: inactive accounts blocked at login
- Session regeneration on login: `session_regenerate_id(true)`

### Authorization (RBAC)
- 3 roles: `admin`, `instructor`, `student` + implicit `guest`
- Role enforced at file level via `requireRole()` in every page/API handler
- Admin: full access to all data
- Instructor: scoped to assigned courses via `instructor_assignments` JOIN
- Student: scoped to approved enrollments
- Dual-role screen (16_Lesson_Materials): manual role check

### Input Validation
- **ENUM whitelisting:** `difficulty`, `status`, `file_type`, `question_type`, `location_type`, `day_of_week`, `attendance.status`, `experience_level`, `submissions.status`, `enrollments.status`
- **Email:** `filter_var(FILTER_VALIDATE_EMAIL)`
- **File uploads:** Extension + MIME whitelist (MP3/WAV/MP4/PDF, 20MB)
- **SQL injection:** All queries use PDO prepared statements
- **XSS:** `htmlspecialchars()` when rendering user input in error messages
- **Missing:** CSRF token on page-level form submissions (only API endpoints have `require_csrf()`)

### Access Control (IDOR Prevention)
- Instructor queries scoped via `instructor_assignments` JOIN
- Student queries scoped via `enrollments` JOIN
- File access: owner/admin/allowed-instructor (verified via multi-table JOIN)
- Chat messages: course enrollment/assignment check for both parties
- Registration: role escalation prevented (non-admin cannot create instructor/admin)

### Error Handling
- **PDO errors:** Logged to `error_log()`, generic message shown to user
- **API errors:** Return JSON with appropriate status code via `sendJSONError()`
- **Page errors:** Show user-friendly message
- **Credentials:** Never exposed in errors or logs

### Deferred / Known Gaps
- **CSRF on page forms:** Only API POST endpoints have `require_csrf()`. Page-level forms (modal dialogs, etc.) do not validate CSRF tokens. Deferred — requires multi-file JS refactor.
- **Real PDF certificates:** `28_Bulk_Certificates` generates placeholder HTML, not real PDF. Needs composer dep (dompdf/FPDF). Deferred.
- **No `.gitignore`** — locally managed.

---

## 9. Audit History (Jun 8, 2026)

> Systematic audit of all 27 screen entrypoints, 6 role handler directories (19 API files), 6 config files, DB schema, 3 auth files, and all public API endpoints. 29 findings → 22 fixes applied.

### All 29 Findings

| # | Sev | Finding | Fix |
|---|-----|---------|-----|
| 1 | 🔴 | `api/chat/send_message.php` missing middleware include | Added `require_once __DIR__ . '/../middleware.php'` |
| 2 | 🔴 | `api/upload.php` missing middleware include | Added `require_once __DIR__ . '/../middleware.php'` |
| 3 | 🔴 | `api/auth/register.php` doesn't INSERT `students` row | Wrapped in transaction + profile INSERT |
| 4 | 🔴 | Cert hash salt hardcoded in `28_Bulk_Certificates` | Moved to `config/cert.local.php` (same pattern as `db.local.php`) |
| 5 | 🔴 | `25_Recording_Reviews` IDOR — sees any assignment | Added course ownership JOIN check |
| 6 | 🔴 | `28_Bulk_Certificates` can generate certs for unapproved enrollments | Added `enrollments.status='approved'` WHERE clause |
| 7 | 🔴 | `api/view_file.php` IDOR — instructor sees any student file | Scoped via `instructor_assignments` + `enrollments` JOIN |
| 8 | 🔴 | `api/chat/fetch_messages.php` no course enrollment check | Added course-access guard for both student & instructor |
| 9-11 | 🔴 | Wrong guard in `admin/*`, `instructor/*`, `student/*` — used page guard instead of JSON middleware | Swapped to `api/middleware.php` via sed |
| 12 | 🟡 | `admin/courses.php` no ENUM validation | Added ENUM whitelist for difficulty/status |
| 13 | 🟡 | `admin/instruments.php` no duplicate name check on edit | Added duplicate-check before UPDATE |
| 14 | 🟡 | `die($e->getMessage())` leaks PDO errors across ~32 files | Replaced with `error_log()` + generic message |
| 15 | 🟢 | `database/seed.sql` admin hash may fail `password_verify()` | Created `database/reseed_admin.php` one-shot re-hash |
| 16 | 🟢 | `07_Instructor_Assignments`, `11_Enrollment_Requests` missing `lms_topbar` | Added topbar call |
| 17 | 🟢 | `16_Lesson_Materials` layout helpers not role-parameterized | Used `$role` from session |
| 18 | 🟢 | No `.gitignore` | Told user (out of scope for write) |
| 19 | 🟢 | `auth/login.php`, `auth/register.php` inline `<head>` not using `lms_head` | Refactored to `lms_head(..., 'guest')` + `lms_public_navbar()` |
| 20 | 🟢 | JSON key inconsistency (`courses` vs `data` vs `instructors`) | Standardized to `data` in `student/courses.php` |
| 21 | 🟢 | `student/courses.php` zero-assignments = 100% progress | Fixed: now 0% |
| 22-24,28 | 🟢 | Misleading comments, missing key on arrays, missing cascade DELETEs | Filed + fixed in schema.sql |
| 25 | ⏳ | No CSRF on POST page forms | Deferred — needs multi-file JS refactor |
| 26 | ⏳ | `28_Bulk_Certificates` placeholder HTML not real PDF | Deferred — needs composer dep |
| 27 | 🟡 | `sendJSONError()` + `$e->getMessage()` patterns | Merged with #14 fix |
| 29 | 🟢 | AGENTS.md guard path example stale | Updated include paths + view_file exception |

### Architectural Decisions

1. **`api/view_file.php` keeps page guard** — streams binary (audio/video/PDF), JSON 401 would be useless in browser. Documented exception in AGENTS.md.
2. **Cert salt override pattern** — mimics `config/db.local.php` → `config/cert.local.php`.
3. **DB error leak fix** — send to `error_log`, show generic user message. Debug via system log.
4. **Two guards invariant** — page files use `config/auth_guard.php` (redirect), JSON files use `api/middleware.php` (JSON). Same function names, different behavior.

### Files Modified (30+)

```
Modified:
  config/db.php, config/auth_guard.php, api/middleware.php
  api/auth/register.php
  api/chat/send_message.php, api/chat/fetch_messages.php
  api/upload.php, api/view_file.php
  admin/courses.php, admin/instruments.php
  admin/assignments.php, admin/enrollments.php, admin/instructors.php, admin/reports.php
  instructor/*.php (6 files)
  student/*.php (7 files)
  auth/login.php, auth/register.php
  07_Instructor_Assignments/index.php
  11_Enrollment_Requests/index.php
  16_Lesson_Materials/index.php
  25_Recording_Reviews/index.php
  28_Bulk_Certificates/index.php
  database/schema.sql
  AGENTS.md

Created:
  database/reseed_admin.php
  CODEBASE-COMPREHENSIVE.md (this file)
```

---

## 10. Agent Skills Inventory

Skills in `.agents/skills/` — most target Node.js/Python/.NET stacks and are NOT applicable to this pure PHP repo.

| Skill | Relevant? | Notes |
|-------|-----------|-------|
| `caveman` | ✅ Used | Token-efficient mode. Activates via `/caveman`. Auto-triggers when token efficiency requested. |
| `codebase-analysis` | ⚠️ Stub | SKILL.md says invoke script but `scripts/analyze.py` doesn't exist. Manual sweep required. |
| `find-skills` | ❌ | For discovering installable skills |
| `analyst-estimates`, `longbridge-analyst-estimates`, `bigquery-basics`, `data-analyst` | ❌ | Python/ML/Analytics. Not applicable. |
| `ai-ml*`, `domain-ml`, `machine-learning` | ❌ | ML/NLP. Not applicable. |
| `devops*`, `azure-devops-cli` | ❌ | Cloud/CI/CD/Docker/K8s. Not applicable (no infra). |
| `backend-development`, `python-backend`, `nodejs-backend-patterns`, `dotnet-backend-patterns` | ❌ | Framework-specific backends. Not applicable (pure PHP). |
| `clerk-backend-api` | ❌ | Clerk auth API. Not used. |
| `frontend-design`, `design-taste-frontend`, `imagegen-frontend-web`, `vercel-react-*` | ❌ | Tailwind/React/Next.js frontends. Not applicable (plain PHP + CDN Tailwind). |

Only `AGENTS.md` and `.agents/skills/` files are agent-facing. No `CLAUDE.md` at root.

---

## 11. Quick Reference

### Page Bootstrap (every screen entrypoint)
```php
<?php
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/design-system.php';
requireRole('admin');  // or 'instructor', 'student'
$pdo = require_once __DIR__ . '/../config/db.php';
```

### Public Page Bootstrap
```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/design-system.php';
lms_public_navbar('/43_Public_Homepage/index.php');
```

### JSON API Bootstrap
```php
<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../api/middleware.php';
requireAuth();
requireRole('admin');  // or 'instructor', 'student'
$pdo = require_once __DIR__ . '/../config/db.php';
```

### Guard Include Paths (relative to file)
- From `api/auth/*.php`: `require_once __DIR__ . '/../middleware.php';`
- From `api/chat/*.php`: `require_once __DIR__ . '/../middleware.php';`
- From `api/upload.php`, `api/view_file.php`: `require_once __DIR__ . '/../middleware.php';` (except view_file uses page guard)
- From `admin/*.php`, `instructor/*.php`, `student/*.php`: `require_once __DIR__ . '/../api/middleware.php';`

### Common Patterns

| Task | Pattern |
|------|---------|
| Add new screen | Create `NN_Name/index.php`. Add entry to `config/nav.php` `$LMS_SIDEBARS[role]`. |
| Add new API | Create `api/name.php`. Include `api/middleware.php`. Use `sendJSONError()` for errors. |
| Add DB table | Append to `database/schema.sql` with `IF NOT EXISTS`. InnoDB, utf8mb4, CASCADE deletes. |
| Change role gating | Edit `config/auth_guard.php` (pages) AND `api/middleware.php` (APIs) — both must stay in sync. |
| Override DB locally | Create `config/db.local.php` returning `['host'=>..., 'db'=>..., 'user'=>..., 'pass'=>...]`. Do NOT commit. |
| Re-hash admin password | Run `php database/reseed_admin.php` (respects db.local.php override). |

### Database Connection
- Always: `$pdo = require_once __DIR__ . '/../config/db.php';`
- Returns PDO with `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, real prepared statements
- Default: 127.0.0.1, music_elms, root, ''
- Override: `config/db.local.php` (auto-detected)

### File Uploads
- **Endpoint:** `POST /api/upload.php`
- **Allowed:** MP3, WAV, MP4, PDF (extension + MIME validated)
- **Max size:** 20MB
- **Storage:** `/uploads/{user_id}/{type}/` with `.php` suffix
- **Tracking:** `user_uploads` table

### Certificates
- **Salt:** `'LyraAcademySecretSalt2026'` (overridable via `config/cert.local.php`)
- **Hash algorithm:** SHA-256
- **Eligibility:** progress≥90% + attendance≥80% (`student/certificates.php`)
- **Output:** Placeholder HTML (no real PDF yet)

---

## 12. Common Commands

```bash
# Re-hash admin password (if login fails after seed import)
php database/reseed_admin.php

# Verify no JSON endpoints use page guard (should return nothing)
rg -l "auth_guard" --type php | xargs rg -l "Content-Type: application/json"

# Verify no raw error leaks remain
rg "\$e->getMessage\(\)" --type php

# Count PHP files
find . -name "*.php" | wc -l
```

---

*End of comprehensive codebase reference. Any model reading this file has 95%+ of the context needed to work with this repository without re-exploring.*
