# PRD — HomeCare Egypt, End-to-End Product Specification

**Version:** 1.0 · **Stage:** Prebuild baseline · **Origin:** Product discovery questions 1–241  
**Cross-references:** BRD, architecture, decision register, acceptance test cases

## 1. Shipping surfaces

| Surface | Personas | Required capabilities |
|---|---|---|
| Public responsive website (Next.js) | Visitors, customers | Service/area browsing, provider public pages, sign-in, patient & delegation, bookings, tracking, subscriptions, history, finance, support |
| Flutter mobile app (one binary, two modes) | Customer, healthcare professional | All mobile customer/provider journeys, push, foreground/background location permission, location freshness, arrival confirmations, visit records, SOS, offline-safe write queue |
| Vue admin SPA + Laravel API | Staff | Roles and actions, approvals, catalog, pricing, geographies, operations hub, safety, reports, dispute, audit, privacy requests, staffing |
| Public service APIs | All authenticated actors | Versioned REST; event-driven real-time updates; strict authorization |

**Localization:** Arabic RTL/English LTR, Arabic-first copy; preserve locale on emails, push, SMS, WhatsApp, PDF/report exports; money EGP and Africa/Cairo timezone display; timestamps internally UTC. Accessibility AA-oriented controls with low-literacy user flows, text scaling, screen readers, reduced-motion.

## 2. Functional specification by domain

### PRD-01 Identity, authentication, people

- Configurable registration: phone OTP, password, optional Apple/Google (on supported channels); multi-device sessions, token refresh/revoke, recover number via verified recovery and staffed escalation.
- Guest may browse service and generic provider profiles; no patient data, exact provider live GPS, or private visit details.
- Identity verification risk states (`unverified`, `pending`, `verified`, `rejected`, `expired`); full customer identity verification gate for female provider booking per product policy; step-up verification on suspicious modifications.
- A customer can manage multiple distinct patients; patient links have rights, delegated scope, time limits, proof and ability to revoke. Patient consents are not implied from account ownership.
- Adult competent patient's own scoped and verified delegation; minors and legal incapacities via specialized guardian/legal review. Emergency booking can be saved but no procedure can proceed without correct legal and clinical authorization.
- Patient medical fields: demographics, medical conditions, medicines, allergies, clinical notes, 3 prioritized emergency contacts. Explicit unknown supported for fields not known by booking delegate. Change history required.
- Duplicate linking may suggest safe proof route, **never reveal that another patient's record exists** before proper verification.
- Booking device's GPS and actual patient visit address are two different objects; for remote booking a precise destination pin/address must be confirmed independent of booker's position; provider will navigate to **visit** pin.

**AC-01:** account A cannot read user B's patient even if IDs guessed; legitimate revocable consent grants limited rights only, and revoked token caches invalidate promptly.

### PRD-02 Professional onboarding / verification

- Professional account supports several profession credential records. Capture national ID securely, qualification, applicable licence/registry membership, per-service scope, employer non-conflict declaration and current compliance evidence.
- Remote verification includes automated first pass and qualified reviewers for flagged high-risk files; baseline license validity for ALL active professionals; random audits are a second-level check, not substitute.
- On expiry of mandatory credentials: warn before expiry; block affected booking eligibility exactly at expiry. Credential transition events append-only; service-level permissions separate from account active state.
- Claimed years experience labelled `self-declared`, not verified. No compulsory routine platform quiz for simple tasks unless law/clinical requirement. Children's services require relevant documented qualification.
- Profile public display only first name, photo, self-declared experience label, rating and specialization; verified status may be displayed as accurate badge.
- Provider categories added through admin and reviewed by clinical/legal owners prior activation.

**AC-02:** invalid/expired/no-scope nurse receives zero clinical offers, regardless of GPS, score or admin override.

### PRD-03 Service catalog + medical form engine

