# Bassir AI Recruitment System

Laravel shared-hosting edition, powered by Bassir Technology.

This package is redesigned for cPanel/Plesk/DirectAdmin shared hosting using:

- PHP 8.2+
- Laravel 12
- MySQL/MariaDB
- Blade UI
- Session authentication
- Private CV storage
- Legal AI CV sourcing via official APIs only

## Features

- Secure login/logout.
- Security headers middleware (CSP, HSTS on HTTPS, X-Frame-Options, and hardening headers).
- Enforced HTTPS redirect middleware with configurable trusted proxies/hosts for reverse-proxy shared hosting.
- No-store cache headers for authenticated/sensitive pages.
- Login throttling, password-change enforcement, and audit logging.
- Login abuse protection with username+IP rate limiting and failed-login audit events.
- Role-based access control for `SUPER_ADMIN`, `HR_MANAGER`, `HIRING_MANAGER`, `INTERVIEWER`, `VIEWER`.
- SaaS-ready company/tenant foundation with company-scoped users, candidates, jobs, interviews, AI searches, salary benchmarks, applications, reports, and audit logs.
- Commercial roles for `SUPER_ADMIN`, `COMPANY_ADMIN`, `HR_MANAGER`, `RECRUITER`, `HIRING_MANAGER`, `INTERVIEWER`, `VIEWER`, `AUDITOR`, and `CANDIDATE`.
- Admin dashboard with pipeline, sourcing, and search KPIs.
- Candidate CRM with skills, languages, notes, tags, status, consent, source tracking, duplicate hash, and structured education/experience/certifications.
- Recruitment pipeline module with candidate applications, stage updates, stage history, and audit logs.
- AI Candidate Ranking per job with 80%+ / 60-79% / weak match grouping.
- Job-to-candidate auto matching when a job is created.
- Candidate-to-job matching when a CV is uploaded or a recruiter refreshes matches.
- Smart HR search assistant for natural-language candidate search.
- Talent pool module for future hiring categories.
- Candidate comparison tool for side-by-side recruiter decisions.
- Recruiter feedback loop for AI recommendations (`Correct`, `Wrong`, `Needs review`).
- Red flag detection and categorized interview question generation.
- Candidate quality score based on CV completeness, skills, experience, education, certifications, languages, interview feedback, recruiter rating, and hiring outcome.
- Specializations management for engineering and custom categories.
- Job requisitions and persisted AI matching.
- Dedicated AI Matching page with candidate comparison and shortlist intelligence.
- Interview scheduling and feedback management.
- Salary benchmark management and salary estimation.
- Encrypted API integration key management.
- CV upload and parsing for PDF/DOC/DOCX plus OCR-ready image/scanned CV ingestion.
- CSV/XLS/XLSX bulk candidate import.
- Mobile API for Android/iOS (`/api/mobile/*`) with bearer token auth.
- AI CV sourcing through Google Custom Search API, Bing Search API, SerpAPI, and permitted agency feeds.
- Resilient provider calls with timeout/error fallback so one API outage does not break AI Search.
- Manual LinkedIn URL import (official/manual only, no scraping/bypass).
- AI search result to candidate import workflow with consent status.
- Route-level throttling on AI search, uploads/imports, matching, interview feedback, user/admin writes.
- Import hardening for CSV/XLS/XLSX (header validation, empty-row cleanup, row-limit guard).
- Password complexity enforcement for admin-created users and password updates.
- Daily log rotation support via `LOG_STACK_CHANNEL=daily` and `LOG_DAILY_DAYS`.
- Reports dashboard with multi-export CSV reports (pipeline, sources, interviews, salary, AI search success).
- User management page, settings page, and audit logs page.
- EN/AR session locale switch with RTL/LTR layout support.
- Request trace context (`X-Trace-Id`) for observability and support diagnostics.
- Private CV document download endpoint with access control checks.
- CV upload hardening with extension validation, magic-number content checks, safe file names, private storage, download counters, and optional malware scan command hook.
- Enhanced CV parsing for current company, previous companies, city, nationality, years of experience, industry, notice period, salary expectation, education, certifications, languages, skills, and summary.
- Salary estimation service and salary benchmark tables.
- MySQL migrations and seed data.
- PHPUnit smoke/unit test scaffold covering public pages, commercial routes, scoring, tenant basics, and file security.

## Shared Hosting Install

1. Upload this folder outside `public_html`, for example:

```bash
/home/account/bassir
```

2. Point your domain document root to:

```bash
/home/account/bassir/public
```

If your host cannot change document root, copy the contents of `public/` to `public_html/` and update `index.php` paths to point to the private app folder.

Shared-hosting fallback is also supported at URLs such as:

