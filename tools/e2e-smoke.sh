#!/usr/bin/env bash
# End-to-end API smoke test over real HTTP against the running Laravel server.
# Synthetic data only. Prints PASS/FAIL for each step and exits non-zero on failure.
set -u
BASE="${1:-http://127.0.0.1:8000/api/v1}"
PHONE="01098765432"
fail=0

step() { printf '%-46s' "$1"; }
ok()   { echo "PASS"; }
bad()  { echo "FAIL ($1)"; fail=1; }

echo "== HomeCare Egypt API end-to-end smoke test =="
echo "base: $BASE"
echo

step "health endpoint"
H=$(curl -s "$BASE/health")
echo "$H" | grep -q '"status":"ok"' && ok || bad "$H"

step "public services catalog"
S=$(curl -s "$BASE/services")
echo "$S" | grep -q '"data"' && ok || bad "$S"

step "protected route without token"
C=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/patients")
[ "$C" = "401" ] && ok || bad "got $C"

step "OTP request"
R=$(curl -s -X POST "$BASE/auth/otp/request" -H 'Content-Type: application/json' \
      -d "{\"phone\":\"$PHONE\"}")
CODE=$(echo "$R" | sed -n 's/.*"dev_code":"\([0-9]*\)".*/\1/p')
[ -n "$CODE" ] && ok || bad "$R"
echo "     dev_code=$CODE"

step "OTP verify"
V=$(curl -s -X POST "$BASE/auth/otp/verify" -H 'Content-Type: application/json' \
      -d "{\"phone\":\"$PHONE\",\"code\":\"$CODE\"}")
TOKEN=$(echo "$V" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
[ -n "$TOKEN" ] && ok || bad "$V"

step "wrong OTP code rejected"
W=$(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/auth/otp/verify" \
      -H 'Content-Type: application/json' -d "{\"phone\":\"$PHONE\",\"code\":\"000001\"}")
[ "$W" = "422" ] && ok || bad "got $W"

step "authenticated /auth/me"
M=$(curl -s "$BASE/auth/me" -H "Authorization: Bearer $TOKEN")
echo "$M" | grep -q '"public_id"' && ok || bad "$M"

step "create patient"
P=$(curl -s -X POST "$BASE/patients" -H "Authorization: Bearer $TOKEN" \
      -H 'Content-Type: application/json' \
      -d '{"full_name":"أم كريم","sex":"female","relationship":"self"}')
PID=$(echo "$P" | sed -n 's/.*"public_id":"\([^"]*\)".*/\1/p')
[ -n "$PID" ] && ok || bad "$P"

step "list own patients"
L=$(curl -s "$BASE/patients" -H "Authorization: Bearer $TOKEN")
echo "$L" | grep -q "$PID" && ok || bad "$L"

step "read own patient"
G=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/patients/$PID" -H "Authorization: Bearer $TOKEN")
[ "$G" = "200" ] && ok || bad "got $G"

step "guest cannot read patient"
N=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/patients/$PID")
[ "$N" = "401" ] && ok || bad "got $N"

step "second account cannot read patient (IDOR)"
PHONE2="01098765433"
R2=$(curl -s -X POST "$BASE/auth/otp/request" -H 'Content-Type: application/json' -d "{\"phone\":\"$PHONE2\"}")
C2=$(echo "$R2" | sed -n 's/.*"dev_code":"\([0-9]*\)".*/\1/p')
V2=$(curl -s -X POST "$BASE/auth/otp/verify" -H 'Content-Type: application/json' -d "{\"phone\":\"$PHONE2\",\"code\":\"$C2\"}")
T2=$(echo "$V2" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
I=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/patients/$PID" -H "Authorization: Bearer $T2")
[ "$I" = "403" ] && ok || bad "got $I"

echo
if [ "$fail" -eq 0 ]; then
  echo "ALL STEPS PASSED"
else
  echo "SOME STEPS FAILED"
fi
exit $fail