# Target Server Cutover (Final)

Use this runbook on the real production server.

## 1) SSH into server and open project

```bash
cd /home/account/bassir
```

## 2) Ensure `.env` is production-ready

Minimum critical values:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain.com`
- `APP_FORCE_HTTPS=true`
- `SESSION_SECURE_COOKIE=true`
- `TRUSTED_HOSTS=your-domain.com,www.your-domain.com`
- `LOG_CHANNEL=stack`
- `LOG_STACK_CHANNEL=daily`
- `LOG_DAILY_DAYS=14`

Deployment modes:

- Preferred: document root points to `/home/account/bassir/public`, with `APP_URL=https://your-domain.com`.
- Fallback subfolder: project lives at `/home/account/public_html/rec`, accessed as `https://your-domain.com/rec/public`, with `APP_URL=https://your-domain.com/rec/public`.

For Cloudflare/reverse proxy:

- Set `TRUSTED_PROXIES=*` when your host supports it.
- Set `APP_TRUST_PROXY_HTTPS_HEADERS=true` only when SSL is terminated before PHP and redirect loops appear.

## 3) Run one-command cutover

First deployment (includes seeding demo/owner setup):

```bash
RUN_SEED=true ./scripts/target-cutover.sh
```

Existing production (skip seeding):

```bash
RUN_SEED=false ./scripts/target-cutover.sh
```

Optional overrides:

```bash
APP_URL_OVERRIDE=https://your-domain.com RUN_SEED=false ./scripts/target-cutover.sh
```

## 4) Verify output

- Script prints `[OK]` for each step.
- A full log is created under:

```bash
storage/logs/target-cutover-YYYYmmdd-HHMMSS.log
```

The cutover script also verifies:

- `/health` returns database/storage `ok`
- security headers (`CSP`, `X-Trace-Id`, `Cache-Control no-store`)
- mobile API auth gate behavior (`/api/mobile/auth/login` and `/api/mobile/dashboard/summary`)

## 5) Configure integrations (Item 2)

```bash
cd /home/account/bassir
./scripts/apply-integrations.sh
php scripts/integration-check.php
```

Optional connectivity test:

```bash
RUN_REMOTE_INTEGRATION_TESTS=true php scripts/integration-check.php
```

## 6) Enable operations (Item 3)

```bash
cd /home/account/bassir
./scripts/ops-backup.sh
./scripts/ops-health-monitor.sh
INSTALL_CRON=true ./scripts/ops-install-cron.sh
```

## 7) Run full smoke suite (Item 4)

```bash
cd /home/account/bassir
SMOKE_USERNAME=yahya \
SMOKE_PASSWORD='YourRealPassword' \
RUN_INTEGRATION_CHECK=true \
./scripts/smoke-test-suite.sh
```

## 8) Post-cutover manual checks

1. Login with owner user `yahya`, then force password change.
2. Open `/dashboard`, `/ai-search`, `/candidates`, `/jobs`, `/reports`.
3. Upload one CV and verify parse + private download.
4. Run one AI search and import one compliant result.
5. Export one CSV report.

## 9) HR/UAT sign-off (Item 5)

Complete:

- `docs/HR_UAT_SIGNOFF_AR.md`

Required evidence:

- cutover log
- smoke test log
- integration check output
- latest backup log

## 10) Rollback safety

If anything fails:

1. Stop and review the cutover log.
2. Restore DB backup and `storage/app/private` backup.
3. Re-run after fixing env/integration issues.
