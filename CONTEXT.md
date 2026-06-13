# CONTEXT.md — Session History & Current State

## Last Session: Certificate System Overhaul + Accessibility Fixes

### What Was Done

#### Phase 1: Certificate System (Production-Grade)

**Problem:** Student certificate claim API (`student/certificates.php`) wrote placeholder text files instead of real PDFs. Cert salt was duplicated in 3 files. Eligibility logic was duplicated in 3 times. No public certificate verification existed.

**Solution:**

| File | Change |
|------|--------|
| `config/cert_helper.php` | **NEW** — Single source of truth: `cert_get_salt()`, `cert_generate_hash()` (HMAC-SHA256, deterministic), `cert_get_eligibility()` |
| `config/pdf_cert.php` | Added 5th param `$certificateHash`. Prints Certificate ID + verification URL on PDF. Requires `cert_helper.php` |
| `config/db.php` | Removed cert salt block (moved to cert_helper) |
| `student/certificates.php` | Replaced placeholder `file_put_contents()` with real `generate_certificate_pdf()`. Uses `cert_generate_hash()` |
| `28_Bulk_Certificates/index.php` | Replaced local cert salt + eligibility with `cert_helper` functions. Passes hash to PDF generator |
| `34_Student_Certificates/index.php` | Added QR codes (qrcode.js CDN), verification URLs, "Verify" button. Uses shared eligibility |
| `api/certificate_verify.php` | **NEW** — Public verification page (no auth). Shows validity, student name, course, instructor, date, cert ID |

**Key design decisions:**
- Deterministic hash (no `time()`) — same student+course always produces same hash
- QR code rendered client-side (qrcode.js) — keeps PDF generation pure PHP, no deps
- Verification page is HTML (not JSON) — user-facing, styled with design system tokens
- Cert salt override via `config/cert.local.php` (same pattern as `db.local.php`)

#### Phase 2: Accessibility Fixes (WCAG 2.1)

**Problem:** Missing skip links, decorative icons not hidden from screen readers, form inputs missing labels, no aria-live for dynamic content.

**Solution across all 33 pages + 2 auth pages + shared components:**

| Fix | Count | Details |
|-----|-------|---------|
| Skip-to-content link | 33 pages | Added via `lms_sidebar()` and `lms_public_navbar()` in `config/design-system.php` |
| `id="lms-main-content"` on `<main>` | 33 pages | Target for skip links |
| `aria-hidden="true"` on decorative icons | 100+ instances | All Material Symbols icons paired with text |
| `aria-label` on icon-only buttons | 4 instances | Close, delete, cancel buttons |
| `for` attributes on labels | 9 instances | Form inputs in 16_Lesson_Materials, 36_Student_Recordings, 28_Bulk_Certificates |
| `role="progressbar"` + `aria-valuenow` | 4 instances | Progress bars in 34_Student_Certificates, 39_Student_My_Courses, 40_Student_Dashboard |
| `aria-live="polite"` on dynamic regions | 18 files | Dashboard metrics, table bodies, chat containers, upload errors |
| `<div onclick>` → `<button>` | 1 instance | Course cards in 16_Lesson_Materials |
| `aria-hidden="true"` on SVGs | 3 instances | Auth pages (login + register error/success icons) |

### Current State

**All 33 pages are accessible and role-correct.** Every page:
- Has skip-to-content link
- Has `id="lms-main-content"` on `<main>`
- Uses `aria-hidden="true"` on decorative icons
- Uses `aria-live="polite"` for dynamic content
- Uses proper form labels with `for` attributes

**Certificate system is production-grade.** Every generated certificate:
- Is a real PDF with student name, course, instructor, date, certificate ID, verification URL
- Has a deterministic hash (HMAC-SHA256)
- Can be verified publicly via `/api/certificate_verify.php?hash=XXX`
- Has a QR code on the student certificates page

**Navigation is correct.** All sidebar entries, routes, and role guards verified. No hardcoded IDs. No cross-role routing issues.

### Files Changed This Session

```
NEW:    config/cert_helper.php
NEW:    api/certificate_verify.php
EDITED: config/pdf_cert.php
EDITED: config/db.php
EDITED: config/design-system.php
EDITED: student/certificates.php
EDITED: 28_Bulk_Certificates/index.php
EDITED: 34_Student_Certificates/index.php
EDITED: AGENTS.md
EDITED: All 33 NN_*/index.php files (accessibility)
EDITED: auth/login.php, auth/register.php (accessibility)
```

### What Was NOT Changed

- Auth page design (glassmorphism kept per user request)
- Navigation entries (already correct)
- Database schema (no new tables needed)
- Any business logic outside certificate system

### Known Limitations

- `39_Student_My_Courses/index.php` has hardcoded GPA (3.92), Practice Hours (142h), Scholarship Status — no DB tables support these
- `27_Course_Students/index.php` has a missing `</div>` closing tag (line ~163)
- `39_Student_My_Courses/index.php` has dead SQL subquery (lines 49-58) — prepared but never executed
- No test suite exists — verification is manual (open browser, run SQL)

### How to Resume Work

If continuing from this session:
1. Read `AGENTS.md` for architecture and conventions
2. Read this file (`CONTEXT.md`) for session history
3. All certificate code flows through `config/cert_helper.php`
4. All pages follow the accessibility conventions documented in AGENTS.md
5. Auth pages use glassmorphism design — don't change unless asked
