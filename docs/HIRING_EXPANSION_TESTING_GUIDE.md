# Testing Guide: Candidate Portal, Communications, Assessments, Offers/Onboarding, Bias Monitoring

This covers the five features added on top of the existing hiring system: the
candidate self-service portal, automated email notifications, assessments,
offer & onboarding, and bias monitoring.

## 0. Prerequisites

Follow the "Run Locally (Development)" section of the main `README.md` first
to get the app running at `http://localhost:8000` with the seeded demo data.

For this testing session, set mail to log locally so you can read outgoing
emails without real SMTP credentials:

```env
MAIL_MAILER=log
```

Then `php artisan config:clear` and restart `php artisan serve`. Every "email
sent" step below can be verified by tailing the log instead of an inbox:

```bash
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log
```

(On the production server, this is `MAIL_MAILER=smtp` with real credentials —
see the "Automated Communications" section below for what to configure.)

## 1. Automated test suite (fastest check)

```bash
php artisan test --filter=HiringSystemExpansionTest
```

This runs 8 tests covering every flow below end-to-end (registration →
apply → notifications → assessment auto-scoring → offer approve/send/accept
→ onboarding task creation → bias-monitoring suppression). Run the full
suite (`php artisan test`) to confirm nothing else regressed — 54 tests
should pass.

## 2. Candidate self-service portal

The portal is scoped per company via its slug. The seeded demo company's
slug is `bassir-demo`.

1. Visit `http://localhost:8000/careers/bassir-demo` — you should see the
   seeded "Senior BIM Engineer" job listed (only `APPROVED` jobs show here).
2. Click into the job, then **Register** using any email/password (min 8
   characters).
3. You're logged in automatically after registering and redirected to
   **My Applications** (`/portal/dashboard`), currently empty.
4. Go back to the job page and click **Submit Application** (CV upload is
   optional).
5. Confirm it now appears on `/portal/dashboard` with stage **Applied**,
   and clicking into it shows a timeline.
6. Log out (top-right), then log back in at
   `/careers/bassir-demo/login` with the same credentials to confirm login
   works independently of registration.

**What to check for correctness:**
- A candidate registered under one company can't see or apply to another
  company's jobs (there's currently only one seeded company, so this can't
  be directly demonstrated without creating a second `Company` row).
- Recruiter side: log in as staff (`yahya` / your changed password) and
  visit `/applications` — the portal-submitted application should appear in
  the **Applied** column with source "Candidate Portal".

## 3. Automated communications

Every trigger below writes a row to the `communications` table (visible via
a candidate's profile page as staff, or `SELECT * FROM communications`) and
attempts an actual send. With `MAIL_MAILER=log`, "attempts" means it's
written to the log file instead of going out over SMTP.

| Trigger | How to fire it | What to check |
|---|---|---|
| Application received | Candidate applies via the portal (§2 step 4) | Log shows `Subject: Application received: <job>` |
| Interview invite | Staff: Interviews → New Interview, pick the candidate | Log shows `Subject: Interview invitation for <job>` |
| Assessment assigned | Staff: Assessments → assign to an application (§4) | Log shows `Subject: Next step: <assessment title>` |
| Offer sent | Staff sends an approved offer (§5) | Log shows `Subject: Job offer: <job>` |
| Rejection | Staff changes an application's stage to **Rejected** | Log shows `Subject: Update on your application: <job>` |

A send failure (e.g. broken SMTP credentials) never blocks the underlying
action — the pipeline stage change, interview creation, etc. always
succeeds; only the email attempt can fail, and it's logged as
`Communication.status = FAILED` with a warning in the log instead of an
exception.

**Production setup**: to make these real emails instead of log entries, set
real `MAIL_MAILER=smtp` + `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` /
`MAIL_PASSWORD` in the server's `.env` (see the earlier conversation's
"What's needed from your side" list — you'll need an SMTP provider like
SendGrid, Mailgun, or Gmail SMTP).

