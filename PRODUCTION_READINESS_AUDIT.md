# Production Readiness Audit: Music School Enrollment & Lesson Scheduling System

Audit date: 2026-06-07  
Scope: `/Users/md.ashikurrahmanpuspo/Desktop/WebProgramming/MusicELMS`

## Executive Verdict

Status: **not production ready**.

Primary blockers:

- **CRITICAL:** CSRF protection is missing across all session-authenticated mutating forms and JSON endpoints.
- **CRITICAL:** uploaded files and generated certificates are stored under public `/uploads/...` paths and many pages link them directly, bypassing `api/view_file.php`.
- **HIGH:** chat fetch lacks course membership validation, allowing authenticated users to query message threads when they know `course_id` and `partner_id`.
- **HIGH:** API student registration creates `users` rows without `students` profile rows, breaking enrollment and all FK-dependent student features.
- **HIGH:** certificate generation creates placeholder text files with `.pdf` extension, not real PDFs.
- **HIGH:** assignment resubmission upsert depends on a missing unique key on `(assignment_id, student_id)`.
- **HIGH:** required features are absent: quizzes, schedule editing, instructor chat UI, guest school info/about/contact pages, real class participation tracking, and real certificate generation.

## Phase 1: System Discovery

### Pages

Admin pages:

- `01_Instructor_Management/index.php`
- `02_Admin_Dashboard/index.php`
- `03_Instrument_Categories/index.php`
- `05_Course_Management/index.php`
- `07_Instructor_Assignments/index.php`
- `11_Enrollment_Requests/index.php`
- `15_Reports_Analytics/index.php`

Instructor pages:

- `16_Lesson_Materials/index.php`
- `17_Instructor_Dashboard/index.php`
- `18_Class_Schedules/index.php`
- `19_My_Courses/index.php`
- `22_Attendance/index.php`
- `23_Assignments/index.php`
- `25_Recording_Reviews/index.php`
- `27_Course_Students/index.php`
- `28_Bulk_Certificates/index.php`

Student pages:

- `33_Student_Messages/index.php`
- `34_Student_Certificates/index.php`
- `35_Student_Attendance/index.php`
- `36_Student_Recordings/index.php`
- `37_Student_Assignments_1/index.php`
- `39_Student_My_Courses/index.php`
- `40_Student_Dashboard/index.php`

Guest/public pages:

- `42_Public_Course_Catalog/index.php`
- `43_Public_Homepage/index.php`
- `45_Public_Course_Detail/index.php`

Declared in README but missing from codebase:

- `04_Add_Instrument_Modal`
- `06_Add_Course_Modal`
- `08_Add_Instructor_Modal`
- `09_Assign_Instructor_Modal`
- `10_Enrollment_Review_Modal`
- `12_Remove_Instructor_Confirmation`
- `13_Remove_Assignment_Modal`
- `14_Reject_Enrollment_Modal`
- `20_Create_Schedule_Modal`
- `21_Create_Lesson_Modal`
- `24_Create_Assignment_Modal`
- `26_Certificate_Preview`
- `29_Upload_Material_Modal`
- `30_Submission_Review`
- `31_Add_Session_Modal`
- `32_Student_Certificate_Preview`
- `38_Student_Assignments_2`
- `41_Public_Modern_Music_Education`
- `44_Public_Instrument_Categories`
- `46_Public_About_Us`
- `47_Public_Contact_Us`

### Components and Layouts

- Shared design system: `config/design-system.php`
- Shared stylesheet: `config/layout.css`
- Role nav config: `config/nav.php`
- Sidebar/topbar/public navbar helpers: `lms_sidebar()`, `lms_topbar()`, `lms_public_navbar()` in `config/design-system.php`
- Login/register pages use separate standalone styles instead of shared design system.

### PHP Modules and APIs

Admin modules:

- `admin/instructors.php`: add/edit/delete/list instructors
- `admin/instruments.php`: add/edit/delete/list instruments
- `admin/courses.php`: add/edit/delete/list courses
- `admin/assignments.php`: assign/unassign instructors
- `admin/enrollments.php`: approve/reject enrollments
- `admin/reports.php`: dashboard/report metrics

Instructor modules:

