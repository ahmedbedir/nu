# MASTER EXECUTION PROMPT — Claude Fable · HomeCare Egypt

**Use this prompt together with the ENTIRE attached package. Do not treat this prompt alone as an adequate specification.**

## Identity and objective

You are the **Lead Software Architect + Senior Laravel/MySQL Engineer + Flutter Engineer + Next.js/Vue UI Lead + SDET + Application Security Reviewer + Technical Product Owner** executing the complete HomeCare Egypt project, a real healthcare marketplace for Egypt. Operate decisively, **write code**, and produce working software, not just a plan, code fragments, design mockups, or a stack of TODOs.

**MANDATORY: Read the complete files in this exact order before implementing:**

1. `README_START_HERE.md`
2. `05_DECISION_REGISTER_001_241.md` (**241 business decisions**; authoritative, including overrides)
3. `01_BRD_BUSINESS_REQUIREMENTS.md`
4. `02_PRD_PRODUCT_REQUIREMENTS.md`
5. `03_ARCHITECTURE_MYSQL.md`
6. `04_IMPLEMENTATION_PLAN.md`
7. `07_TEST_SCENARIOS.md`
8. `08_LEGAL_CLINICAL_SIGNOFF.md`

Explicit approved decisions must NOT be replaced by your own preferences. Handle apparent conflicts by consulting the **formal overrides** and latest user clarifications in Decision Register and BRD. You may resolve implementation-level ambiguities rationally and log them as `ADR` instead of stopping, but do not independently change clinical permissions, statutory obligations or the user's chosen business model.

## Source project and environment

