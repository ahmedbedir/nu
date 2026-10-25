# Acceptance / Threat / Integration Test Matrix

**Version:** 1.0 · Synthetic data only · Tests must be automated where feasible and supplemented by audited manual clinical/legal UAT

## A. Identity, patients, consent

| ID | Scenario and expected behavior |
|---|---|
| T-001 | Guest can view public services/professional summarized profile but not private data/current exact GPS. |
| T-002 | OTP verification rate-limited, expired/incorrect codes never authenticate; social login cannot bypass phone gating. |
| T-003 | Access patient ID from unrelated user → 403/404 without enumeration or private metadata. |
| T-004 | Authorize caregiver with book-only scope: cannot read diagnosis; revoke → access blocked promptly. |
| T-005 | Remote son books for patient in different town: route targets patient's saved verified pin, not son's GPS. |
| T-006 | Customer requesting female nurse without full verification cannot confirm booking; clear verification flow shown. |
| T-007 | Multiple family accounts linking same patient require verified authority; no record existence leak. |
| T-008 | Unknown medication/allergy recorded UNKNOWN without fictitious default; unsafe procedure not performed. |
| T-009 | A support agent creates booking on behalf of client: appropriate permission, recorded reason, exact actor trail. |
| T-010 | PII/PHI not emitted by errors, access logs, push notifications or CDN URLs. |

## B. Professional, clinical and geo

| ID | Scenario and expected behavior |
|---|---|
| T-011 | Unverified/expired licence cannot be made eligible by high rating/override. |
| T-012 | Nurse approved only for service A never accepts B (including within bundle). |
| T-013 | Credential validity warning, expiry and immediate eligibility suspension verified with simulated clock. |
| T-014 | Clinical catalog edit requires medical publisher approval and preserves old quote snapshot. |
| T-015 | Red-flag service assessment stops dispatch and gives emergency-channel guidance without AI diagnosis. |
| T-016 | Bad medication order / inconsistent dose → review or blocked procedure, no automatic edits. |
| T-017 | New pediatric age-specific service blocked without relevant provider qualification. |
| T-018 | Geo: cross-governorate allowed if service zone contains address, route ETA within policy. |
| T-019 | Provider's stale GPS excludes instant dispatch; advance bookings still use calendar rules. |
| T-020 | Region activation occurs only after qualified provider minimum and simulation success; existing bookings protected on later deactivation. |

## C. Matching, travel and bookings

| ID | Scenario and expected behavior |
|---|---|
| T-021 | Search-start default instant radius 10km, configurable per region, adaptive widening without wrong gender. |
| T-022 | Dynamic wave offers expire at configured 30s; accept after expiry rejected. |
| T-023 | 30 concurrent providers attempt to accept same request: exactly one winner/hold/confirmed booking. |
| T-024 | Provider provisional accept without customer confirmation remains UNCONFIRMED and hold expires safely. |
| T-025 | Two visits non-overlapping in clock time but impossible travel gap → conflict; no double confirmed. |
| T-026 | Direct-picked provider declines; customer offered explicit convert-to-auto-search prompt. |
| T-027 | Search map blurs/masks provider locations before booking. |
| T-028 | ETA routing provider failure surfaces safe degraded mode, does not invent travel time. |
| T-029 | Travel intent vs actual departure distinct, with correct notification and live-tracking starting point. |
| T-030 | Provider ARRIVED only + GPS inside geofence, customer does not confirm: timer remains off and paid duration zero. |
| T-031 | Customer confirmation + provider arrived valid: one session start event; retries idempotent. |
| T-032 | Provider goes offline en route: show last known position and freshness; warn/notify/escalate, not fake motion. |
| T-033 | Customer switches accepted provider → cancellation policy, new booking, no hidden reassignment. |

## D. Visits, fees, subscriptions