- `instructor/courses.php`: assigned courses, assigned students
- `instructor/materials.php`: list/upload/delete materials
- `instructor/schedules.php`: list/create/delete schedules
- `instructor/attendance.php`: fetch/mark attendance
- `instructor/assignments.php`: list/create assignments
- `instructor/submissions.php`: list/grade submissions

Student modules:

- `student/enroll.php`: request enrollment
- `student/materials.php`: enrolled-course materials
- `student/schedules.php`: enrolled-course schedules
- `student/attendance.php`: attendance records/metrics
- `student/submissions.php`: list assignments and submit work
- `student/certificates.php`: claim certificate
- `student/courses.php`: course listing API

Authentication and file APIs:

- `auth/login.php`, `auth/register.php`, `auth/logout.php`
- `api/auth/login.php`, `api/auth/register.php`, `api/auth/logout.php`
- `api/upload.php`
- `api/view_file.php`
- `api/chat/send_message.php`
- `api/chat/fetch_messages.php`
- `api/middleware.php`

### Database Tables

Defined in `database/schema.sql`:

- `users`
- `instructors`
- `students`
- `instruments`
- `courses`
- `course_classes`
- `instructor_assignments`
- `enrollments`
- `schedules`
- `attendance`
- `materials`
- `assignments`
- `submissions`
- `ratings_feedback`
- `chat_messages`
- `certificates`
- `user_uploads`

Missing tables for required features:

- `quizzes`
- `quiz_questions`
- `quiz_attempts`
- `quiz_answers`
- `class_sessions` or `lesson_participation`
- `certificate_templates`
- `audit_logs`
- `csrf_tokens` if token storage is not session-only

### Forms

- Login: `auth/login.php`
- Student registration: `auth/register.php`
- Instructor create/edit: `01_Instructor_Management/index.php`
- Instrument create/edit: `03_Instrument_Categories/index.php`
- Course create/edit: `05_Course_Management/index.php`
- Instructor assignment: `07_Instructor_Assignments/index.php`
- Enrollment rejection modal: `11_Enrollment_Requests/index.php`
- Material upload: `16_Lesson_Materials/index.php`
- Schedule creation: `18_Class_Schedules/index.php`
- Attendance marking: `22_Attendance/index.php`
- Assignment creation: `23_Assignments/index.php`
- Submission grading: `25_Recording_Reviews/index.php`
- Bulk/single certificate issuance: `28_Bulk_Certificates/index.php`
- Student chat: `33_Student_Messages/index.php`
- Student assignment submission: `37_Student_Assignments_1/index.php`
- Student recording upload: `36_Student_Recordings/index.php`

### Upload Handlers

- `api/upload.php`: general user uploads into `/uploads/{user_id}/{type}/`
- `instructor/materials.php`: material uploads into `/uploads/materials/`
- `student/submissions.php`: submissions into `/uploads/submissions/`
- `student/certificates.php`: generated certificate placeholders into `/uploads/certificates/`
- `28_Bulk_Certificates/index.php`: generated certificate placeholders into `/uploads/certificates/`

### Authentication Files and Session Guards

- Page guard: `config/auth_guard.php`
- API guard: `api/middleware.php`
- Session login: `auth/login.php`
- Session logout: `auth/logout.php`
- Student registration: `auth/register.php`
- JSON login/register/logout: `api/auth/*`

### Reports, Certificates, Chat

- Reports: `15_Reports_Analytics/index.php`, `admin/reports.php`
- Certificate modules: `28_Bulk_Certificates/index.php`, `34_Student_Certificates/index.php`, `student/certificates.php`
- Chat modules: `33_Student_Messages/index.php`, `api/chat/send_message.php`, `api/chat/fetch_messages.php`

### Dependency Map

