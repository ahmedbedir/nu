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

## Phase 2 — Catalog, matching, booking  ✅ (core engine)

- [x] Schema for service catalog, dispatch, holds, quotes, reservations.
- [x] Schema for visits, check-ins, clinical notes, SOS.
- [x] Schema for financials (pricing, invoices, ledger, payments, refunds, disputes).
- [x] Schema for recurrence, shifts, subscriptions, support, admin approvals.
- [x] **Provider eligibility engine** — deterministic; invalid/expired/unverified
      credential → zero offers, regardless of rating (T-011/T-012).
- [x] **Dispatch with atomic single winner** — `FOR UPDATE` + unique active-hold column
      proves exactly one winner among 5 concurrent acceptors (T-023/T-024).
- [x] **Booking state machine** with server-side rejection of invalid transitions.
- [x] **Visit session** — GPS-only arrival yields zero billing; only verified two-sided
      check-in starts the timer (T-030/T-031); 30-minute minimum then per-minute (T-034).
- [x] **Double-entry ledger** — balanced journals enforced; unbalanced posts rejected (T-048).
- [x] **Idempotent payment webhook** — replayed 10× settles once; invalid signature never
      settles; cash disagreement → dispute (T-045/T-046/T-047).
- [x] **SOS** — durable intake, honest delivery status, separate ACK/RESOLVE, auto-escalation,
      documented senior closure (T-057…T-060).
- [~] Service catalog publish/approval HTTP endpoints (engine + schema ready).
- [~] Red-flag screening endpoint (rules column exists; engine pending).

## Phase 3 — Live visit + SOS  ✅ (core)

- [x] Departure ETA, departure, arrival claim, customer confirmation, completion.
- [x] Append-only `visit_time_events`; human-exception path recorded distinctly from GPS.
- [x] SOS intake, delivery attempts, acknowledgements, escalations, closure rules.
- [ ] Live trip map transport + authenticated WebSocket channels (T-061).
- [ ] Offline queue on the mobile client (T-058 device side).

## Phase 4 — Money  ✅ (core)

- [x] Ledger accounts + balanced journal posting + whole-ledger balance check.
- [x] Electronic settlement via verified webhook only; idempotent by gateway event id.
- [x] Cash dual attestation; commission recorded as a provider receivable.
- [ ] Pricing rule evaluation (min 30 min / night / holiday / travel) HTTP layer.
- [ ] Refund + dispute workflow endpoints with manager approval (T-055).

## Next

Catalog/pricing HTTP endpoints and provider-onboarding endpoints; then the front-ends
(Next.js customer, Vue admin operations room, Flutter app), then Phase 5–7.