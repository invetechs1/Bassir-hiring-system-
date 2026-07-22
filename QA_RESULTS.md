# QA Results

## 2026-07-21 Go-Live Rebuild Verification

The system was rebuilt into a clean Git repository (vendor and runtime state
excluded, `composer.lock` retained) and re-verified end-to-end on a native
PHP 8.4 / Composer runtime.

Runtime QA executed:

```bash
composer install --optimize-autoloader        # 82 packages, prod + dev
php artisan migrate:fresh --seed --force       # 9 migrations + demo seed, PASS
php artisan config:cache && php artisan route:cache && php artisan view:cache
php scripts/preflight.php                       # 26 passed / 0 failed on .env.example
php vendor/bin/phpunit                           # 40 passed, 90 assertions
```

Results:

- Laravel Framework 12.61.1 boots; `/`, `/login`, `/privacy` return 200,
  `/dashboard` redirects guests to login, `/health` returns
  `{app:ok, database:ok, storage:ok}`.
- Route cache builds cleanly (no route-action closures).
- Preflight passes 26/26 against the production `.env.example`.
- Automated test suite expanded from 9 to 40 tests (90 assertions):
  - `AuthenticatedSmokeTest`: 21 authenticated pages load without error,
    record-scoped candidate/job pages, all five CSV report exports, and the
    candidate/job/specialization create workflows.
  - `MobileApiTest`: bearer-token login, protected endpoints, and rejection of
    invalid credentials and inactive users.
  - Existing public-page, scoring, quality, file-security, and tenant tests.
- PHP lint (`php -l`) clean across `app`, `config`, `database`, `routes`, `tests`.

Verdict: core recruitment platform is functionally complete and launch-ready.
Remaining roadmap items (candidate self-service, edit/delete screens,
subscription billing, queue offloading for high-volume AI/CV workloads) are
post-launch enhancements, not go-live blockers.

## Local Static QA Run

Date: 2026-06-06

Environment note: local Codex workspace does not expose native `php` or `composer`, so Laravel runtime QA was executed through Docker using the official Composer image.

## Commands Run Locally

```bash
node -e "JSON.parse(require('fs').readFileSync('composer.json','utf8')); console.log('composer.json OK')"
```

Result: PASS

```bash
bash -n scripts/apply-integrations.sh scripts/ops-backup.sh scripts/ops-health-monitor.sh scripts/ops-install-cron.sh scripts/smoke-test-suite.sh scripts/target-cutover.sh scripts/qa-server-suite.sh
```

Result: PASS

```bash
rg -n "fn \\(|function \\(" routes/api.php routes/web.php
```

Result: PASS for route-cache objective. No route action closures remain in `routes/api.php`; remaining `function ()` entries are route group registration callbacks.

```bash
command -v php
command -v composer
command -v docker
```

Result: native PHP/Composer not available; Docker available.

## 2026-06-06 Static QA Addendum

Commercial-readiness pass added tenant foundation, recruitment pipeline, API permission checks, scoped audit/matching, stricter candidate uniqueness, file-security tests, and PHPUnit scaffolding.

AI selection acceleration pass added job ranking, candidate-to-job matching, natural-language talent search, talent pools, candidate comparison, recruiter feedback loop, quality score, enhanced CV parsing fields, and time-to-hire KPIs.

Commands run locally:

```bash
node -e "JSON.parse(require('fs').readFileSync('composer.json','utf8')); console.log('composer.json OK')"
bash -n scripts/*.sh
rg -n "candidate_applications|pipeline_stage_histories|candidates_company_email_unique" routes database app resources tests -S
rg -n "rankings.job|search-assistant|talent-pools|comparisons.candidates|job-matches" app routes resources tests -S
```

Result: PASS for available static checks.

Docker runtime QA was executed after generating `composer.lock` and production `vendor`:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 install --no-dev --optimize-autoloader --ignore-platform-req=ext-gd
php artisan route:list --except-vendor
php artisan migrate:fresh --seed
php artisan test
```

Result:

- Laravel Framework: 12.61.1
- Route list: PASS, 87 route-list lines.
- Migrations + seed on SQLite: PASS after making migration `2026_05_29_000006` SQLite-compatible for tests.
- Tests: PASS, 9 passed, 36 assertions.
- Route cache, config cache, and Blade view cache: PASS.
- Final `vendor` was restored to production mode with `--no-dev`.

Note: `phpoffice/phpword` 1.4 requires PHP `gd`. The packaged `vendor` is included, but if the developer reinstalls dependencies on the target server, enable `gd` or run Composer with the documented temporary `--ignore-platform-req=ext-gd` flag after confirming DOC/DOCX parsing requirements.

## Required Target Server QA

Run on a clean server or staging database:

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate --force
php artisan optimize:clear
php artisan migrate:fresh --seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php scripts/preflight.php
bash scripts/smoke-test-suite.sh
./scripts/qa-server-suite.sh
```

For staging test execution with PHPUnit, install dev dependencies first:

```bash
composer install --optimize-autoloader
php artisan test
composer install --no-dev --optimize-autoloader
```

Authenticated smoke:

```bash
SMOKE_USERNAME=yahya SMOKE_PASSWORD='OwnerPasswordProvidedSecurely' bash scripts/smoke-test-suite.sh
```

Use the owner password provided securely during installation. Do not publish production passwords in documentation or tickets.

## Manual Browser QA Required

- Login page loads at `/` and `/login`.
- Login with owner user works.
- Forced password change works.
- Dashboard opens.
- Sidebar links do not return 404/405/500.
- `/jobs/create` opens.
- `/candidates/create` opens.
- Candidate creation works.
- Job creation works.
- Recruitment pipeline opens at `/applications`.
- Candidate can be assigned to a job application.
- Pipeline stage update writes history and audit log.
- Job ranking opens at `/jobs/{id}/ranking`.
- AI ranking rebuild stores scores and recommendations.
- Recruiter can shortlist/reject/schedule from ranking page.
- AI feedback can be marked Correct/Wrong/Needs review.
- Candidate job matches open at `/candidates/{id}/job-matches`.
- Search assistant returns candidates from natural-language query.
- Talent pools can create groups and save candidates.
- Candidate comparison handles 2-5 candidates.
- Dashboard time-to-hire KPIs render.
- Interview scheduling works.
- Upload CV works.
- Reports export works.
- Logout works.

## 2026-06-06 Pre-Release Security and Page QA

See `docs/PRE_RELEASE_SECURITY_QA_2026_06_06_AR.md` for the detailed Arabic handoff report.

Executed before final packaging:

- `composer validate --strict`: PASS.
- `composer audit --no-dev`: PASS, no security vulnerability advisories found.
- Static risk scan for raw Blade output, eval/system/shell execution patterns, unsafe storage, and raw SQL: PASS with reviewed exceptions only.
- OCR filename privacy hardening: updated to send a sanitized file name through `FileSecurityService::safeOriginalName`.
- Authenticated page crawl: PASS, 27 page checks returned 200.
- Internal link crawl: PASS, no internal link errors.
- Form CSRF check: PASS, no POST/PATCH/DELETE forms missing CSRF in crawled pages.
- Action/button workflow QA: PASS, 18/18 actions succeeded.
- CV upload/download QA: PASS, PDF upload created a candidate and protected download returned 200.
- Negative security checks: PASS.
  - Unauthenticated `/dashboard` redirects to login.
  - POST without CSRF returns 419.
  - Direct private-storage URL returns 404.
