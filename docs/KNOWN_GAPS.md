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

- **Live operations stream** — an authenticated SSE endpoint now exists and the room
  renders Live / Connecting / Denied states with real events. Production should replace
  the bounded SSE adapter with Reverb or an equivalent broker; the client contract and
  event names stay the same. Per-channel subscription authorisation (T-061) is still
  coarse: the whole staff stream is gated by the `staff` middleware.
- **Customer booking wait step uses polling** as a local fallback until the customer app
  consumes the same authenticated stream.
- **Eligibility screening is now called from booking creation** — hard red flags stop
  the conventional booking; UNKNOWN fields create `requires_information`; soft rules
  create `clinical_review_pending`. Typed API responses now return `422 clinical_blocked`
  with `outcome`, `reasons`, `missing` and `guidance`, or `409 booking_not_ready`.
- **Refund approval HTTP path is staff-gated but the admin SPA has no finance screen yet.**

Implemented since the previous gap report:

- Provider onboarding, credential submission/review and service-level approval endpoints.
- Booking create/search/quote/confirm and provider offer accept/decline endpoints.
- Visit departure/arrival/customer-confirmation/completion endpoints.
- SOS create/ack/close endpoints with honest per-channel delivery status.
- Pricing engine with callout, procedure/time, travel and night uplift lines.
- Clinical screening engine: eligible / requires_information / requires_qualified_review /
  rejected_red_flag, with age gating and no AI-generated clinical content.
- Catalog publish/suspend with reviewer permission and immutable version snapshots.
- Refund workflow: collected-amount ceiling, credit consent, self-approval block, dual
  approval above EGP 500, balanced compensating journal.
- Purpose-specific consent endpoints (grant/revoke).
- `staff` middleware so back-office routes reject customer tokens before permission checks.

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