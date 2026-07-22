# Final QA Checklist (Pre-Go-Live)

Use this checklist after deployment on shared hosting.

## 1) Environment & Security

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_FORCE_HTTPS=true`
- [ ] `LOG_STACK_CHANNEL=daily` and `LOG_DAILY_DAYS` set
- [ ] `.env` is outside public web access
- [ ] HTTPS enabled and forced
- [ ] `TRUSTED_HOSTS` set to production domain(s)
- [ ] `TRUSTED_PROXIES` set correctly if behind CDN/reverse proxy
- [ ] `/storage/app/private` is not public
- [ ] `public/.htaccess` is readable and has shared-hosting permissions (`644`)
- [ ] `vendor/autoload.php` exists after Composer install
- [ ] Run: `php scripts/preflight.php`
- [ ] Run: `./scripts/target-cutover.sh` on target server and save log artifact
- [ ] Verify response headers include CSP / X-Frame-Options / X-Content-Type-Options

## 2) Database & Seed

- [ ] `php artisan migrate --force` succeeds
- [ ] `php artisan db:seed --force` succeeds
- [ ] `php artisan bassir:create-owner` executed with a private production password
- [ ] `php artisan config:cache` succeeds
- [ ] `php artisan route:cache` succeeds
- [ ] `php artisan view:cache` succeeds
- [ ] `php artisan test` succeeds
- [ ] Seeded owner logs in (`yahya`) and changes password immediately
- [ ] At least one active `SUPER_ADMIN` exists

## 3) Integrations

- [ ] OpenAI key stored in `/integrations` or `.env`
- [ ] Google CSE key + CSE ID configured
- [ ] Bing Search key configured
- [ ] SerpAPI key configured (optional but recommended)
- [ ] Agency Feed URL/token configured if agency integration is used
- [ ] OCR key configured if scanned CV OCR is required
- [ ] Run: `./scripts/apply-integrations.sh`
- [ ] Run: `php scripts/integration-check.php`
- [ ] Optional remote test: `RUN_REMOTE_INTEGRATION_TESTS=true php scripts/integration-check.php`

## 4) Functional Smoke Tests

- [ ] Login/logout works
- [ ] `/login` opens with GET and does not return 405
- [ ] Repeated failed login attempts trigger temporary lockout message
- [ ] Responses include `X-Trace-Id` header for request tracing
- [ ] Authenticated pages return `Cache-Control: no-store`
- [ ] Mobile API login works (`POST /api/mobile/auth/login`)
- [ ] Mobile bearer token accesses `/api/mobile/dashboard/summary`
- [ ] Dashboard loads KPIs
- [ ] Add specialization
- [ ] Add candidate manually
- [ ] `/candidates/create` opens for authorized users and does not return 404
- [ ] Upload CV and parsed profile appears
- [ ] Candidate private CV download works for authorized users
- [ ] Bulk import CSV works and duplicates are skipped
- [ ] Create job and run AI matching
- [ ] `/jobs/create` opens for authorized users and does not return 404
- [ ] Open `/matching` and verify AI comparison table renders
- [ ] Open `/jobs/{job}/ranking` and verify candidates are ranked
- [ ] Rebuild AI ranking for one job
- [ ] Filter ranking by score, skill, city, salary, availability, language, education
- [ ] Mark AI feedback as Correct/Wrong/Needs review
- [ ] Move one ranked candidate to shortlist
- [ ] Reject one weak candidate from ranking page
- [ ] Schedule interview from ranking page
- [ ] Open `/candidates/{candidate}/job-matches`
- [ ] Refresh candidate-to-job matches
- [ ] Open `/search-assistant` and search a natural-language request
- [ ] Create a talent pool and save/remove one candidate
- [ ] Compare 2-5 candidates on `/candidate-comparison`
- [ ] Verify candidate quality score and CV completeness display
- [ ] Verify red flags appear but do not auto-reject candidates
- [ ] Open `/applications` and verify the recruitment pipeline renders
- [ ] Add candidate to job application
- [ ] Move application stage and verify stage history/audit log
- [ ] Schedule interview and submit feedback
- [ ] Run AI Search and import one result into candidate DB
- [ ] Manual LinkedIn URL import works (no scraping)
- [ ] Switch EN/AR locale and verify RTL/LTR layout behavior
- [ ] Export reports CSV files
- [ ] Audit logs record key actions
- [ ] User management create/update user
- [ ] Password change enforcement works for `must_change_password=1`

## 5) Compliance Checks

- [ ] LinkedIn sourcing is manual-only (URL metadata only)
- [ ] No bypass/scraping scripts are deployed
- [ ] Candidate consent status is captured before outreach
- [ ] Source and consent note stored in `candidate_sources`

## 6) Backup & Operations

- [ ] Daily DB backup configured (`./scripts/ops-backup.sh`)
- [ ] Daily backup for `storage/app/private` (`./scripts/ops-backup.sh`)
- [ ] Log rotation retention configured
- [ ] Uptime monitoring on `/health` (`./scripts/ops-health-monitor.sh`)
- [ ] Cron installation validated (`INSTALL_CRON=true ./scripts/ops-install-cron.sh`)
- [ ] Baseline smoke suite executed (`./scripts/smoke-test-suite.sh`)
- [ ] Authenticated smoke suite executed with real credentials
- [ ] Full server QA suite executed (`./scripts/qa-server-suite.sh`)

## 7) HR/UAT Sign-off

- [ ] `docs/HR_UAT_SIGNOFF_AR.md` completed
- [ ] HR manager signed
- [ ] Hiring manager signed
- [ ] Technical owner signed
- [ ] All mandatory logs attached