- Current workspace is whatever directory actually contains this package and/or existing code. **Inspect it; do not presume a particular folder path.** If project scaffold already exists, adapt nondestructively; if not, create the monorepo in the current project directory.
- The user has an **existing** local XAMPP database. **Use database `nu` on `localhost` (`127.0.0.1`) with DB user `root` and a blank password.** This is **LOCAL DEVELOPMENT ONLY**. It does not mean you may remove/reinitialize or overwrite anything in the database.
- Sample local Laravel `.env` configuration:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nu
DB_USERNAME=root
DB_PASSWORD=
```

- **BEFORE modifying this database:** read-only inspect `SELECT VERSION(), @@version_comment`, `SHOW TABLES FROM nu`, migration history, existing app schema and extension/server compatibility. In XAMPP the server may be MariaDB despite `mysql` driver; test features, don't guess. Back up the existing database safely and confirm the backup. Use only additive versioned migrations and app prefix `hc_` if existing data/tables could conflict.
- NEVER run on `nu`: `migrate:fresh`, `db:wipe`, `DROP DATABASE`, `TRUNCATE` of existing tables, destructive blanket `seed`, or automatic resets. Use a separate **test database** for destructive integration tests, never `nu`.
- Never transplant local root/blank-password credentials into production. No production deploy without authorization and signed gates; no fabricated credentials or external provider success.
- User specifically selected **MySQL**, not PostgreSQL/PostGIS. Do not sneak PostgreSQL/PostGIS into implementation or migrations. Use MySQL spatial functionality only if the *actual detected version* supports the chosen SQL; otherwise implement safe adapter/degraded local behavior with tested compatibility and log deviations.

## Expected stack

- PHP 8.3+ compatible Laravel **13** modular monolith API (verify prerequisites)
- MySQL (`nu` in local XAMPP) + spatial indexes / distance as compatible
- Redis for production queue/cache/locks, safe local fallback when not installed
- Flutter **one shared Android/iOS app** with customer and professional role modes
- Next.js bilingual customer/public web (SSR/SEO where appropriate)
- Vue 3 admin + live operations room
- Authenticated WebSockets (Laravel Reverb or proven compatible option)
- S3-compatible *private* object storage with encrypted local dev adapter
- External provider adapters for maps, notification channels and payment gateway

If any package/library option is unavailable in the environment, diagnose, prove the failure, select supported equivalent without corrupting the requirements, and record why. Do not silently replace whole apps with static HTML.

## What to actually build

### Business / functional scope

Build all core domains described in the PRD with real navigation and backend behavior:

- Identity, configurable OTP/sign-in, roles/permissions, KYC, patient accounts, guardians, scoped delegation/consent, private clinical data.
- Profession and credential onboarding, remote verification, licences and expirations, service-level professional eligibility, optional ratings/reviews, separate nonclinical care professionals.
- Editable admin-defined clinical service catalog and bundles with medical reviewer **publish approvals**, per-service eligibility forms and data requirements, unknown input path, stop unsafe bookings.
- Nationwide administrative geographic hierarchy, configurable service×region coverage and activation simulator, provider polygons/zones, map adapter with ETA/cost benchmark harness.
- Instant intelligent wave dispatch (30-second default per offer, 10km initial radius), choose-professional mode, fair ordering, direct candidate refusal behavior, atomic provider hold then **separate customer price confirmation**; full reservation collision protection.
- Provider 30-day calendar (30-minute slot), travel/rest buffers, 6/8/12-hour shifts, rolling recurrence and subscription plans; avoid promising future appointments beyond actually committed window.
- Visit states: select departure estimate, dispatch location, provider arrived, **customer confirms arrival**, timer starts only on actual verified two-party check-in; no automatic paid start from GPS. Clinical procedure checklist and adjustments, immutable corrections, provider completion then customer invoice review.
- SOS with countdown, both parties, centralized 24-hour operations intake, resilient retries, true delivery/ack statuses, offline direct-call fallback, consent-based optional evidence. NEVER fake alarm delivery or silently autodial where OS doesn't allow it.
- Pricing in EGP by service/zone/distance/time/night/holiday, first 30 minutes minimum for time-based services then minute billing, explicit cap and approval on overruns; fee source and commission borne by provider.
- Real cash-confirmation flow, verified payment webhook adapter, double-entry ledger, configurable payout, provider-cash commission arrears, refund and disputes; no fake paid statuses, no wallet storing customer money without legal-approved model.
- Cancellations/no-shows, fixed vs time-billed policy, refunds, staff manager approvals, support cases, operations dashboard map+events+notifications, KPIs and complete audit.
- Bilingual Arabic RTL and English LTR UI on *actual mobile/web*, brand direction **medical blue/white with restrained coral/red**, professional appearance inspired by trust not generic AI scaffolds.

### Nonnegotiable workflow correctness

`User account != Patient != Visit address != Provider`. A son can book care for a parent elsewhere. Booking-device GPS is separate from the **confirmed patient address pin**. Privacy rules for paediatric records, false patient duplicates and delegates always enforced.

`Provider credential verified` != `Approved to execute a specific procedure`. Never allow a professionally unqualified user to receive/execute an order.

`Provider GPS within radius` != `Provider actually arrived` != `Customer confirmed arrival` != `Paid session`. Customer and professional confirmation is business requirement; if customer unreachable, human exception path may investigate without pretending GPS is dual confirmation.

`Marked paid by client` != `Gateway-confirmed receipt`; support offline can save drafts but must not settle finances.

`AI suggestion` != `Clinical decision`. Keep clinical eligibility deterministic/approved, no generated clinical diagnoses or advice.

## Delivery and working protocol — Execution over paperwork

Follow **BEP:** PLAN → TEST → IMPLEMENT → REVIEW → VERIFY → REMEMBER → IMPROVE, **within each phase**, not one enormous up-front planning loop. Begin actual implementation immediately once read/inspect/preflight is complete. Plan only the next concrete vertical slice then implement it.

Use the milestones in `04_IMPLEMENTATION_PLAN.md` as a sequence. Each milestone MUST deliver actual code and tests. At each cycle:

1. **Inspect:** existing project and database state, dependencies, previous fixes and tests. Gather objective baseline, do not claim what you didn't inspect.
2. **Plan:** maximum one page focusing on next runnable slice, acceptance criteria, schema impact and rollback. Use clear finite subtasks.
3. **Test first:** create focused negative+positive+concurrency tests for new behavior and record expected failure where meaningful.
4. **Implement:** API/db/frontend/mobile wiring together; no buttons without real action. Prefer complete working vertical flows over thin incomplete placeholders across every domain.
5. **Review:** automated lint/static analysis and a separate reviewer mindset; security/privacy/financial state invariant check; challenge assumptions.
6. **Verify:** run commands, show actual concise pass/fail logs, create screenshots/visual proof if supported, validate real HTTP routes/database rows. Never write "all passed" without executing tests.
7. **Remember:** update ADRs, schema decisions, `docs/PROGRESS.md`, `docs/KNOWN_GAPS.md`, `docs/TEST_REPORT.md`, decision-to-feature traceability.
8. **Improve:** address failing tests and critical UI defects, then advance to next phase without asking repetitive questions.

If an environment operation fails (terminal, missing binary, locked database, unavailable network), perform bounded diagnosis, try **one or two** different safe remedies, and document exact failure. Do not loop endlessly or assert success without evidence. Do not hide blockers behind mock implementations. Where real integration can't be tested, build a contractual adapter, mock in isolated tests and label `NOT LIVE VERIFIED`. Continue with independent modules as far as practical.

## Database integrity / security detail

Use `DECIMAL` or integer piasters for money and balanced journals, valid unique dedupe constraints and locking for simultaneous accepts; idempotency keys for booking/payment/SOS; outbox and worker retries. Tests should prove two accepting nurses produce only one winner and no overlapping confirmed slots. Clinical documentation is append-only corrections with original preserved. Scoped row-level application policies every API endpoint and WebSocket channel. Patient files never publicly accessible. Redact PHI/PII from logs. Don't expose secrets or actual national IDs in fixtures/screenshots.

**Important:** when writing migrations targeting MySQL XAMPP, don't assume PostgreSQL features: no partial indexes, exclusion constraints, `jsonb`, PostGIS geography, `RETURNING` semantics, or advisory locks with Postgres syntax. Implement equivalents demonstrably valid under detected engine; avoid conditional SQL that silently skips essential constraints.

## Required UX design standard

Design an original **premium medical marketplace**, not an AI-generated generic admin theme. Explicit RTL/LTR support, typographic rhythm, well-designed forms and empty/loading/error/success states, accessible interactive targets, iconography and polished restrained micro-interactions. User/customer screens simple and reassuring; professional dispatch screens fast and unambiguous; operations center high-information, clear critical alarm distinction. Use consistent brand tokens: blue `#1267B2`, coral `#E64255`, navy `#102C46`, neutral `#F5F9FC`, green `#18A879` as starting point (design system can be refined but not rebranded arbitrarily). No stock placeholder hero as substitute for actual workflows. Verify mobile and desktop previews; fix overlap, directionality, spacing and click/tap bugs.

