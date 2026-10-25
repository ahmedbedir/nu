# HomeCare Egypt — Product & Engineering Handoff

**Version:** 1.0 (Discovery Baseline)  
**Date:** 2026-10-25  
**Status:** Business decisions captured; **NOT legally/clinically approved for public launch**  
**Primary language:** Arabic (Egypt) · English supported from day one

## Read in this order

1. `01_BRD_BUSINESS_REQUIREMENTS.md` — business model, operations, approvals, risks.
2. `02_PRD_PRODUCT_REQUIREMENTS.md` — actors, experiences, workflows, state machines, acceptance criteria.
3. `03_ARCHITECTURE_MYSQL.md` — Laravel 13 + MySQL architecture, schema plan, local XAMPP setup, privacy, integrity.
4. `04_IMPLEMENTATION_PLAN.md` — phase-by-phase delivery, gates and definition of done.
5. `05_DECISION_REGISTER_001_241.md` — **source of truth** for decisions 1–241 and documented overrides.
6. `06_CLAUDE_FABLE_MASTER_PROMPT.md` — send this prompt alongside this entire package to Claude Fable.
7. `07_TEST_SCENARIOS.md` — functional, concurrency, money, access-control, and failover test suite.
8. `08_LEGAL_CLINICAL_SIGNOFF.md` — review requests for lawyer, chartered accountant, and licensed clinical lead.

## The user's confirmed development environment

- Local XAMPP; DB host `127.0.0.1` (localhost), port normally `3306`.
- Existing database **`nu`**; username **`root`**; blank password **for LOCAL XAMPP ONLY**.
- **No connection to this XAMPP instance has been made by this document author.** The schema/version/content must be inspected by the executor before running migrations.
- No existing tables should be dropped or overwritten. Make a backup, inspect engine/version and table list first.
- Never use `migrate:fresh`, `db:wipe`, `DROP DATABASE`, destructive seeders, or resetting `nu`.
- Prefix new application-owned tables with `hc_` when the database is not empty or might be shared; verify migrations and model naming explicitly.
- Local credentials must not be reused on an exposed server; create least-privilege users for staging/production.

## Decision authority

- Explicit customer business choices: **APPROVED**.
- Proposed engineering solution and sequencing: **RECOMMENDED**, subject to implementer testing.
- Clinical service permissions and acceptance pathways: **PENDING clinical sign-off**.
- Egyptian licensing, processing of health data, insurance applicability, and financial stored value rules: **PENDING legal/accounting sign-off**.
- Map vendor: **PENDING actual Egypt routing quality + volume cost benchmark**.
- Additional optional professional liability insurance (decision #60): **DEFERRED**, mandatory legal obligations still apply.

**Non-negotiable distinctions:** booking owner != patient != visit address; GPS proximity != actual check-in; clinician authorization != provider account verification; cash marked paid != independently verified payment; SOS != emergency services; AI assistance != medical decision.
