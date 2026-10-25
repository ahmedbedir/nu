# Execution Plan — milestones, gates and handoff

**Version:** 1.0 · **Approach:** Implement vertically, complete each stage, then continue without unnecessary questions.

## Phase 0 — Preflight and safeguards

- Locate existing workspace; if new, create monorepo layout without replacing anything.
- Verify PHP/Composer/MySQL **actual XAMPP version** and all required binaries/extensions; inspect `nu` read-only, existing tables and migration state; backup before changes. Never reset or delete `nu`. Use application-specific table prefix if necessary.
- Install Laravel 13 (if PHP runtime is compatible), initialize MySQL local database connection with empty `root` password LOCAL ONLY, create isolated TEST database.
- Establish git repo/branch, README, `.env.example`, secret scanner, lint/checks, CI skeleton, formatter, unit/feature testing, OpenAPI scaffold, Docker optional for CI.
- Record known limitations and real test evidence. **Gate:** clean boot, real `nu` connection, tables preserved, blank health endpoints tested, initial baseline commit.

## Phase 1 — Core security + professional and patient records

- Identity/login/OTP abstraction, role-specific sessions, roles+RBAC, verification lifecycle, patient/guardian/delegation/consent, file storage and audit.
- Provider credentials, license/profession scopes, expiry blocks, clinical approval workflow, geography admin tree.
- Functional responsive customer registration/profile and professional onboarding, admin verification screens, RTL/EN UI.
- **Gate:** forged patient ID access denied, revoked delegation inaccessible, unlicensed provider cannot receive offers, upload abuse tested.

## Phase 2 — Approved catalog and matching + booking

- Service and medical approval workflow, service-specific screening forms, procedural eligibility deterministic rules, age restrictions, orders and unknown responses.
- Provider availability, calendar with travel/rest buffers, provider coverage polygon/distance. Geospatial and ETA provider adapters, mock/deterministic simulator, dynamic offer waves, 30s TTL, quote hold and double confirmation.
- Customer service browsing/search/map; professional offers with accept/decline; admin operations base screen.
- **Gate:** same booking concurrently offered to many providers has exactly one confirmed winner, patient clinical eligibility enforced, new provider ratings fair, quote expires correctly.

## Phase 3 — Real-time visit, clinical record and SOS

- Departure estimate, movement tracking (consented), arrival provider check + customer confirm, validated timer and visit procedure note audit. No automatic timer from GPS/geofence.
- Live Flutter/web trip map, provider role UI and notifications, SOS countdown, central ops alerts, retries, emergency fallback, escalation and operator ACK.
- Offline encrypted queued drafts; sync conflict resolution; SOS offline status truthful.
- **Gate:** GPS-only arrival creates zero billing; no SOS false-positive delivery; concurrent incidents operate independently; raw live provider location never appears to unrelated browsers.

## Phase 4 — Money and visit settlement

- Pricing rules/versions, min 30min charged for time-based visits with per-minute after, night holiday uplift, distance travel, additional approval ceiling and invoice line items.
- Double-entry ledger and provider receivables for cash, server-verified digital gateway (sandbox until signed partner), partial/full refunds, approval controls, disputes and payout cycles.
- **Gate:** ledger balances, money invariant tests, repeated payment webhook idempotent, no forbidden stored-value or unapproved credit flow on public launch.

## Phase 5 — Recurrence, shifts, plans

- Shift templates and handover, 30d provider calendar, recurrence and speculative future vs committed reserve, preferred provider/approved alternate, family plans, subscriptions/usage/pause/upgrade/renewal and reminders.
- **Gate:** 24h plan never assigns same nurse beyond 12h continuous cap, no overlap with travel/rest, future beyond horizon not marked confirmed, recurrent plan unit accounting correct.

## Phase 6 — Advanced operations and customer service

- Live operations room map and event stream; 24h roster, per-region operations, complaints and finance escalation, approvals and audit reporting, SMS/WhatsApp/Email/Push adapters, localized templates.
- AI admin assistance only with human approval of consequential actions, redaction, and no autonomous clinical decisions.
- **Gate:** staff access least privilege; high-risk finance/provider actions require approval; failed/slow notification channels degrade gracefully with tracked retries.

## Phase 7 — Hardening and sign-offs (mandatory before public launch)

- Security review + penetration test, threat model, OWASP ASVS baseline, dependency scanning, secrets scan, privacy impact, data retention review, incident drill, backup restore drill.
- Clinical lead signs service/procedure coverage and SOPs; lawyer signs entity licensing/medical liability/data processing/insurance/consents; accountant signs taxes, cash handling, refund/credit/payment accounting; ops signs 24h SOS staffing.
- Maps benchmark with actual Egypt test routes and price estimates, GPS accuracy tests across real devices/cities/villages.
- UAT across Arabic RTL, English, Android, iOS, web, admin, offline, degraded network; meaningful performance test with realistic concurrent bookings.
- **Gate:** only then production deployment with segregated DB creds and staging verified. Defer any feature without legal/clinical clearance behind disabled feature flags rather than silently launching.

## Phase 8 — Controlled activation and measurement

- Activate service×area combinations only after verification, simulations, staff coverage and provider availability thresholds. Monitor 24h alerts, lost offer races, quote timeouts, cancellation fairness, patient/provider complaints and contribution margin by region.
- Review metrics weekly during pilot; do not claim national coverage until proved.

## Deliverable gates for EACH engineering phase

1. Focused implementation source code and clean diffs.
2. New migrations additive/reversible, no destructive changes to `nu`.
3. API contracts/OpenAPI updated + localization and UI states.
4. Automated tests written first for critical invariants; results given with actual commands/output summary (PASS/FAIL), not invented.
5. Screenshot/visual UX check (desktop/phone + RTL/English); accessibility checks.
6. Security/permission negative tests; verified logging with no PHI/PII leakage.
7. Short change note, current working functionality, unsolved blockers, next phase.
8. Restore/backout plan for staged features and migrations.

## Priority implementation examples

**High risk:** onboarding legal/clinical licence verification; consent and delegation; no GPS automatic paid arrival; SOS; patient record access; payment integrity; duplicate provider acceptance; time/financial reconciliation. Test these ahead of cosmetic dashboards.

**UX quality equally mandatory:** no raw admin scaffolds shipped as product UI; coherent component library, skeletons, forms, copy, high-contrast red/blue medical identity, review all common breakpoints and right-to-left composition. App and web customer journeys must be actually navigable, not disconnected screenshots.

## Local commands template — only after verifying runtime and no schema collision

```powershell
# PowerShell examples; choose correct PHP executable under installed XAMPP.
php -v
composer --version
php artisan about
php artisan migrate:status  # inspect only
php artisan test            # MUST point to separate TEST DB, NOT nu
```

Actual source code directory and tooling are unknown at this handoff. The executor must inspect and adapt commands. Do not claim migrations, tests, app deployment, or MySQL schema were already executed.