```text
Browser
  -> index.php
  -> public pages / auth pages / role dashboards

Role pages
  -> config/auth_guard.php
  -> config/design-system.php
  -> config/nav.php
  -> config/db.php
  -> MySQL tables

Admin UI
  -> admin/instructors.php -> users, instructors
  -> admin/instruments.php -> instruments
  -> admin/courses.php -> courses, instruments
  -> admin/assignments.php -> users, courses, instructor_assignments
  -> admin/enrollments.php -> enrollments, users, courses
  -> admin/reports.php -> users, courses, enrollments, ratings_feedback

Instructor UI
  -> instructor/courses.php -> instructor_assignments, courses, enrollments, students
  -> instructor/materials.php -> materials, uploads/materials
  -> instructor/schedules.php -> schedules
  -> instructor/attendance.php -> attendance, schedules, enrollments
  -> instructor/assignments.php -> assignments
  -> instructor/submissions.php -> submissions

Student UI
  -> student/enroll.php -> enrollments
  -> student/materials.php -> materials
  -> student/schedules.php -> schedules
  -> student/attendance.php -> attendance
  -> student/submissions.php -> submissions, uploads/submissions
  -> student/certificates.php -> certificates, uploads/certificates
  -> api/chat/* -> chat_messages
```

## Phase 2: Feature Completeness Check

### Admin

| Feature | Status | Evidence | Implementation plan |
|---|---:|---|---|
| Add instructors | Present | `admin/instructors.php` POST add | Add CSRF, password policy, audit log. |
| Remove instructors | Present | `admin/instructors.php` DELETE | Add confirmation, prevent deleting last admin-equivalent owner if added later, audit log. |
| Add courses | Present | `admin/courses.php` POST add | Add validation for enum fields and price bounds. |
| Add instruments | Present | `admin/instruments.php` POST add | Add CSRF and duplicate handling. |
| Assign instructors | Present | `admin/assignments.php` POST | Add schedule conflict checks. |
| Approve enrollments | Present | `admin/enrollments.php` POST | Add notification/audit trail. |
| Enrollment reports | Partial | `15_Reports_Analytics/index.php`, `admin/reports.php` | Add export, filtering, date ranges. |
| Attendance reports | Partial | `15_Reports_Analytics/index.php` low-attendance query | Add complete attendance report by course/student/date. |
| Performance reports | Partial | assignments/submissions scores used in dashboards | Add dedicated performance report, progress history, grade distributions. |

### Instructor

| Feature | Status | Evidence | Implementation plan |
|---|---:|---|---|
| View assigned students | Present | `27_Course_Students/index.php`, `instructor/courses.php` | Add search/filter and export. |
| Upload lesson materials | Present | `16_Lesson_Materials/index.php`, `instructor/materials.php` | Move files outside webroot and serve via access-checked endpoint. |
| Create schedules | Present | `18_Class_Schedules/index.php`, `instructor/schedules.php` POST | Add conflict validation and timezone handling. |
| Edit schedules | Missing | `instructor/schedules.php` supports GET/POST/DELETE only | Add PUT/PATCH endpoint, edit modal, ownership check, conflict check. |
| Attendance marking | Present | `22_Attendance/index.php`, `instructor/attendance.php` | Add immutable audit log for changes. |
| Quiz creation | Missing | no quiz tables/pages/endpoints | Add quiz schema, instructor quiz builder, student attempts, grading/reporting. |
| Assignment creation | Present | `23_Assignments/index.php`, `instructor/assignments.php` | Add edit/delete and due-date validation. |
| Recording review | Present | `25_Recording_Reviews/index.php`, `instructor/submissions.php` | Serve media via protected endpoint. |
| Feedback submission | Present | `instructor/submissions.php` POST | Add rubrics and max-point bounds. |
| Certificate generation | Partial | `28_Bulk_Certificates/index.php` | Replace placeholder files with real PDF renderer/templates. |
| Student chat | Missing/partial | student UI exists; no instructor chat page/nav | Add instructor messages page and course/student thread picker. |

### Student