- Admin creates categories/procedures/bundles, editable draft and published version; licensed clinical approver publishes.
- Structured service schema: profession/scope, medical-order requirement and validation method, safety/age/weight/risk form with UNKNOWN handling, stable-patient restrictions, preparation, necessary supplies/waste, duration, unit billing, geographies, eligible provider genders as available, medical note fields, photo attachment permission.
- Gate transitions: `eligible`, `requires_information`, `requires_qualified_review`, `rejected_red_flag`, `unavailable`. A red flag prevents conventional booking, shows contact emergency guidance. No AI diagnoses.
- Prescribed medicines supplied by client via licensed source; mismatched dose or unclear/old order moves to review; never execute from contradictory client-entered text.
- Multiple approved procedures one visit if one provider credentialled for all; otherwise propose split/rescope with new price and customer acceptance.
- Optional new procedure during visit: user request -> validate professional scope -> required clinical order -> disclose price -> consent -> record addition -> charge. If safety blocked, cannot add.

**AC-03:** service revision after booking retains historical catalog snapshot but safety-critical recalls can suspend unperformed visits.

### PRD-04 Geographies and coverage

- Administrative tree governorate → city/district → village/sector, with coordinates/polygons and source/versioning; admin can draw exceptions and per-service zones.
- Provider can cover across governorate boundaries. Coverage per profession/service automatically enabled only when minimum **verified, actually bookable** supply and simulator test gates satisfied; existing confirmed bookings preserved if supply drops.
- Areas without supply: demand interest, activation notifications; exact release date never promised.

**AC-04:** a provider located nearby but disallowed by service-zone boundary cannot receive a request.

### PRD-05 Search, real-time matching and direct booking

- Customer search modes: intelligent default, choose specific provider. Gender is required; alternative gender shown only after explicit consent.
- Filter hard eligibility first: licences, service-level approval, patient-service criteria, insurance compliance where required, shift schedule/rest/travel block, fresh GPS for instant, zone, no overlapping accepted bookings.
- Rank by estimated travel **time** primarily, straight-line or route distance secondary; then rating and reply speed (default neutral rating for new providers).
- Intelligent waves based on provider density and demand, initial max 10km configurable, 30s offer lifetime configurable, adaptive widening/routing ETA; new online provider can enter subsequent wave.
- State separation: `provider_accept` creates short exclusive offer hold; customer sees provider and final quote and explicitly accepts; only then booked confirmed. Configurable quote/hold expiry and atomic winner selection.
- No providers: broaden/next date/manual direction, no silent conversion to different gender/provider.
- Service split proposal for bundles requires customer agreement before replacing original quote.

**AC-05:** two simultaneous provider accept requests result in ONE unique winning reservation; late accept is rejected with idempotent explanation. Provider cannot be accepted for overlapping travel/visit/mandatory-rest times.

### PRD-06 Schedule, shifts, recurring, subscriptions

- 30min calendar slot UI; professional publishes up to 30 days; exception/holiday/break/leave; block only unconfirmed slots. Visit service duration may vary, bill in minutes where applicable.
- Shift templates 6/8/12 hours, upper continuous 12h subject to qualified occupational review; 24h continuous care has distinct professionals and handover note.
- Advanced recurrence RRULE-like definitions by day/date/interval; end by count, date, or open; full disclosure calendar/availability/estimates; edit one occurrence only from UI chosen by customer. Series beyond 30 days scheduled as **tentative future occurrences**, not fake confirmed reservations; rolling 30-day commitment.
- Provider first preference, replacement only accepted by client. Holiday surcharge needs consent. No-response escalation reminders + phone.
- Subscription plan types visit-units, hour-units, membership discounts; each plan defines service eligibility, activation areas, sharing family scope, carry-forward, pause-one-time, usage/expiry, renewal consent; validity begins at purchase; upgrade immediately, downgrade at next renewal.
- Family patient calendars are separate (not unified by default), each respecting delegation.

