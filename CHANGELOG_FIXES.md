# Changelog Fixes

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