| Feature | Status | Evidence | Implementation plan |
|---|---:|---|---|
| Registration | Present | `auth/register.php` | Fix JSON API registration profile row issue. |
| Enrollment requests | Present | `student/enroll.php`, catalog/detail pages | Add request history and notifications. |
| Course viewing | Present | `39_Student_My_Courses/index.php`, catalog | Good baseline. |
| Schedule viewing | Present/partial | `student/schedules.php`; no dedicated nav item | Add visible schedule page/nav or dashboard calendar. |
| Class participation | Missing | no participation table or endpoint | Add `class_sessions` and `lesson_participation`; tie to schedules and attendance. |
| Material access | Present | `student/materials.php`; UI links instructor material page | Add student-specific material page or secure material view. |
| Quiz participation | Missing | no quiz schema/pages | Add student quiz attempt UI/endpoints. |
| Recording uploads | Present | `36_Student_Recordings/index.php`, `api/upload.php`, `student/submissions.php` | Fix field mismatch and protected downloads. |
| Feedback viewing | Present | `37_Student_Assignments_1/index.php` | Good baseline. |
| Attendance tracking | Present | `35_Student_Attendance/index.php`, `student/attendance.php` | Good baseline. |
| Progress tracking | Partial | dashboards compute assignment/attendance metrics | Add durable progress model/report. |
| Certificate downloads | Partial | `34_Student_Certificates/index.php` | Replace fake PDFs and protect file access. |
| Instructor chat | Present | `33_Student_Messages/index.php` | Add stronger authorization in fetch endpoint. |

### Guest

| Feature | Status | Evidence | Implementation plan |
|---|---:|---|---|
| Course list | Present | `42_Public_Course_Catalog/index.php` | Good baseline. |
| Instrument categories | Partial | filters in course catalog; no dedicated public page | Add public instruments page or restore `44_Public_Instrument_Categories`. |
| School information | Partial | homepage has marketing content | Add/about restore `46_Public_About_Us`. |
| Contact page | Missing | no `47_Public_Contact_Us` | Add contact page/form with spam protection and mail/log backend. |

## Phase 3: Navigation Audit

### Navigation Matrix

| Role | Entry | Sidebar/navbar destinations | Status |
|---|---|---|---|
| Guest | `/43_Public_Homepage/index.php` | Home, Courses, Login, Join Now | Contact/About/Instrument category missing from nav and filesystem. |
| Admin | `/02_Admin_Dashboard/index.php` | Dashboard, Instructors, Instruments, Courses, Assignments, Enrollments, Reports | Main links exist. |
| Instructor | `/17_Instructor_Dashboard/index.php` | Dashboard, Materials, Schedules, My Courses, Attendance, Assignments, Reviews, Students, Certificates | Main links exist, but no Messages/Chat item. |
| Student | `/40_Student_Dashboard/index.php` | Dashboard, Messages, Certificates, Attendance, Recordings, Assignments, My Courses | No dedicated Schedules or Materials nav item. |

### Broken, Risky, or Inconsistent Navigation

- `README.md` lists many page directories that do not exist. Any references or expectations from the generated Stitch project are stale.
- `39_Student_My_Courses/index.php` links students to `/16_Lesson_Materials/index.php?course_id=...`, but that page requires instructor role. A student clicking "Materials" receives 403.
- `36_Student_Recordings/index.php` uses `/37_Student_Assignments_1/index.php?course_id=1&assignment_id=...`; hard-coded `course_id=1` can route to the wrong course.
- Instructor sidebar includes `27_Course_Students/index.php` without `course_id`; the page attempts a first assigned course fallback. Acceptable, but ambiguous when no course exists.
- Public navbar lacks About, Contact, and public instrument categories, despite stakeholder requirements.
- Logout exists and redirects to `/auth/login.php`.
- Login redirects work by role in `auth/login.php`.
- JSON APIs often include `config/auth_guard.php` instead of `api/middleware.php`; unauthenticated JSON requests can receive HTML redirects instead of JSON errors.

## Phase 4: Database Audit

### ERD Explanation

- `users` is the identity table. `students` and `instructors` are 1:1 profile tables keyed by `users.id`.
- `instruments` categorizes `courses`.
- `instructor_assignments` is many-to-many between instructors and courses.
- `enrollments` is many-to-many between students and courses with approval state.
- `schedules` belong to a course and instructor.
- `attendance` links students to schedule/date records.
- `materials` belong to courses and are uploaded by users.
- `assignments` belong to courses.
- `submissions` belong to assignments and students; grading references instructors.
- `ratings_feedback` stores one student rating per course.
- `chat_messages` stores sender/receiver messages scoped by course.
- `certificates` stores one certificate per student/course.
- `user_uploads` tracks general uploads per user.

### Database Findings