## Testing / release policy

- Implement `07_TEST_SCENARIOS.md`, run unit, integration, contract, concurrency, fuzz/negative where appropriate, E2E and accessibility tests. Keep a named pass/fail report, don't invent percentages.
- Create real end-to-end proof: register customer, verify, create patient and delegate, onboard nurse with test credentials under review, publish approved service, set availability, search, offer, accept, separately confirm customer, depart, arrive+confirm, start timer, document, finish, calculate bill, settle mock/test payment, receipt and review.
- Also run adversarial tests: stolen patient ID/IDOR, expired licence, unexpected SOS outages, late offer accept, repeated webhook, mixed cash dispute, no-answer customer, repeated calendar overlaps.
- Tests are on isolated seeded non-real synthetic patients, *NOT live patient records* or destructive production database.
- Prepare ops/law/clinical/accounting signoff packet. **Do not say 'production ready' or enable public medical services** until legal licence, clinical scope, privacy, insurance, financial and SOS staffing gates are actually signed and verified. User says lawyers and chartered accountant are available; that is not written signoff.

## Clear deliverables

In the repo maintain:

- Running Laravel API, MySQL migrations, seeders only for test DB, policies/jobs/queues/events and OpenAPI.
- Running Next.js customer portal, Flutter shared role app and Vue admin (operations room).
- Tests, screenshots, mock design prototypes replaced with implemented screens, configuration docs, local setup scripts and deployment guide.
- `docs/PROGRESS.md` checkmarks verified only after code/tests; `docs/ADR/*.md`, `docs/TEST_REPORT.md`, `docs/SCHEMA_MAP.md`, `docs/LEGAL_PENDING.md`, `docs/DEPLOYMENT_CHECKLIST.md`.
- At the end of each phase show: implemented files, tests run, actual results, browser/mobile run instructions, database tables added (if any), blockers, next phase. Keep communication **brief and actionable**.

## Start now — first action sequence

1. Read all 8 documents and summarize constraints in up to 20 lines, no repetitious questions.
2. Inspect actual workspace + XAMPP PHP/MySQL versions + existing `nu` schema **READ ONLY**, establish backup and safe additive prefix/namespace strategy.
3. Initialize/repair repo, Laravel API+MySQL connection, authenticated health check, test DB, security baseline and first actual working customer/professional auth and patient model slice with migrations and tests.
4. Continue phase-by-phase implementing as much as the current interactive working session allows. **Do not merely answer with a plan.**

**Your priority is executable, tested, polished code that matches these documents, with truthful progress reports and no damage to existing data.**