```text
https://your-domain.com/rec/public
```

In that mode set:

```env
APP_URL=https://your-domain.com/rec/public
```

3. Install dependencies if `vendor/autoload.php` is not already included from the handoff ZIP:

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

The final handoff ZIP includes `composer.lock` and production `vendor`. If your shared host requires reinstalling dependencies and Composer asks for `ext-gd` because of `phpoffice/phpword`, enable the PHP `gd` extension. A temporary install fallback is:

```bash
composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-gd
```

4. Configure `.env` with MySQL and API keys.

5. Run migrations and seed:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

6. Create or verify the owner account:

```bash
php artisan bassir:create-owner --username=yahya --email=owner@example.com --name="Bassir Owner" --company="Bassir Technology"
```

The seeder still creates a local demo owner for development. For production, set a secure password privately and change it immediately after first login.

7. Run shared-hosting preflight:

```bash
php scripts/preflight.php
```

8. If you deploy behind Cloudflare / reverse proxy, configure:

```bash
TRUSTED_PROXIES=*
TRUSTED_HOSTS=your-domain.com,www.your-domain.com
APP_FORCE_HTTPS=true
# Use only if SSL is terminated before PHP and redirects loop.
APP_TRUST_PROXY_HTTPS_HEADERS=true
```

9. Recommended production logging:

```bash
LOG_CHANNEL=stack
LOG_STACK_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
```

## Integration Keys (Optional)

Save keys in `/integrations` (encrypted at rest) or `.env`:

- `openai`
- `google_cse_key`
- `google_cse_id`
- `bing_search`
- `serpapi`
- `agency_feed_url`
- `agency_feed_token`
- `ocr_space`

## AI Selection Acceleration Pages

- `/jobs/{job}/ranking`: Smart Candidate Ranking for a job.
- `/candidates/{candidate}/job-matches`: Candidate-to-job recommendations.
- `/search-assistant`: Natural-language HR talent search.
- `/talent-pools`: Talent pool management.
- `/candidate-comparison`: Compare 2-5 candidates side by side.

The ranking engine has deterministic fallback logic and does not crash when an external AI provider is unavailable. OpenAI enriches summaries where configured, while local scoring remains available for business continuity.

## Production Operations (Items 2, 3, 4, 5)

Configure integrations:

```bash
./scripts/apply-integrations.sh
php scripts/integration-check.php
```

Install backup + health monitoring cron:

```bash
./scripts/ops-backup.sh
./scripts/ops-health-monitor.sh
INSTALL_CRON=true ./scripts/ops-install-cron.sh
```

Run smoke suite:

```bash
./scripts/smoke-test-suite.sh
```

Authenticated smoke suite:

```bash
SMOKE_USERNAME=yahya SMOKE_PASSWORD='YourPassword' ./scripts/smoke-test-suite.sh
```

Full server QA suite:

```bash
./scripts/qa-server-suite.sh
```

Application test suite after Composer install:

```bash
php artisan test
```

Finalize HR acceptance:

- Fill and sign `docs/HR_UAT_SIGNOFF_AR.md`

## Compliance

The system does not scrape LinkedIn or bypass Google/LinkedIn protections. Allowed sourcing paths:

- Google Custom Search API.
- Bing Search API.
- SerpAPI.
- Agency/job-board API feeds when contractually permitted.
- Manual LinkedIn URL import.
- Candidate-provided CVs.
- CSV/Excel imports with lawful source and consent basis.

AI recommendations are decision-support only. Human HR review is required before interview, rejection, offer, or hiring decisions.

## Important Paths

- Routes: `routes/web.php`, `routes/api.php`
- Controllers: `app/Http/Controllers`
- Services: `app/Services`
- Models: `app/Models`
- Migrations: `database/migrations`
- Views: `resources/views`
- Private CV storage: `storage/app/private/cvs`
- Shared hosting preflight script: `scripts/preflight.php`
- Target server one-command cutover: `scripts/target-cutover.sh`
- Server QA suite: `scripts/qa-server-suite.sh`
- QA checklist: `docs/FINAL_QA_CHECKLIST.md`
- API reference: `docs/API_REFERENCE.md`
- Shared hosting install: `docs/SHARED_HOSTING_INSTALL.md`
- Commercial readiness report: `docs/COMMERCIAL_READINESS_REPORT_AR.md`
- Target cutover runbook: `docs/TARGET_SERVER_CUTOVER.md`
- Production operations runbook: `docs/PRODUCTION_OPERATIONS_RUNBOOK.md`
- HR UAT sign-off template: `docs/HR_UAT_SIGNOFF_AR.md`
- Mobile app scaffold (Expo): `../bassir-mobile-expo`