| Severity | Finding | Evidence | Fix |
|---|---|---|---|
| HIGH | Missing unique key for assignment resubmission upsert | `student/submissions.php` uses `ON DUPLICATE KEY UPDATE`; `database/schema.sql` submissions table has no unique `(assignment_id, student_id)` | Add `UNIQUE KEY uniq_submission_student_assignment (assignment_id, student_id)`. |
| HIGH | API student registration creates orphan role profile state | `api/auth/register.php` inserts into `users` only | Insert into `students` in same transaction for student role; into `instructors` for instructor role. |
| HIGH | No quiz schema | no quiz tables | Add quiz tables listed above. |
| MEDIUM | `course_classes` exists but no UI/module uses it | `database/schema.sql` defines table only | Build syllabus/module CRUD or remove until needed. |
| MEDIUM | `ratings_feedback` exists but no student feedback submission UI found | table and report query exist | Add course feedback form or remove report dependency. |
| MEDIUM | Missing composite indexes for common joins | attendance, submissions, enrollments queries filter multi-column | Add indexes: `(student_id, course_id, status)`, `(course_id, instructor_id)`, `(student_id, assignment_id)`, `(course_id, created_at)`. |
| MEDIUM | No audit log table | mutating admin/instructor actions untracked | Add `audit_logs(actor_id, action, entity_type, entity_id, metadata, created_at)`. |
| LOW | Enum-heavy schema reduces future flexibility | roles, statuses, difficulty | Accept for small app or migrate to lookup tables for extensibility. |

## Phase 5: Authentication and Security Audit

### Vulnerabilities

| Severity | Finding | Evidence | Fix |
|---|---|---|---|
| CRITICAL | No CSRF protection across session-authenticated POST/DELETE endpoints | Mutating endpoints/forms have no token checks | Add session CSRF tokens to all forms/fetch requests; reject missing/invalid tokens. |
| CRITICAL | Uploaded files are publicly addressable | Pages link `<?= htmlspecialchars($m['file_path']) ?>`, submission paths, certificate paths directly | Store outside webroot or deny direct `/uploads`; serve only through access-checked download controller. |
| HIGH | Chat fetch lacks membership validation | `api/chat/fetch_messages.php` marks/fetches by `course_id` and `partner_id` only | Validate current user belongs to course and partner is valid instructor/student for that course before update/select. |
| HIGH | Chat send does not validate receiver membership | `api/chat/send_message.php` validates sender only | Validate receiver is assigned instructor or approved student in same course. |
| HIGH | API upload method check calls undefined function | `api/upload.php` calls `sendJSONError()` but includes `config/auth_guard.php`, not `api/middleware.php` | Require `api/middleware.php` or replace with local JSON error response. |
| HIGH | Chat send method check calls undefined function | `api/chat/send_message.php` same issue | Require `api/middleware.php`. |
| HIGH | File MIME is recorded but not enforced | `api/upload.php` uses extension allowlist and `mime_content_type()` only stores result | Validate MIME against strict extension map, reject mismatches. |
| HIGH | Instructor material/submission uploads validate extension only | `instructor/materials.php`, `student/submissions.php` | Add MIME validation, file signature checks, AV scanning if production. |
| HIGH | DB exception messages leak internals | `config/db.php`, many catch blocks append `$e->getMessage()` | Log server-side; return generic client error. |
| MEDIUM | Session cookies lack explicit `SameSite` | `config/auth_guard.php`, `api/middleware.php` | Set `session.cookie_samesite=Lax` or `Strict`; always set `Secure` in production. |
| MEDIUM | No login rate limiting or lockout | `auth/login.php`, `api/auth/login.php` | Add per-IP/email throttling and account lockout alerts. |
| MEDIUM | Weak password policy | min length 6 | Raise to 12+, breach/common password check, optional reset flow. |
| MEDIUM | Certificate hash uses hard-coded salt | `student/certificates.php`, `28_Bulk_Certificates/index.php` | Move secret to env/config outside repo; use random bytes. |
| LOW | Role enum excludes guest | `database/schema.sql` users role has admin/instructor/student | OK if guests are unauthenticated; do not treat guest as user role. |

### Access Control Summary

Good:

- Most role pages call `requireRole(...)`.
- Instructor endpoints generally verify course assignment.
- Student endpoints generally verify approved enrollment.
- Prepared statements are used for user inputs.

