# Production Operations Runbook

This runbook covers go-live items 2, 3, 4, and 5:

- Integrations setup
- Backup/monitoring operations
- Smoke testing
- HR/UAT sign-off

## 1) Configure Integrations (Item 2)

### Option A: From `.env` values

```bash
cd /home/account/bassir
./scripts/apply-integrations.sh
```

### Option B: From separate secure file

Create a file (example: `/home/account/secure-integration.env`) with:

```bash
OPENAI_API_KEY=...
GOOGLE_CUSTOM_SEARCH_API_KEY=...
GOOGLE_CUSTOM_SEARCH_ENGINE_ID=...
BING_SEARCH_API_KEY=...
SERPAPI_API_KEY=...
AGENCY_FEED_URL=...
AGENCY_FEED_TOKEN=...
OCR_SPACE_API_KEY=...
```

Then run:

```bash
cd /home/account/bassir
./scripts/apply-integrations.sh /home/account/secure-integration.env
```

### Validate integrations

```bash
cd /home/account/bassir
php scripts/integration-check.php
```

Optional remote connectivity checks:

```bash
RUN_REMOTE_INTEGRATION_TESTS=true php scripts/integration-check.php
```

## 2) Backups + Monitoring (Item 3)

### Manual backup run

```bash
cd /home/account/bassir
./scripts/ops-backup.sh
```

Artifacts are stored in:

- `storage/backups/<timestamp>/`
- `storage/logs/ops-backup-*.log`

### Manual health monitor run

```bash
cd /home/account/bassir
./scripts/ops-health-monitor.sh
```

Optional alert webhook:

```bash
ALERT_WEBHOOK_URL=https://your-webhook ./scripts/ops-health-monitor.sh
```

### Install cron jobs

Preview:

```bash
cd /home/account/bassir
./scripts/ops-install-cron.sh
```

Install:

```bash
cd /home/account/bassir
INSTALL_CRON=true ./scripts/ops-install-cron.sh
```

## 3) Smoke Testing (Item 4)

### Baseline smoke suite

```bash
cd /home/account/bassir
./scripts/smoke-test-suite.sh
```

### Authenticated smoke suite (recommended)

```bash
cd /home/account/bassir
SMOKE_USERNAME=yahya \
SMOKE_PASSWORD='YourRealPassword' \
RUN_INTEGRATION_CHECK=true \
./scripts/smoke-test-suite.sh
```

The smoke suite validates:

- `/health`
- security headers
- `GET /login`
- authenticated web login with CSRF/session cookies when credentials are provided
- dashboard, candidates, jobs, matching, interviews, reports, settings, and admin pages
- mobile auth gate behavior
- mobile authenticated endpoints (with credentials)
- optional integrations check

### Full server QA suite

```bash
cd /home/account/bassir
./scripts/qa-server-suite.sh
```

For a disposable staging database only:

```bash
QA_FRESH_DATABASE=true ./scripts/qa-server-suite.sh
```

## 4) HR/UAT Sign-off (Item 5)

After smoke tests pass, complete HR acceptance:

1. Open and fill: `docs/HR_UAT_SIGNOFF_AR.md`
2. Attach required logs:
   - `storage/logs/target-cutover-*.log`
   - `storage/logs/smoke-test-suite-*.log`
   - output of `php scripts/integration-check.php`
   - latest `storage/logs/ops-backup-*.log`
3. Collect signatures from HR manager, hiring manager, and technical owner.
4. Mark final decision (`Go Live` / conditional approval / rejected).

No production launch should be finalized without this sign-off.