| ID | Scenario and expected behavior |
|---|---|
| T-034 | Time care at 12 minutes billed at declared 30-minute minimum; at 47 minutes billed exactly 47 minutes. |
| T-035 | Fixed-price procedure completed early retains agreed item price, subject to legal adjustments. |
| T-036 | Bill overrun beyond accepted automatic cap cannot be charged before customer re-consent; original duration still recorded. |
| T-037 | Extra in-visit procedure unauthorized for profession cannot be added even with customer approval. |
| T-038 | Append-only correction preserves original medical notes and correct actor/time/reason. |
| T-039 | Only verified patient/booking participants see clinical report and permitted images. |
| T-040 | Rolling 30-day recurring booking confirmed window; future dates are visibly tentative; no silent provider promise. |
| T-041 | 6/8/12-hour shifts and max 12-hour continuous policy; 24h coverage uses multiple qualified staff. |
| T-042 | Handover information retained and available to assigned, authorized next provider. |
| T-043 | Paid subscription start date purchase, one pause allowed within cap, renewal consent logged, no duplicate consumption. |
| T-044 | Separate calendars per patient, delegated access respected. |

## E. Cash, gateway, disputes and refund

| ID | Scenario and expected behavior |
|---|---|
| T-045 | Signed payment webhook replayed 10 times: exactly one settlement posting. |
| T-046 | Forged gateway webhook / browser 'paid' button does NOT create verified payment. |
| T-047 | Cash nurse says paid / customer denies: disputed status, not unilateral settlement. |
| T-048 | Ledger journal balanced, account currency consistent, rounding per piaster correct; correction compensating only. |
| T-049 | Provider commission and service-specific eligible item base correct; platform fee not charged to customer unless separately lawful/approved. |
| T-050 | Offline action cannot create paid status, wallet credit or unauthorized refund. |
| T-051 | Customer cancels before provider departure: no fee; after departure: distance/phase policy and evidence. |
| T-052 | Provider no-show: urgent replacement or eligible full refund; no duplicate capture. |
| T-053 | Customer no-show: 15-minute wait and documented contact attempts before fee. |
| T-054 | Refund to credit requires positive consent and legal allowed flow; otherwise original method available when required. |
| T-055 | Support cannot approve financial override reserved for manager; financial dispute routed manager, clinical incident copied to medical lead. |
| T-056 | Prepaid unit cancelled in allowed window returns to exact subscription ledger once, no duplicates. |

## F. SOS, security, operations, accessibility

| ID | Scenario and expected behavior |
|---|---|
| T-057 | SOS 3-second-ish configured hold with countdown and accidental cancel, initiation works for both roles. |
| T-058 | Offline SOS clearly remains unconfirmed; phone dial fallback, SMS only if OS supports, eventual resend works without duplicates. |
| T-059 | SOS ack and SOS resolved are separate events; central operations escalation triggers if no ack. |
| T-060 | Reporter unavailable to confirm closure: qualified senior exception logged, no silent case deletion. |
| T-061 | Provider phone GPS tracking stops after visit; unauthorized third party cannot subscribe to channel. |
| T-062 | Privileged staff break-glass patient record read logs reason and approved purpose. |
| T-063 | Admin suspended provider after grave complaint, remains suspended pending human review; appeal event preserved. |
| T-064 | Config changes/pricing versions/audit evidence retained against casual mutation. |
| T-065 | Arabic RTL customer/pro screens and English LTR admin mobile/responsive tests pass at common breakpoints. |
| T-066 | Keyboard navigation, accessible focus, no unlabeled SOS/confirmation control; color not only alarm indicator. |
| T-067 | Wrong service area/gender/provider eligibility access, IDOR, cross-tenant row leak, CSRF, SQL injection, path traversal blocked. |
| T-068 | Malware / oversized medical file quarantined and never publicly served. |
| T-069 | 24h escalation rota simulator fails closed when no on-duty responder configured (alarm visible and fallback triggered). |
| T-070 | Restore backup to isolated database, verify data and encryption key control, no production overwrite. |

## G. Real device + integration test gates

- Egypt route benchmark: city/urban fringe/village samples; routing ETA and real GPS accuracy measured on **actual devices**, vendor quotas and costs recorded.
- Android/iOS notification and location background permission scenarios across OS app lifecycle states; distinguish process killed vs backgrounded.
- Test phone-only SOS emergency flow with permitted dialing capability, no automated guarantee.
- Payment test gateway sandbox confirmed by actual signed webhooks; never assume unsupported payment mechanism.
- 50/100/500 parallel booking offer trials, lock and deadlock retry behavior, zero double allocations.
- Legal + clinical signoff UAT external, not 'green' because unit tests pass.

**Required output artifact:** `docs/TEST_REPORT.md` with `Test-ID → commit → command → observed output → PASS/FAIL/SKIP reason → env version`. Zero fake PASS or undocumented SKIP.
