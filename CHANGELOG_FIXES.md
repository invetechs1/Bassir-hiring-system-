# Changelog Fixes

## 2026-07-21 Continuous Integration

- Added a GitHub Actions CI workflow (`.github/workflows/ci.yml`) that runs on
  every push and pull request across PHP 8.2 and 8.4: installs dependencies,
  runs the production cache gate (config/route/view caching), migrates+seeds on
  SQLite, and executes the full PHPUnit suite.
- Added a CI status badge to the README.
- Verified locally: the exact workflow command sequence is green (64 tests).

## 2026-07-21 Background Queue for Heavy Work

- Moved AI candidate ranking (on job create/update) and scheduled auto-sourcing
  runs onto Laravel's queue via `RankJobCandidates` and `RunSourcingSearch` jobs,
  so large runs never block a web request or hit PHP's max execution time.
- Stays `sync` (inline) by default for shared hosting; set
  `QUEUE_CONNECTION=database` + run a worker for background processing at scale.
- Added `config/queue.php` and a queue-tables migration using `queue_jobs`
  (renamed to avoid colliding with the recruitment `jobs` requisitions table),
  plus `.env.example` and README worker/cron guidance.
- Fixed a latent bug: `SourcingRun` counter columns were null in memory before a
  refresh, which crashed a zero-result sourcing run when writing
  `last_import_count`; the model now defaults all counters to 0.
- Tests: ranking dispatched on job create/update, ranking handler produces
  scores, one queued job per active search, and the sourcing handler records a
  run (suite 58 -> 63).

## 2026-07-21 Form Request Validation

- Extracted candidate and job write-validation into dedicated Form Request
  classes (`Store*/Update*CandidateRequest`, `Store*/Update*JobRequest`),
  closing the documented "add Form Request classes for all write controllers"
  follow-up.
- Removed duplicated inline validation from the controllers (job create/update
  rules were previously repeated) and the ad-hoc tenant-unique helper; the
  tenant-scoped unique email/LinkedIn rule with self-ignore now lives in one
  place and is shared by create and update.
- Added tests for required-field enforcement, duplicate-email rejection, and
  valid payloads passing through.

## 2026-07-21 Archive & Restore

- Added recoverable soft-delete archiving for candidates and jobs (never a hard
  delete of PII): archive from the edit screen, view archived records via an
  Active/Archived toggle on each list, and restore with one click.
- Archive/restore are permission-gated (`candidate.write` / `job.write`),
  tenant-scoped, and audit-logged (`*_ARCHIVE` / `*_RESTORE`).
- Archived records are automatically excluded from all default queries, lists,
  matching, and reports via Eloquent SoftDeletes.
- Lists now show session flash messages and empty-state text; pagination
  preserves search/filter/archived query strings.
- Tests cover archive, hidden-from-default-queries, restore, and the
  read-only-role guard.

## 2026-07-21 Edit Screens

- Added candidate edit/update (`/candidates/{id}/edit`) and job edit/update
  (`/jobs/{id}/edit`), closing the previously documented gap where records could
  be created and viewed but not modified.
- Edit is permission-gated (`candidate.write` / `job.write`), tenant-scoped,
  audit-logged, and preserves consent metadata; candidate skills/languages and
  job required-skills are replaced cleanly on save, and jobs are re-ranked.
- Tenant-unique email/LinkedIn validation now ignores the record being edited.
- Added "Edit" actions on the candidate and job profile pages.
- Tests cover edit page load, update persistence, self-unique validation, and
  the read-only-role guard.


## 2026-06-04 Production Repair Pass

- Upgraded project dependency target from Laravel 11 to Laravel 12 in `composer.json`.
- Added `GET /login` route to avoid 405 errors when users open login directly.
- Reordered candidate/job resource routes so `create` routes are registered before `{candidate}` and `{job}` show routes.
- Replaced API Closure routes with cache-safe controllers:
  - `Api/PortalCandidateController`
  - `Api/PortalJobController`
  - `Api/PortalMeController`
- Added branded error pages for:
  - `403`
  - `404`
  - `405`
- Changed Blade role checks for visible actions to permission checks where practical.
- Hid action links/buttons from users without the required permission:
  - Add Candidate
  - Create Job
  - Schedule Interview
  - Save Interview Feedback
  - Run/Rebuild AI Matching
  - System defaults settings
  - Admin sidebar links
