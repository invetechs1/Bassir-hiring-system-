# API Reference (Current Web/API Endpoints)

## Health

- `GET /health`  
  System health JSON (app/database/storage status).
- `GET /api/health`  
  API health JSON.

## Auth

- `POST /login`
- `POST /logout`
- `GET /locale/{locale}` (locale switch for `en` / `ar`)

## Candidates

- `GET /candidates`
- `GET /candidates/{candidate}`
- `GET /candidates/{candidate}/documents/{document}/download`
- `GET /api/candidates`

Write actions (role-protected):

- `GET /candidates/create`
- `POST /candidates`
- `POST /candidates/{candidate}/action`
- `POST /import-candidates`

## CV Upload

- `GET /upload-cv`
- `POST /upload-cv`

## Jobs & Matching

- `GET /jobs`
- `GET /jobs/{job}`
- `GET /api/jobs`
- `GET /jobs/create`
- `POST /jobs`
- `POST /jobs/{job}/match`
- `GET /matching`
- `GET /jobs/{job}/ranking`
- `POST /jobs/{job}/ranking/rebuild`
- `POST /jobs/{job}/ranking/{candidate}/decision`

Ranking result fields include:

- overall match
- skills/technical match
- experience match
- education match
- salary fit
- location fit
- notice period fit
- risk indicators
- missing requirements
- interview questions
- recruiter decision
- recruiter AI feedback

## Applications / Recruitment Pipeline

- `GET /applications`
- `POST /applications`
- `PATCH /applications/{application}/stage`

Pipeline stages:

- `APPLIED`
- `AI_REVIEWED`
- `SHORTLISTED`
- `PHONE_SCREENING`
- `INTERVIEW_SCHEDULED`
- `INTERVIEWED`
- `OFFER_SENT`
- `HIRED`
- `REJECTED`
- `WITHDRAWN`

## Candidate Matching and Acceleration

- `GET /candidates/{candidate}/job-matches`
- `POST /candidates/{candidate}/job-matches/rebuild`
- `GET /search-assistant`
- `GET /candidate-comparison`

## Talent Pools

- `GET /talent-pools`
- `POST /talent-pools`
- `POST /talent-pools/{pool}/candidates`
- `DELETE /talent-pools/{pool}/candidates/{candidate}`

## AI Search & Sourcing

- `GET /ai-search`
- `POST /ai-search/cv-sourcing`
- `POST /ai-search/import-result`
- `POST /ai-search/import-linkedin-manual`

## Interviews

- `GET /interviews`
- `GET /interviews/create`
- `POST /interviews`
- `POST /interviews/{interview}/feedback`

## Salary Benchmarks

- `GET /salary-benchmarks`
- `POST /salary-benchmarks`

## Reports (CSV Exports)

- `GET /reports`
- `GET /reports/candidates.csv`
- `GET /reports/sources.csv`
- `GET /reports/interviews.csv`
- `GET /reports/salary-benchmarks.csv`
- `GET /reports/ai-search-success.csv`

## Admin Modules

- `GET /integrations`
- `POST /integrations`
- `GET /users`
- `POST /users`
- `PUT /users/{user}`
- `GET /audit-logs`
- `GET /settings/profile`
- `POST /settings/password`
- `POST /settings/general`

## Notes

- API routes include a dedicated mobile token flow under `/api/mobile/*`.
- Most write endpoints are protected by per-route throttling for production abuse protection.
- Web and mobile APIs enforce role/permission checks and company scoping for tenant data.
- Integration keys and global system defaults are restricted to `SUPER_ADMIN`.

## Mobile API (Android/iOS)

Base path: `/api/mobile`

Authentication:

- `POST /api/mobile/auth/login`  
  Returns bearer token in format `token_id|secret`.
- `POST /api/mobile/auth/logout` (Bearer required)
- `POST /api/mobile/auth/logout-all` (Bearer required)
- `GET /api/mobile/auth/me` (Bearer required)

Dashboard:

- `GET /api/mobile/dashboard/summary` (Bearer required)

Candidates:

- `GET /api/mobile/candidates` (Bearer required)
- `GET /api/mobile/candidates/{candidate}` (Bearer required)
- `POST /api/mobile/candidates/{candidate}/status` (Bearer required)

Jobs:

- `GET /api/mobile/jobs` (Bearer required)
- `GET /api/mobile/jobs/{job}` (Bearer required)
- `POST /api/mobile/jobs/{job}/match` (Bearer required, manager roles)
