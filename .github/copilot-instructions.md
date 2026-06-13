# Copilot instructions — Lyra Academy Music LMS

Summary
- Purpose: Help Copilot-style agents navigate this pure-PHP, MySQL repo and surface actionable guidance (no fluff).

Build / test / lint
- No build, test, or lint toolchain in repo (no npm, composer, or CI).
- Useful quick commands:
  - Run a local PHP server to view pages: php -S localhost:8000 -t .  then open http://localhost:8000/43_Public_Homepage/index.php
  - Re-seed admin user: php database/reseed_admin.php
  - Run migration: mysql -u root music_elms < database/migration_v2.sql
  - Rollback: mysql -u root music_elms < database/rollback_v2.sql
- No unit tests exist; mention this in suggestions rather than assuming tests when generating edits.

High-level architecture (big picture)
- Stack: PHP 8 + MySQL (PDO). Frontend uses Tailwind via CDN (no build step).
- Repository layout (conceptual):
  - NN_<Screen>/index.php — page entrypoints (number is UI ID, do NOT renumber)
  - auth/ — login/logout/register pages
  - admin/, instructor/, student/ — JSON API handlers for roles
  - api/ — public JSON endpoints (api/auth, api/chat, api/upload.php, api/view_file.php, api/middleware.php)
  - config/ — db.php, auth_guard.php, design-system.php, nav.php, layout.css, csrf.php, pdf_cert.php
  - database/ — schema.sql (source of truth), seed.sql, migration/rollback scripts
  - .agents/skills/ — repo-local agent skill packs (not runtime code)

- Page bootstrap: every page must include config/auth_guard.php and design-system.php (design-system is SINGLE SOURCE OF TRUTH for tokens and layout). Example pattern:
  require_once __DIR__ . '/../config/auth_guard.php';
  require_once __DIR__ . '/../config/design-system.php';
  requireRole('admin');
  $pdo = require_once __DIR__ . '/../config/db.php';

Key conventions (repo-specific rules)
- Two auth guards — do not mix:
  - HTML pages use config/auth_guard.php (redirects to login, renders HTML 403)
  - API endpoints use api/middleware.php (returns JSON errors via sendJSONError())
  - Exception: api/view_file.php uses config/auth_guard.php for browser-friendly 302 behavior.
- Absolute paths used in nav and links (e.g., /02_Admin_Dashboard/index.php). Web root must be the repository root when serving.
- NN_ prefix: numeric UI IDs are fixed identifiers. Add new screens as NN_NewScreen and update $LMS_SIDEBARS in config/nav.php — do NOT renumber existing screens.
- No autoloader: all includes are explicit require_once with __DIR__ paths.
- config/db.php returns a PDO instance — assign it: $pdo = require_once __DIR__ . '/../config/db.php';
- Local overrides (never committed): config/db.local.php and config/cert.local.php — create these for machine-specific credentials/overrides.
- File uploads: uploaded files go to /uploads/{user_id}/{type}/ (uploads/ is created on first upload). Allowed: MP3, WAV, MP4, PDF. Max 20MB.
- CSRF: HMAC-SHA256(session_id()), 2-hour expiry. Enforced on all POST API endpoints + auth/login.php and auth/register.php. Header and JSON body token checks supported.
- Cert generation: config/pdf_cert.php is pure PHP (no external libs); salt is 'LyraAcademySecretSalt2026' (override via config/cert.local.php if needed).

Database guidance
- Source of truth: database/schema.sql (v2, 25 tables). Use SQL files for migration and rollback.
- Default DB credentials in config/db.php: host=127.0.0.1, db=music_elms, user=root, pass=''. Use config/db.local.php on dev machines.
- Re-seed / reset convenience commands are in database/ (see reseed_admin.php and seed.sql).

Searching & verification shortcuts (useful for automated agents)
- Find pages/screens: rg "^NN_" --files
- Re-hash admin: php database/reseed_admin.php
- Verify no JSON endpoints use page guard:
  rg -l "auth_guard" --type php | xargs rg -l "Content-Type: application/json"
- Check for raw error leaks:
  rg "\$e->getMessage\(\)" --type php

AI / agent notes
- There is an AGENTS.md with an agent-focused summary. Also see .agents/skills/ for local skill packs. Many skills target other stacks — prefer human-like recommendations for PHP-specific changes.
- When proposing edits, prefer small, surgical changes to PHP files. Avoid adding unrelated infrastructure (CI, composer, npm) without explicit approval.

Files of interest (entry points for human/agent investigation)
- README.md, AGENTS.md, config/, api/, admin/, instructor/, student/, database/schema.sql

After-creation
- If desired, configure an MCP server for Playwright or web testing; ask if automated browser testing servers should be added. (Do not create servers without permission.)

----
Created from README.md and AGENTS.md. Keep this file short and actionable; update when adding tests, CI, or build tooling.