## 4. Assessments

1. Log in as staff, go to **Assessments** → **New Assessment**.
2. Create a `TEST` type assessment with one MCQ question (options
   comma-separated, correct answer typed exactly as one of the options) —
   or a `DOCUMENT_VERIFICATION` / `BACKGROUND_CHECK` type, which skips
   questions entirely and instead expects a candidate document upload.
3. Back on the Assessments list, use the **Assign** form — enter the
   candidate application's ID (visible in the URL when viewing an
   application, or query `SELECT id FROM candidate_applications`).
4. As the candidate (log in to the portal), go to **Assessments** →
   **Start**, answer the question(s) or upload the document, and submit.
5. As staff, open the assessment's results page
   (`/assessments/results/{id}`, linked from the assign confirmation or
   found via the candidate's application) — MCQ answers are auto-scored
   immediately (status `SCORED`); free-text or document types land in
   `SUBMITTED` awaiting manual review via the **Review** form on that page.

## 5. Offers & Onboarding

1. As staff, on `/applications`, find a candidate's card and click
   **Draft Offer** (only visible if your role has offer permissions — HR
   Manager, Recruiter, Hiring Manager, or admins).
2. Fill in salary/start date/terms and save — status starts at
   `PENDING_APPROVAL`.
3. On `/offers`, click **Approve** (SUPER_ADMIN / COMPANY_ADMIN /
   HR_MANAGER only), then **Send to Candidate**. The application's pipeline
   stage automatically moves to **Offer Sent**.
4. As the candidate, open `/portal/offers/{id}` (linked from their
   application detail page) and click **Accept Offer**.
5. Confirm:
   - The application stage becomes **Hired**.
   - As staff, `/onboarding` now shows this candidate with 4 default
     checklist tasks (upload signed offer, upload ID, acknowledge
     policies, payroll form).
   - Mark a task **Complete** and confirm its status updates; once every
     task is complete the onboarding record itself flips to `COMPLETED`.
6. To test decline instead: draft/approve/send a second offer and click
   **Decline** on the candidate side — application stage should become
   **Withdrawn**, no onboarding record created.

## 6. Bias monitoring

1. As staff logged in as `yahya` (SUPER_ADMIN) or a COMPANY_ADMIN, visit
   `/bias-monitoring`.
2. With only a handful of seeded/test candidates, every nationality group
   is smaller than the 5-candidate suppression threshold, so you'll see a
   single **"Other (small groups)"** row — this is the intended
   privacy-preserving behavior, not a bug. To see a real per-nationality
   breakdown, you'd need ≥5 applications sharing the same nationality value
   in the pipeline.
3. Confirm the page is **not** visible to a RECRUITER or HR_MANAGER login —
   it's gated to admins only (`role:SUPER_ADMIN,COMPANY_ADMIN` +
   `permission:bias_monitoring.view`).

## Known v1 limitations (by design, not bugs)

- **Offer letters are not PDFs** — no PDF library was added (to avoid
  another PHP-version/Composer conflict like the one hit earlier this
  session). The offer is a formatted email + portal page; "download as
  PDF" today means browser print-to-PDF.
- **SMS is not implemented** — only email. Adding SMS requires picking a
  provider (Twilio, Unifonic, etc.) and their API credentials, which
  weren't available.
- **No real e-signature or background-check vendor integration** — offer
  acceptance is an in-app click, and background checks are manual
  status/notes entered by staff, not a live vendor API call.
- **Candidate accounts are scoped to one company** — a candidate who wants
  to apply to two different companies on this platform needs two separate
  portal accounts (matching the existing per-tenant candidate data
  isolation design).
- **Bias monitoring uses nationality only** — the only demographic field
  already collected by this system. No new sensitive data collection was
  added; treat the output as a monitoring aid for HR/legal review, not a
  compliance certification.
