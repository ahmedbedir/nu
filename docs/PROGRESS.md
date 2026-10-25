# Progress — HomeCare Egypt

Legend: `[x]` verified by executed code/tests · `[~]` implemented, not yet verified · `[ ]` not started

Last updated: 2026-10-25

## Phase 0 — Preflight & safeguards  ✅

- [x] Read all 8 handoff documents and summarised binding constraints.
- [x] Inspected workspace + PHP/Composer/DB versions **read-only**.
- [x] Confirmed `nu` exists, charset `utf8mb4`, **empty** (no tables to preserve).
- [x] Backed up `nu` to `backups/nu_baseline_*.sql` before any mutation.
- [x] Git repository initialised at workspace root.
- [x] Laravel scaffolded in `apps/api` (Laravel 12 on PHP 8.2 → ADR-001).
- [x] `hc_` table prefix enforced (ADR-003); existing scaffold tables renamed, not dropped.
- [x] Isolated test database `nu_test` created; `phpunit.xml` points at it.
- [x] `DestructiveCommandGuard` blocks destructive commands against `nu`.
- [x] `GET /api/v1/health` performs a real DB probe (no fabricated status).

## Phase 1 — Identity, security, patients  ✅ (core slice)

- [x] Phone OTP sign-in (hashed codes, TTL, attempt cap, rate limiting, no enumeration).
- [x] Sanctum multi-device tokens; logout revokes the current device token.
- [x] RBAC: 12 roles, 27 permissions, 63 grants synced idempotently.
- [x] Patient records: demographics, explicit **UNKNOWN** clinical states.
- [x] Patient ↔ account links (creator verified) and scoped, revocable delegations.
- [x] `PatientAccess` + `PatientPolicy` enforce scope exactly (`book` ≠ `view_medical`).
- [x] Append-only `hc_audit_events` with a verifiable hash chain.
- [x] Tests: OTP (T-002), patient IDOR (T-003), delegation scope + revoke (T-004), audit chain.
- [~] KYC document upload (schema present; endpoints pending).
- [~] Identity verification review workflow (schema present; endpoints pending).

## Phase 2 — Catalog, matching, booking  🟡 schema complete

- [x] Schema for service catalog, dispatch, holds, quotes, reservations.
- [x] Schema for visits, check-ins, clinical notes, SOS.
- [x] Schema for financials (pricing, invoices, ledger, payments, refunds, disputes).
- [x] Schema for recurrence, shifts, subscriptions, support, admin approvals.
- [ ] Provider onboarding endpoints + credential review workflow (T-011/T-012).
- [ ] Service catalog publish/approval endpoints.
- [ ] Eligibility engine wiring + deterministic gate transitions (T-015).
- [ ] Dispatch waves, 30s offer TTL, atomic single-winner award (T-022/T-023).
- [ ] Quote + separate customer confirmation (T-024).

## Next

Phase 3 (live visit + SOS), Phase 4 (money), Phase 5 (recurrence/plans),
Phase 6 (ops), Phase 7 (hardening + sign-offs). Front-ends (Next.js, Vue, Flutter)
follow the API slices.