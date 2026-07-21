# Developer Handoff

This is the Laravel/PHP shared-hosting redesign of Bassir AI Recruitment System.

## Main Modules

- Auth: `AuthController`
- Dashboard: `DashboardController`
- Candidates: `CandidateController`
- Jobs and matching: `JobController`
- Matching dashboard page: `MatchingController`
- Specializations management: `SpecializationController`
- AI CV sourcing: `AiSearchController`
- CV parsing/upload: `CvUploadController`
- Reports: `ReportController`
- Interviews: `InterviewController`
- Salary benchmarks: `SalaryBenchmarkController`
- Integrations/API keys: `IntegrationController`
- Settings/Profile: `SettingsController`
- User management: `UserManagementController`
- Audit logs: `AuditLogController`
- Locale switch (EN/AR session): `LocalizationController`
- Health: `HealthController`

## Services

- `CandidateScoringService`
- `SalaryEstimatorService`
- `SearchProviderService`
- `CvParserService`
- `DuplicateDetectionService`
- `AuditService`
- `ApiCredentialService`
- `AiInsightsService`
- `OcrService`

## Operational Artifacts

- Server preflight script: `scripts/preflight.php`
- Integrations apply script: `scripts/apply-integrations.sh`
- Integrations validation script: `scripts/integration-check.php`
- Backup script: `scripts/ops-backup.sh`
- Health monitor script: `scripts/ops-health-monitor.sh`
- Cron installer script: `scripts/ops-install-cron.sh`
- Smoke suite: `scripts/smoke-test-suite.sh`
- Final go-live checklist: `docs/FINAL_QA_CHECKLIST.md`
- Production operations runbook: `docs/PRODUCTION_OPERATIONS_RUNBOOK.md`
- HR sign-off template: `docs/HR_UAT_SIGNOFF_AR.md`
- Endpoint reference: `docs/API_REFERENCE.md`
- Arabic programmer upload handoff: `docs/PROGRAMMER_UPLOAD_HANDOFF_AR.md`

## Next Development Steps

- Add rich DataTables sorting/filtering if desired.
- Specializations are managed from `/specializations` and seeded with engineering, technology, finance, HR, and operations categories.
- Interviews are managed from `/interviews`.
- Salary benchmarks and estimator are managed from `/salary-benchmarks`.
- Encrypted integration keys are managed from `/integrations`.
- User access and role controls are managed from `/users`.
- Audit trail is available at `/audit-logs`.
- Password update and system defaults are managed from `/settings/profile`.
- AI sourcing result import and manual LinkedIn import are managed from `/ai-search`.
- Add queue workers for high-volume CV parsing.
- Add antivirus scanning provider.
- Add email/WhatsApp notification providers.
- Add SaaS tenant isolation before multi-company launch.
- Add Arabic translations in `lang/ar`.