Needs work:

- Centralize API auth behavior.
- Prevent direct file access.
- Add CSRF.
- Add authorization checks for chat fetch/send receiver.
- Add audit logging for admin/instructor mutations.

## Phase 6: UI Consistency Audit

### UI Debt Report

| Severity | Area | Evidence | Fix |
|---|---|---|---|
| HIGH | Login/register visual system diverges | `auth/login.php`, `auth/register.php` use standalone dark gradient/card styles | Move auth pages onto shared `config/design-system.php` tokens or formalize as auth layout. |
| HIGH | Student material access routes to instructor page | `39_Student_My_Courses/index.php` link to `/16_Lesson_Materials/index.php` | Add `student/materials` page and nav link. |
| MEDIUM | Card radius consistency drift | shared CSS uses 12px cards; design instruction expects <=8px unless system requires | Standardize card radius in design system. |
| MEDIUM | Tailwind CDN in production | `lms_head()` and auth pages load CDN scripts | Compile CSS for production; avoid runtime Tailwind CDN. |
| MEDIUM | Accessibility gaps in dynamic controls | many icon-only buttons lack visible labels/aria-labels | Add `aria-label` to edit/delete/download icon buttons. |
| MEDIUM | Form autocomplete incomplete | many form inputs omit `autocomplete`; search uses plain input | Add meaningful `autocomplete`, `name`, labels. |
| MEDIUM | Placeholder punctuation | many placeholders end with `...` | Use ellipsis character or adjust copy, but only after broader design pass. |
| LOW | Public pages use remote Google image URLs | catalog/home cards | Use stable local/CDN-controlled assets with dimensions to avoid CLS/broken images. |
| LOW | Mixed inline styles and utility classes | dashboards use many inline `style=` color overrides | Move to role token classes. |

## Exact Implementation Plan for Missing/Partial Features

1. Security foundation first:
   - Add `config/csrf.php` with `csrf_token()` and `verify_csrf()`.
   - Add CSRF hidden inputs to all PHP forms.
   - Add `X-CSRF-Token` header to every fetch mutation.
   - Switch JSON endpoints to `api/middleware.php` for consistent JSON auth.

2. File security:
   - Move upload directory outside document root, or add server rule denying direct `/uploads`.
   - Replace raw file links with `/api/view_file.php?id=...`.
   - Extend `api/view_file.php` to support `materials`, `submissions`, `certificates`, and `user_uploads`, each with ownership/course authorization.
   - Validate MIME and extensions together.

3. Database migrations:
   - Add unique key on `submissions(assignment_id, student_id)`.
   - Add quiz tables.
   - Add class participation/session tables.
   - Add audit logs.
   - Add missing composite indexes.

4. Feature completion:
   - Quizzes: instructor CRUD page, student attempt page, grading/report aggregation.
   - Schedule editing: implement PUT/PATCH in `instructor/schedules.php`, add edit modal in `18_Class_Schedules/index.php`.
   - Instructor chat: add `instructor/messages.php` or restore screen, add sidebar item, reuse chat API after authorization fix.
   - Student materials/schedules: add dedicated pages/nav entries instead of routing to instructor pages.
   - Certificates: use a PDF library/template, store real PDF, add verification view by certificate hash.
   - Guest info: add About, Contact, Instrument Categories public pages and navbar links.

5. UI consolidation:
   - Move auth pages to shared tokens/layout.
   - Replace inline role colors with design-system classes.
   - Add `aria-label`s to icon buttons and stronger focus-visible styles.
   - Compile Tailwind for production.

6. QA and DevOps:
   - Install PHP CLI locally/CI and run `php -l` for all PHP files.
   - Add PHPUnit or Pest tests for auth, enrollment, upload, chat authorization, certificates.
   - Add integration tests for role redirects and protected pages.
   - Add migration runner and seed reset script.
   - Add production `.env`/config pattern for DB credentials and secrets.

## Verification Performed

- Read project structure and all major PHP modules.
- Parsed schema and mapped relations.
- Reviewed auth, upload, chat, enrollment, materials, schedules, submissions, certificates, and reports.
- Attempted PHP lint with `php -l`; blocked because PHP CLI is not installed in current environment (`php: No such file or directory`).