**AC-06:** future occurrence beyond booked horizon is displayed `planned_unconfirmed`, counted against neither provider guaranteed capacity nor customer's used units until appropriately confirmed or used.

### PRD-07 Live travel and visit execution

- On provider acceptance+customer confirmation, travel intent and departure-ETA may be selected within admin cap. Notify customer on true departure.
- Before departure show coarse provider region; from departure through session end client may view live tracking **under clear consent and strict audience/scope** (product choice #121), but no hidden tracking outside provider Online, trip, or ongoing visit. Record consent evidence; stop collection/transmission at end. Tracking inside patient's home has data-minimization controls: restricted sampling/exposure and conspicuous indicator; privacy/legal review can further constrain it.
- State: provider taps arrived; customer confirms. GPS alone records `geofence_arrival_observed`, NOT physical arrival or start of charge. If customer unresponsive, attempts and ops independent evidence path; never auto-bill.
- Timer starts at validated dual arrival confirmation and captures exact start/stop immutable UTC; service billing 30-minute minimum then per-minute for time-priced care; fixed-price procedures use agreed price.
- Provider adds checklist procedures, notes, optional consent-based media. Customers approve any out-of-scope priced addition. End initiated by provider, customer receives report and invoice review; disputes possible.
- If session running offline, local safe notes and candidate duration events enqueue with IDs, server later reconciles and audits. Disputed offline timestamps never auto-settle money.

**AC-07:** GPS at geofence for 20 minutes without customer's confirmation must yield **zero started paid time** unless authorized evidence-based human resolution is recorded separately.

### PRD-08 SOS and operational safety

- Two actor entry points, hold/countdown cancel. Critical centralized incident with last known GPS/current coords, user/visit IDs, emergency contacts, event timeline, location accuracy and age.
- Staffed 24/7 duty coverage, reminder/fallback escalation, ACK vs RESOLVE distinct; every alert highest priority. SOS attempts while offline prompt direct call; optional SMS where supported; pending packet resubmitted on reconnection.
- Auto-call/SMS only if supported by device and platform permissions and allowed by law; never claim silent background call can be guaranteed. Avoid automatic contact to public emergency lines absent verified lawful supported integration; instead offer direct dial.
- Manual audio/video upload on explicit consent only; encrypted retention. Normally reporter acknowledges closure; senior ops documented exceptional close if unreachable.

**AC-08:** simulate airplane mode, background termination, invalid GPS, concurrent SOS and expired token: no SOS event is falsely shown as acknowledged when not delivered.

### PRD-09 Pricing, invoice, commission, ledger

- Prices versioned by area/service/clock and provider overrides where authorized. Presearch estimate → post-provider quote → customer accept; price snapshot immutable for confirmed line items (except agreed modifications).
- Items: visit, time, clinical procedures, travel distance, night/holiday uplift %, extra approved procedures, package units/discounts, tax as legally scoped; all monetary calculations decimal minor units (EGP piasters) using exact decimal, no binary float.
- Pricing rule scopes which lines pay commission, provider bears it. Quote includes min first 30 minutes and thereafter minute-based where time-charged; protect max overrun accepted automatically; outside cap needs new consent.
- Gateway-specific prepay/capture/deposit per service/value; **only verified server webhook / reconciliation** settles electronic payment.
- Financial ledger immutable double-entry (customer invoice receivable, gateway clearing, cash collected by provider, commission due, provider payable, refund, adjustments, dispute hold); payout window/threshold admin-configured with controlled holds. Cash payment ideally attested by both; mismatch -> finance incident.
- Provider arrears: alert, exposure limit, legal permitted offset and graduated temporary restriction. All disputes financial-manager owned, crosslinked clinical/safety escalation if related.

**AC-09:** idempotent payment webhook twice posts **one** settlement only; ledger always balances; no refund beyond amount collected or legally authorized credit.

### PRD-10 Cancel/compensate/refund/dispute

- Client free cancel until departure event; after departure cancellation fee per configured travel distance/phase. On no-answer arrival, 15-minute wait + logged contact attempts before no-show fee.
- Provider cancellation -> free replacement; provider lateness threshold -> free client cancellation; provider no-show -> urgent alternative or full money refund.
- Procedure impossible -> refund nonperformed procedures, review time/callout disputes by root cause; agreed fixed-price service remains price unless law or misconduct requires adjustment. Time-billed actual-minute rules may differ; make quote explicit to avoid contradiction.
- Refund to platform credit only after **explicit opt-in** and permissible accounting/legal framework; original instrument fallback where required. Subscription unit clawback/return controlled by plan terms.
- Disputes link to visit, media/evidence, financial manager, SLA, any medical review, appeal and immutable closure reason.

**AC-10:** cancel during search, during customer quote hold, after departure, after arrival, and during session all produce distinct audit and settlement results.

### PRD-11 Admin and operations

- Permission-scoped staff custom role manager, with 2-person approval for big refunds and provider bans, except short-term safety pause requiring rapid approval audit.
- Live Operations Room: current searches/waves, accepted, en route, arrived, in session, delayed, failed, SOS, stuck jobs, payment flags, presence, qualified nearby resources; map with role-based privacy and retention limits.
- Ops staff may reschedule/reassign with both parties' relevant consent, no bypass of licences. Operational notifications on failures/delays/SOS; escalations with ACK, owner, time and evidence. Ticket-centric complaint workflow.
- Dashboards by company, area, service, date, provider cohort; export permission-controlled, redacted where needed; reporting view not production-DB raw personal dump.
- Append-only auditable actions on admin settings, provider credentials, consent, clinical access, payouts, disputes, deletions, SOS, pricing rule changes.

**AC-11:** a support agent cannot self-approve a refund requiring manager countersign; ops impersonation prohibited unless audited limited-session tool is explicitly approved.

### PRD-12 Notifications and communications

- Channel router with Push/SMS/WhatsApp/Email by notification type, user preference and lawful opt-in, priority fallback and idempotent delivery. Differentiate dispatched / delivered / acknowledged; no generic ‘sent’ equals received.
- In-app chat only associated with legitimate booking; phone contact with privacy masking/relay where technically viable; do not expose real number by default where relay is supported.
- Service templates localized; never put detailed diagnoses in SMS/push lock screen; retain consent logs and provider delivery costs.

### PRD-13 AI assistant and maps providers

- AI only assists office/support/dispatch scoring as a **suggestion**; deterministic hard medical and legal rules always control eligibility. Record recommendation explanation, model/version, accepted/rejected by human where consequential.
- Map routing/places adapter measures Egypt ETA accuracy, pin reliability, quotas, price; compare Google Maps and Mapbox; OSM-compatible routing only if production SLA credible. Prioritize actual road ETA not Euclidean distance. Fallback safe limits on failure.

### PRD-14 Privacy and security

- Separate identity/patient/address/clinical files; encryption in transit and at rest, secret management, password hashing, least privilege, audit, session expiry, purpose-specific consent, children safeguards, logged break-glass, periodic independent penetration tests.
- File uploads: MIME validation+magic bytes, size limit, malware scan/quarantine, private storage, signed short-lived downloads. Redact national ID and medical records from logs, analytics, chat LLM prompts and exception traces.
- Medical/document retention/deletion policies are policy-driven and authorized by counsel and clinical records lead, not arbitrary. External vendors vetted with DPA, transfer approval/permissions as applicable.
- Incident response: detection, containment, root cause, documented lawful notifications, restore test.

## 3. State-machine requirements

### Booking states

`draft → eligibility_check → search_pending → searching → offered → provider_provisionally_accepted → awaiting_customer_confirmation → confirmed → scheduled | preparing → departing → en_route → arrival_claimed → arrival_confirmed → in_session → provider_completed → customer_review_pending → invoiced → settled → closed`

Terminal/side paths: `eligibility_blocked`, `clinical_review_pending`, `no_match`, `offer_expired`, `customer_confirmation_expired`, `cancelled_customer`, `cancelled_provider`, `no_show`, `reassignment_pending`, `under_investigation`, `disputed`, `aborted_unsafe`. Reject invalid transitions server-side, append event log and emit notification via outbox.

Care-plan future occurrences have separate states: `planned_unconfirmed`, `reservation_held`, `booked`, `completed`, `skipped`, `rescheduled`, `cancelled`.

SOS states: `initiated_local → accepted_server → notified_ops → acknowledged → coordinating → resolved → closed`, with `delivery_failed/retry_pending` and `false_alarm` only with justification and source.

Payment states: `draft_invoice → finalized → due/authorized → capture_pending → paid_verified | cash_claimed → cash_confirmed | disputed → refunded/partially_refunded → settled`. Cash confirmation is not the same process as online gateway receipt.

## 4. Required API contracts (representative, versioned `/api/v1`)

| Endpoint / Event | Contract |
|---|---|
| `POST /auth/otp/request`, `/auth/otp/verify` | Rate limited; no account enumeration |
| `GET/POST /patients`, `POST /patients/{id}/delegations` | Scoped consent and purpose |
| `POST /professionals/onboarding`, `POST /professional-credentials/{id}/review` | Permission and dual verification as required |
| `GET /service-catalog`, `POST /service-catalog/{id}/publish` | Published version + approval |
| `POST /bookings/eligibility`, `POST /bookings` | Input schema, unknown handling, idempotency key |
| `POST /bookings/{id}/search`, `/offers/{id}/accept` | Atomic dispatch/hold, offer TTL |
| `POST /bookings/{id}/confirm` | Customer quoted amount and price version accepted |
| `POST /bookings/{id}/depart`, `/arrive`, `/confirm-arrival` | Distinct verified events |
| `POST /visits/{id}/actions`, `/complete` | Actor scope, append-only clinical notes |
| `POST /sos`, `POST /sos/{id}/ack`, `/close` | Highest priority incident, offline dedupe |
| `POST /payments/{gateway}/webhook` | Signature verified + idempotent event, never client trust |
| `POST /refund-requests`, `POST /finance/approvals` | Financial manager approval and ledger |
| `GET /admin/operations/stream` | Authz-scoped WebSocket/SSE events |

Every mutating request uses authenticated actor, policy authorization, input validation, ULID/UUID, idempotency when relevant, UTC timestamps, correlation/trace ID, normalized errors, structured audit and reliable event/outbox emission. Never expose PHI through open APIs or websocket broadcasts.

## 5. UX requirements

Design a premium, trustworthy medical-service product, NOT generic AI dashboard. Blue primary, reserved coral/red for action/safety, white/soft-neutral surfaces and dark text; typography appropriate Arabic/English, visible accessibility focus and CTA hierarchy. Distinct customer, professional and admin information density. Real UI states: searching, pending verification, no availability, failed GPS, permission denied, offline, upload rejected, delayed arrival, no response, held quote expiry, account restricted, payment disputed, SOS acknowledged vs unacknowledged. Mobile tappable targets and RTL mirrored navigation. Hero and marketing copy must not claim medical guarantee or emergency ambulance response.

## 6. Testable release criteria

All 14 domains need functional test coverage in 07_TEST_SCENARIOS. No mocked external gateway/provider in production masquerading as live. Real account data must never be used as seed data. No clinical booking publicly active without human-approved catalog, credential verification, incident response and legal sign-offs. Security checks need repeatable results and manual review. Generate screenshots/previews for desktop/phone screens and run RTL + EN visual QA; fix layouts before release.

**Business decisions are source-authoritative only when explicitly documented in decision register; proposed safeguards may require sign-off if they alter user business policy.**
