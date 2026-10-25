# Test Report — HomeCare Egypt

**Environment:** PHP 8.2.12 · Laravel 12.70.1 · MariaDB 10.4.32 (XAMPP, `127.0.0.1:3306`)
**Test database:** `nu_test` (isolated) · **Application DB:** `nu` (protected, additive only)
**Command:** `php artisan test`

## Run 2026-10-25 — Phase 0/1

**Result: 18 passed (58 assertions), 0 failed** — initial identity/patient slice.

| Test ID | Suite | What it proves | Result |
|---|---|---|---|
| T-002 | AuthOtpTest | OTP code stored hashed, returned only in local dev | PASS |
| T-002 | AuthOtpTest | Correct code authenticates; replay of the same code fails | PASS |
| T-002 | AuthOtpTest | Incorrect code never authenticates, no user created | PASS |
| T-002 | AuthOtpTest | Expired code rejected | PASS |
| T-002 | AuthOtpTest | Attempt cap exhausts and kills the code | PASS |
| — | AuthOtpTest | `/auth/me` requires a token; valid token returns the caller | PASS |
| T-003 | PatientAccessTest | Owner can create + read their own patient | PASS |
| T-003 | PatientAccessTest | Another account cannot read a patient even with the real id (403) | PASS |
| T-003 | PatientAccessTest | Patient never appears in another account's listing | PASS |
| T-003 | PatientAccessTest | Guest cannot list patients (401) | PASS |
| T-004 | PatientAccessTest | `book` scope grants neither summary nor medical access | PASS |
| T-004 | PatientAccessTest | `view_visit_status` shows summary but hides clinical block | PASS |
| T-004 | PatientAccessTest | `view_medical` reveals the clinical block | PASS |
| T-004 | PatientAccessTest | Revocation blocks access immediately | PASS |
| — | PatientAccessTest | Delegation to an unregistered phone is refused (422) | PASS |
| — | PatientAccessTest | Creator account link is verified immediately | PASS |
| — | HealthTest | `/health` reports the real DB and it is `nu_test` | PASS |
| — | HealthTest | `/ping` requires authentication (401) | PASS |
| — | AuditChainTest | Audit rows form a verifiable hash chain | PASS |
| — | AuditChainTest | Tampering is detected by chain verification | PASS |

## Run 2026-10-25 — Phase 2/3/4 service layer

**Result (cumulative): 49 passed (173 assertions), 0 failed**

| Test ID | Suite | What it proves | Result |
|---|---|---|---|
| T-011 | ProviderEligibilityTest | No valid licence → never eligible | PASS |
| T-011 | ProviderEligibilityTest | **Expired** licence blocks eligibility even when approved | PASS |
| — | ProviderEligibilityTest | Unverified licence does not count as valid | PASS |
| T-012 | ProviderEligibilityTest | Approved for service A is not eligible for service B | PASS |
| — | ProviderEligibilityTest | Suspended profile is not eligible even with a valid licence | PASS |
| — | ProviderEligibilityTest | Unpublished service is not bookable | PASS |
| T-023 | DispatchTest | **5 concurrent acceptors → exactly ONE winner and one active hold** | PASS |
| T-023 | DispatchTest | Provisional accept does NOT confirm; `confirmed_at` stays null | PASS |
| T-022 | DispatchTest | Expired offer cannot be accepted | PASS |
| T-024 | DispatchTest | Customer confirmation is a separate step; expired hold releases safely | PASS |
| — | DispatchTest | Confirmation inside the window confirms the booking | PASS |
| — | DispatchTest | A provider suspended after the offer cannot accept it | PASS |
| T-030 | VisitSessionTest | **GPS inside the geofence with no customer confirmation → zero billing** | PASS |
| T-031 | VisitSessionTest | Provider claim + customer confirm → exactly one session start (idempotent) | PASS |
| — | VisitSessionTest | Customer cannot confirm before the provider claims arrival | PASS |
| T-034 | VisitSessionTest | 12 min → 30 min minimum; 47 min → exactly 47 | PASS |
| — | VisitSessionTest | Completion records billed minutes from server time | PASS |
| — | VisitSessionTest | Human exception path is recorded distinctly from GPS | PASS |
| T-048 | FinanceIntegrityTest | Unbalanced journal is rejected | PASS |
| T-048 | FinanceIntegrityTest | Balanced journal posts and verifies | PASS |
| T-045 | FinanceIntegrityTest | **Webhook replayed 10× posts exactly ONE settlement** | PASS |
| T-046 | FinanceIntegrityTest | Invalid signature never settles | PASS |
| T-047 | FinanceIntegrityTest | Cash disagreement → dispute, never settlement | PASS |
| — | FinanceIntegrityTest | Cash settles only on dual attestation; commission receivable recorded | PASS |
| T-059 | SosTest | ACK and RESOLVE are separate events | PASS |
| T-057 | SosTest | Both reporter roles can raise an incident | PASS |
| T-058 | SosTest | Log-only adapter never reports "delivered" | PASS |
| T-059 | SosTest | Unacknowledged incident escalates automatically past the SLA | PASS |
| T-060 | SosTest | Senior closure without the reporter requires a documented reason | PASS |
| — | SosTest | Concurrent incidents are independent | PASS |

### Notes / honest gaps

- No test is SKIPPED. Nothing is marked PASS without an executed assertion.
- Still **not written** (schema exists, service layer pending): T-014 catalog publish
  routing, T-015 red-flag screen endpoint, T-025 travel-gap conflict, T-036 overrun cap,
  T-041 12-hour shift cap, T-043 subscription consumption, T-061 live-location channel
  authz, T-068 malware scan, T-069 rota fail-closed. Deliberately absent rather than faked.
- All tests run on **synthetic** data against the isolated `nu_test` database.
- External integrations (SMS, maps, payment gateway) are **NOT LIVE VERIFIED**; they sit
  behind adapters (`SmsSender`, `GeoDistance`) that report honest status.

### Engine-compatibility findings (proven during migration, not assumed)

| Finding | Evidence | Resolution |
|---|---|---|
| MariaDB 10.4 rejects `TIMESTAMP NOT NULL` without an explicit default (`1067`) | migration `160800` failed | app-managed timestamps made nullable / `useCurrent()` |
| Index names > 64 chars are rejected (`1059`) | `hc_scheduling_reservations_…_index` | explicit short index names |
| DDL is non-transactional in MariaDB | partial tables after a failed migration | migrations re-run from a cleaned, verified-empty state |
| `ST_Distance_Sphere` works | Cairo→Giza probe returned 4884.55 m | used behind the `GeoDistance` contract (ADR-002) |
| Generated-column UNIQUE works | `hc_booking_holds.active_key` created | basis of the single-winner guarantee (T-023) |