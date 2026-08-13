#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

[[ -f .env ]] || { echo "[FAIL] .env not found"; exit 1; }
command -v curl >/dev/null 2>&1 || { echo "[FAIL] curl command is required"; exit 1; }

env_value() {
  local key="$1"
  local line
  line="$(grep -E "^${key}=" .env 2>/dev/null | tail -n 1 || true)"
  line="${line#*=}"
  line="${line%\"}"
  line="${line#\"}"
  line="${line%\'}"
  line="${line#\'}"
  printf '%s' "$line"
}

APP_URL="${APP_URL_OVERRIDE:-$(env_value APP_URL)}"
APP_URL="${APP_URL%/}"
[[ -n "$APP_URL" ]] || { echo "[FAIL] APP_URL is empty"; exit 1; }

SMOKE_USERNAME="${SMOKE_USERNAME:-}"
SMOKE_PASSWORD="${SMOKE_PASSWORD:-}"
SMOKE_DEVICE_NAME="${SMOKE_DEVICE_NAME:-SmokeTestDevice}"

LOG_DIR="$ROOT_DIR/storage/logs"
mkdir -p "$LOG_DIR"
TS="$(date +%Y%m%d-%H%M%S)"
LOG_FILE="$LOG_DIR/smoke-test-suite-$TS.log"
exec > >(tee -a "$LOG_FILE") 2>&1

pass=0
fail=0

ok() {
  pass=$((pass + 1))
  echo "[OK] $*"
}

bad() {
  fail=$((fail + 1))
  echo "[FAIL] $*"
}

expect_code() {
  local label="$1"
  local expected="$2"
  local url="$3"
  local code
  code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "$url" || true)"
  [[ "$code" == "$expected" ]] && ok "$label" || bad "$label expected $expected got $code"
}

expect_authenticated_page() {
  local label="$1"
  local path="$2"
  local code
  code="$(curl -sS -L -b "$WEB_COOKIE_JAR" -o /dev/null -w '%{http_code}' --max-time 30 "$APP_URL$path" || true)"
  case "$code" in
    200|302) ok "$label ($path) reachable" ;;
    *) bad "$label ($path) failed (HTTP $code)" ;;
  esac
}

echo "Smoke test suite started: $TS"
echo "Target URL: $APP_URL"
echo "----------------------------------------"

expect_code "GET /login route works" "200" "$APP_URL/login"

health="$(curl -fsS --max-time 20 "$APP_URL/health" || true)"
if [[ "$health" == *'"database":"ok"'* && "$health" == *'"storage":"ok"'* ]]; then
  ok "/health is healthy"
else
  bad "/health failed: $health"
fi

headers_file="$(mktemp)"
if curl -fsS -D "$headers_file" -o /dev/null --max-time 20 "$APP_URL/"; then
  grep -qi '^content-security-policy:' "$headers_file" && ok "CSP header present" || bad "CSP header missing"
  grep -qi '^x-trace-id:' "$headers_file" && ok "X-Trace-Id header present" || bad "X-Trace-Id header missing"
  grep -qi '^cache-control:.*no-store' "$headers_file" && ok "no-store cache-control present" || bad "no-store cache-control missing"
else
  bad "Homepage request failed"
fi
rm -f "$headers_file"

mobile_unauth_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "$APP_URL/api/mobile/dashboard/summary" || true)"
[[ "$mobile_unauth_code" == "401" ]] && ok "Mobile unauthorized guard works" || bad "Mobile unauthorized guard expected 401 got $mobile_unauth_code"

