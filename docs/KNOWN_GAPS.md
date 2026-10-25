# Known gaps — current, honest state

Everything below is a real limitation of the current build, not a to-do wish list.
Nothing here is presented to users as working.

## Environment blockers

| Gap | Impact | Workaround / next step |
|---|---|---|
| **Flutter SDK not installed** | The shared customer/professional mobile app cannot be built or run here | API contracts, design tokens and event catalogue are ready for it (`packages/contracts`). Install Flutter, then scaffold `apps/mobile` against `/api/v1`. Not faked with a placeholder app. |
| PHP 8.2 instead of 8.3+ | Laravel 12 locally, not 13 | ADR-001. Upgrade XAMPP PHP or install 8.3, then bump `laravel/framework` |
| MariaDB 10.4, not MySQL 8.4 | Some DDL differences (`TIMESTAMP` defaults, identifier length) | ADR-002/ADR-005. All SQL is valid on both; production target is MySQL 8.4 |
| No Redis | Queue/cache/session on the database | Fine for local; production must use Redis |

## Not yet implemented (schema exists, behaviour pending)

- **Provider onboarding HTTP endpoints** — credential submission, document upload,
  review verdicts. Engine (`ProviderEligibility`) and schema are done.
- **Catalog publish/approval endpoint** — only a licensed medical reviewer may publish.
  Currently `homecare:seed-catalog` creates drafts and `--publish` is a local preview flag.
- **Eligibility screening endpoint** — red-flag rules are stored in `hc_services.red_flag_screen`
  but no endpoint evaluates them into `eligible / requires_information / rejected_red_flag`.
- **Booking HTTP endpoints** — create booking, open wave, accept offer, customer confirm.
  `DispatchService` is complete and tested; only the HTTP layer is missing.
- **Visit HTTP endpoints** — depart/arrive/confirm/complete. `VisitSessionService` is done.
- **SOS HTTP endpoints** — `SosService` is done; no controller yet.
- **Pricing engine** — min-30-minute, night/holiday uplift, travel-per-km, overrun cap.
- **Refund + dispute workflows** with two-person approval.
- **Live operations stream** — the admin room renders an explicit "not connected" state
  rather than fake data. Needs an authenticated Reverb/SSE channel (T-061).
- **Admin staff-role gate** — the admin SPA authenticates with a normal token. A staff
  role/permission check on the API side is required before the room is reachable.

## Test coverage gaps (from `07_TEST_SCENARIOS.md`)

Written and passing: T-002, T-003, T-004, T-011, T-012, T-022, T-023, T-024, T-030,
T-031, T-034, T-045, T-046, T-047, T-048, T-057, T-058, T-059, T-060.

Not written yet: T-001, T-005–T-010, T-013–T-021, T-025–T-029, T-032–T-033, T-035–T-044,
T-049–T-056, T-061–T-070.

## Not live verified

- SMS, WhatsApp, push and email: **development adapters only**. The SMS adapter explicitly
  reports "not delivered", and that truth is what the SOS tests assert.
- Maps/routing: `SqlGeoDistance` gives great-circle distance and a labelled fallback ETA.
  No Egyptian road-routing benchmark has been run (#220 gate).
- Payment gateway: `mock` only. Webhook handling is signature-gated and idempotent, but no
  real provider has been integrated or tested.
- No malware scanning on uploads (T-068) — the column exists, no scanner is wired.

## Compliance

All legal, clinical, privacy, accounting and SOS-staffing gates remain **open**
(`docs/LEGAL_PENDING.md`). Nothing in this build authorises a public launch.