# Test Report — HomeCare Egypt

**Environment:** PHP 8.2.12 · Laravel 12.70.1 · MariaDB 10.4.32 (XAMPP, `127.0.0.1:3306`)
**Test database:** `nu_test` (isolated) · **Application DB:** `nu` (protected, additive only)
**Command:** `php artisan test`

## Run 2026-10-25 — Phase 0/1

**Result: 18 passed (58 assertions), 0 failed**

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

### Notes / honest gaps

- No test is currently SKIPPED. Nothing is marked PASS without an executed assertion.
- Concurrency (T-023), dispatch TTL (T-022), GPS-only arrival (T-030), ledger
  idempotency (T-045) and SOS delivery (T-057…) are **not yet written** — the schema
  exists but the service layer is the next phase. They are deliberately absent rather
  than faked.
- The 18 above are unit/feature tests on synthetic data. External integrations
  (SMS, maps, payment gateway) are **NOT LIVE VERIFIED** and use safe adapters.

### Engine-compatibility findings (proven during migration, not assumed)

| Finding | Evidence | Resolution |
|---|---|---|
| MariaDB 10.4 rejects `TIMESTAMP NOT NULL` without an explicit default (`1067`) | migration `160800` failed | app-managed timestamps made nullable / `useCurrent()` |
| Index names > 64 chars are rejected (`1059`) | `hc_scheduling_reservations_…_index` | explicit short index names |
| DDL is non-transactional in MariaDB | partial tables after a failed migration | migrations re-run from a cleaned, verified-empty state |
| `ST_Distance_Sphere` works | Cairo→Giza probe returned 4884.55 m | used behind the `GeoDistance` contract (ADR-002) |
| Generated-column UNIQUE works | `hc_booking_holds.active_key` created | basis of the single-winner guarantee (T-023) |