if [[ -n "$SMOKE_USERNAME" && -n "$SMOKE_PASSWORD" ]]; then
  WEB_COOKIE_JAR="$(mktemp)"
  WEB_LOGIN_HTML="$(mktemp)"
  web_login_code="$(curl -sS -L -c "$WEB_COOKIE_JAR" -o "$WEB_LOGIN_HTML" -w '%{http_code}' --max-time 30 "$APP_URL/login" || true)"
  csrf_token="$(sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' "$WEB_LOGIN_HTML" | head -n 1)"
  if [[ "$web_login_code" == "200" && -n "$csrf_token" ]]; then
    web_post_code="$(curl -sS -L -b "$WEB_COOKIE_JAR" -c "$WEB_COOKIE_JAR" -o /dev/null -w '%{http_code}' --max-time 30 \
      -X POST "$APP_URL/login" \
      -H 'Content-Type: application/x-www-form-urlencoded' \
      --data-urlencode "_token=$csrf_token" \
      --data-urlencode "username=$SMOKE_USERNAME" \
      --data-urlencode "password=$SMOKE_PASSWORD" || true)"
    case "$web_post_code" in
      200|302) ok "Web login submitted successfully" ;;
      *) bad "Web login failed (HTTP $web_post_code)" ;;
    esac

    expect_authenticated_page "Dashboard" "/dashboard"
    expect_authenticated_page "Candidates" "/candidates"
    expect_authenticated_page "Candidate create" "/candidates/create"
    expect_authenticated_page "Jobs" "/jobs"
    expect_authenticated_page "Job create" "/jobs/create"
    expect_authenticated_page "AI Matching" "/matching"
    expect_authenticated_page "Interviews" "/interviews"
    expect_authenticated_page "Interview create" "/interviews/create"
    expect_authenticated_page "Reports" "/reports"
    expect_authenticated_page "Settings profile" "/settings/profile"
    expect_authenticated_page "AI Search" "/ai-search"
    expect_authenticated_page "Specializations" "/specializations"
    expect_authenticated_page "Integrations" "/integrations"
    expect_authenticated_page "Users" "/users"
    expect_authenticated_page "Audit logs" "/audit-logs"
  else
    bad "Could not load web login form or CSRF token (HTTP $web_login_code)"
  fi
  rm -f "$WEB_COOKIE_JAR" "$WEB_LOGIN_HTML"

  login_payload="$(printf '{"username":"%s","password":"%s","device_name":"%s"}' "$SMOKE_USERNAME" "$SMOKE_PASSWORD" "$SMOKE_DEVICE_NAME")"
  login_response="$(curl -sS --max-time 20 -X POST "$APP_URL/api/mobile/auth/login" -H 'Content-Type: application/json' -d "$login_payload" || true)"
  token="$(echo "$login_response" | sed -n 's/.*"access_token":"\([^"]*\)".*/\1/p')"

  if [[ -n "$token" ]]; then
    ok "Mobile login succeeded"
    me_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 -H "Authorization: Bearer $token" "$APP_URL/api/mobile/auth/me" || true)"
    [[ "$me_code" == "200" ]] && ok "Mobile /auth/me succeeded" || bad "Mobile /auth/me failed (HTTP $me_code)"

    dash_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 -H "Authorization: Bearer $token" "$APP_URL/api/mobile/dashboard/summary" || true)"
    [[ "$dash_code" == "200" ]] && ok "Mobile dashboard succeeded" || bad "Mobile dashboard failed (HTTP $dash_code)"

    candidates_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 -H "Authorization: Bearer $token" "$APP_URL/api/mobile/candidates?per_page=10" || true)"
    [[ "$candidates_code" == "200" ]] && ok "Mobile candidates endpoint succeeded" || bad "Mobile candidates endpoint failed (HTTP $candidates_code)"

    jobs_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 -H "Authorization: Bearer $token" "$APP_URL/api/mobile/jobs?per_page=10" || true)"
    [[ "$jobs_code" == "200" ]] && ok "Mobile jobs endpoint succeeded" || bad "Mobile jobs endpoint failed (HTTP $jobs_code)"

    logout_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 -X POST -H "Authorization: Bearer $token" "$APP_URL/api/mobile/auth/logout" || true)"
    [[ "$logout_code" == "200" ]] && ok "Mobile logout succeeded" || bad "Mobile logout failed (HTTP $logout_code)"
  else
    bad "Mobile login failed: $login_response"
  fi
else
  echo "[INFO] Skipping authenticated mobile tests (set SMOKE_USERNAME and SMOKE_PASSWORD)"
fi

if [[ "${RUN_INTEGRATION_CHECK:-false}" == "true" ]]; then
  if command -v php >/dev/null 2>&1; then
    if php scripts/integration-check.php; then
      ok "Integration presence check passed"
    else
      bad "Integration presence check failed"
    fi
  else
    bad "php command not found, integration check skipped"
  fi
fi

echo "----------------------------------------"
echo "Passed: $pass"
echo "Failed: $fail"
echo "Log file: $LOG_FILE"
exit $([[ $fail -eq 0 ]] && echo 0 || echo 1)