- Hardened HTTPS redirect handling for proxy/shared-hosting deployments through `APP_TRUST_PROXY_HTTPS_HEADERS`.
- Updated `.env.example` with reverse-proxy and shared-hosting notes.
- Expanded `scripts/preflight.php` checks for:
  - `vendor/autoload.php`
  - `public/.htaccess` readability and shared-hosting permissions
  - proxy HTTPS handling when `APP_URL` is HTTPS
- Expanded `scripts/smoke-test-suite.sh` to test:
  - `GET /login`
  - web login with CSRF/session cookies
  - dashboard and key authenticated pages
  - admin-only pages when admin credentials are provided
- Added `scripts/qa-server-suite.sh` for server-side PHP lint, cache checks, migration check, preflight, and smoke testing.
- Updated deployment documentation for:
  - preferred document-root-to-public mode
  - `/rec/public` shared-hosting fallback mode
  - Cloudflare/proxy settings
  - authenticated smoke testing
- Added `docs/CHATGPT_REVIEW_CONTEXT_AR.md` for external review context.

## Known Follow-up Work

- Add full Laravel feature tests once a PHP/Composer test runtime is available.
- Add Form Request classes for all write controllers.
- Add candidate/job edit screens.
- Add richer enterprise UI polish with icons, active sidebar states, and mobile collapse.
- Add queue worker abstraction for high-volume AI/CV workloads.

## 2026-06-06 Commercial Foundation Pass

- Added SaaS-ready tenant foundation:
  - `companies`
  - `departments`
  - `branches`
  - `company_id` scoping on key operational tables
- Added company-scoped candidate uniqueness for email and LinkedIn URL.
- Added commercial roles:
  - `COMPANY_ADMIN`
  - `RECRUITER`
  - `AUDITOR`
  - `CANDIDATE`
- Tightened web read permissions for dashboard, candidates, jobs, matching, interviews, and pipeline.
- Tightened mobile/portal API read permissions for candidates, jobs, and dashboard summary.
- Restricted global integrations and system defaults to `SUPER_ADMIN`.
- Added recruitment pipeline module:
  - `/applications`
  - candidate-to-job applications
  - pipeline stage updates
  - stage history records
  - audit logs
- Added candidate application and pipeline history models.
- Added tenant-scoped audit log viewing.
- Added tenant-scoped AI matching page.
- Added secure CV handling improvements:
  - magic-number document checks
  - private CV storage path
  - optional malware scan command hook
  - download count and last download timestamp
- Added owner creation command:
  - `php artisan bassir:create-owner`
- Added privacy notice page:
  - `/privacy`
- Added PHPUnit scaffolding and tests for:
  - public pages
  - route registration
  - candidate scoring
  - tenant basics
  - file security

## Remaining Commercial Roadmap

- Build true candidate self-service dashboard and candidate-to-user ownership mapping.
- Add edit/update/delete screens for jobs, candidates, departments, branches, and companies.
- Add subscription/billing tenant management if sold as multi-tenant SaaS.
- Move long AI/CV workloads to queues for high volume.
- Add full browser/UI regression tests after PHP/Composer runtime is available.

## 2026-06-06 AI Selection Acceleration Pass

- Added AI Candidate Ranking per job:
  - `/jobs/{job}/ranking`
  - ranking rebuild
  - recruiter decision capture
  - AI feedback loop
- Added Candidate-to-Job Matching:
  - `/candidates/{candidate}/job-matches`
  - automatic refresh after CV upload
- Added Natural Language HR Search Assistant:
  - `/search-assistant`
- Added Talent Pools:
  - `/talent-pools`
  - candidate save/remove workflow
- Added Candidate Comparison:
  - `/candidate-comparison`
- Enhanced candidate scoring:
  - education score
  - location fit score
  - notice period fit
  - ranking band
  - red flag detection
  - categorized interview questions
- Enhanced CV parser fields:
  - city
  - current job title
  - current company
  - previous companies
  - years of experience
  - industry
  - notice period
  - summary
- Added candidate quality score and quality factors.
- Added time-to-hire dashboard KPIs.
- Added tests for new route registration and candidate quality scoring.
