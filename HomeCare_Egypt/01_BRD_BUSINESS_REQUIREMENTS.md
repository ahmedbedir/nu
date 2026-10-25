# BRD — Home Nursing & Home Healthcare Marketplace (Egypt)

**Version:** BRD 1.0 · **Date:** 2026-10-25 · **Scope:** Decisions 1–241 · **Status:** Baseline / sign-offs pending

## 1. Business objective

Build a nationwide configurable marketplace connecting individuals/families with **independent, professionally verified** nurses and later other independently licensed home-health providers. Instant dispatch resembles ride-hailing; direct provider selection and planned/repeating visits resemble medical booking. Marketplace is an intermediary, **not an unlicensed clinical provider**; the lawyer must determine actual responsibilities, required registrations/licences and contracting model based on real-world operations, not wording alone.

**Launch channels:** responsive Next.js website + Android and iOS, one Flutter mobile app switching between customer/provider modes, Arabic and English from inception, 24-hour availability according to approved providers' real availability. Private servers (VPS/Dedicated) maintained by the business. Operating company already exists, to be evaluated by its lawyer for this activity.

## 2. Outcome measures and unit economics

Business KPIs: active verified providers by qualified service and coverage area; active bookable coverage (not just registered headcount); search-to-confirm conversion; time-to-first-qualified-offer; distribution acceptance rate; actual ETA vs predicted ETA; punctuality; completion and cancellation rates; repeated booking retention; provider utilization, no-shows, service quality, complaints per 1,000 completed visits, SOS acknowledgment time, payment collection rates, accounts receivable for cash commissions, gross booking value, platform recognized revenue (commission only where appropriate), refunds, incentives, chargebacks, unit contribution (revenue less maps, messaging, processing, support, incentive and incident costs).

No fabricated numeric targets. Set realistic SLA/target thresholds after pilot operational measurement; retain historical target versioning.

## 3. Business roles / actor separation

- Visitor: view generic services and limited public professional profiles, no patient/real-time private locations.
- Customer / booking account owner: authenticate, identity-check according to risk; gender-of-provider selection required; may book for patients in their care.
- Patient: unique clinical identity, own medical and consent records; may differ from customer and recipient at address; adults can delegate with authenticated consent; children/legal incapacity follow separate legal guardian policy.
- Delegate/family member: scoped, revocable permissions (book, pay, view selected visit status, view selected medical data) and traceable consent.
- Professional: one identity with potentially multiple **independent professional credential profiles**; must be licensed and verified for specific procedure; declares compatible external employment; can accept/reject without coercion; has validated coverage and schedule.
- Medical reviewer (qualified), document verifier, privacy officer, financial manager, 24h operations/support, regional manager, admin, super-admin, audit/security officer.
- Future: organizational buyer under independent account model; not part of initial B2C commercial launch.

Staff use prepared roles plus granular custom permissions; clinical record review is restricted to medical reviewers and support only on recorded necessity, with minimum required exposure and elevated approval for exceptional break-glass access.

## 4. Service catalog, clinical scope and boundaries

Admin creates categories; medical reviewer approves. A service is one procedure or an approved bundle; form schema can vary but mandatory safety fields remain. Each catalog version stores: category, licensed profession, required professional scopes and credentials, approved age range, exclusions/contraindications and red-flag screening rules set by licensed clinical reviewers, necessary orders and validity, required consumables/waste procedures, schedule duration, billing mode and component pricing, optional clinical photos, documentation field contract, active geographies and approval history.

Initial clinical offering is restricted to medically suitable **stable patients / straightforward services**, with additional fields and reviewer approval for selected pediatric services. Separate nonmedical personal care from professional nursing. Lab sampling may be enabled as separately regulated service after permissions. No remote diagnosis, prescription, clinical consultation or autonomous treatment advice; only administrative/preparatory inquiries remotely.

Unknown answers are recorded as **UNKNOWN**, never fabricated. Booking can continue if a necessary parameter is not known by the person booking, provided rules do not permit unsafe execution: the provider must establish minimum safety details before a clinical procedure or refer to qualified review. For a hard clinical red flag, stop normal booking and direct to appropriate emergency channels; the platform is not an ambulance service.

Patient supplies prescribed medications through licensed channels. Provider is responsible for standard equipment; no repetitive predeparture checkbox for every visit, but required equipment and service safety standards apply. Clinical waste disposal arrangements require validated legal and operational protocol. Medical document ambiguity and order/prescription conflicts halt affected procedure pending qualified review; never adjust medication orders automatically.

## 5. Key workflows / business rules

### 5.1 Identity, family and patients

Customer registration via configurable phone OTP, optional password and social sign-in; device/risk step-up authentication. Guest browsing allows general services/providers. Patient profiles unlimited per customer relationship, but one logical patient identity with authorized links (do not expose existence of other records before verification). Patient baseline includes name/age/sex, history, medications and allergies (unknown may be recorded explicitly). At most three prioritized emergency contacts. Identity recovery auto-first, human review fallback.

Full customer verification required for booking a female professional per selected policy; a general risk-based path applies to others. **Visit address is explicitly distinct from booking-device GPS**. User subsequently delegated choosing the better option: permit remote booking for another patient by entering/verifying patient visit pin, while recording booking-device location separately as available and proportionate. A phone's GPS position cannot be substituted for patient address. Requirements for determining reliable address and payer identity remain mandatory.

### 5.2 Discovery, booking and dispatch

Search-first UX, direct professional picking also available. Required provider gender, alternate gender only on explicit customer consent. Filter hard by verified active credentials, specific procedure permission, availability, geographic zone, patient/service age suitability and required documentation, then rank primarily predicted arrival time, secondarily distance, then user-selected secondary factors rating and responsiveness. New provider neutral score avoids cold-start lockout.

Instant default radius **10km**, per-area configurable expansion; dynamic waves with offer expiry default **30 seconds**, adjustable. Providers newly coming online can join candidates when better qualified/closer, without invalidating accepted offers. Public discovery maps show **coarse service areas**, not provider home/current coordinates. Provider live GPS must be recent to receive instant offers; old GPS suspends immediate eligibility until refreshed.

Base estimate before search; when provider accepts, **hold** a provisional slot, calculate final previsit quote, seek **separate customer confirmation**. Atomic award/confirmation prevents double-assignment; temporary hold expires safely and releases provider. Direct selection rejection offers customer the choice to run automatic search. If no providers, show schedule/area alternatives and collect interest for areas not active. Provider cancellation triggers approved replacement with customer approval; a switch initiated after confirmation requires cancellation/rebooking per policy.

Advance booking minimum **1 hour**, even though provider calendar works in 30-minute slots (duration/minute billing do not get rounded to slots). ETA based on map routing adapter, not straight-line distance as primary. Travel fee scales with traveled distance according to published rules and quote.

### 5.3 Provider availability, shifts and recurring care

Provider declares weekly schedule, exceptions, leave, rest and non-public blocked slots; opens calendar **30 days** ahead; may close unreserved slots. Shift lengths **6/8/12 hours**, single-provider continuous working max **12 hours** as a product rule, subject to qualified occupational and clinical review; round-the-clock care uses multiple professionals, documented handover. Break time is within shift but patient safety/coverage must not be compromised. Booking slots every 30 minutes, visit durations configurable by service; actual time billed by applicable pricing rule.

Recurring booking supports advanced patterns and termination by date/count/ongoing. Booking holds/confirmations rolling **30-day horizon**; longer series can be *searched/planned* up front but **must not be shown confirmed** beyond a provider's actual booked horizon. Preferred provider first, approved alternate selected by client; missed consent triggers reminders then call. Changes to an individual recurrence affect that single occurrence (not all following ones). Previously confirmed reservations protected, while availability can be rechecked. Holiday surcharges shown and accepted beforehand.

Subscriptions: visits, prepaid hours, discount membership, individual/family variants; can apply across activated areas with applicable services and providers. Rules for consuming units and carry-over configurable and clearly disclosed. Validity begins at **purchase**. Pause once within per-plan cap; immediate upgrade, downgrade on renewal; renewal method depends on package/payment instrument with explicit consent and advance notice. No surprise charges.

### 5.4 Live visit and professional documentation

Provider sets intended departure minutes within admin-defined maximum; tracking starts when provider actually departs. After provisional accept customer sees coarse location; live map on trip. Provider taps **Arrived**, and **customer separately confirms arrival**. Crucial explicit user clarification: GPS geofence is a signal only, NEVER starts paid session or verifies physical entry alone. If customer unavailable, record location and attempts; human-managed dispute/alternate proof path. Billed session begins only after valid two-sided verified check-in/appropriate human resolution, and captures audit evidence. Customer and provider can see session timer, with minute-accurate event timestamps.

Provider documents procedure list with timing and free notes; custom new procedure only after scope/clinical and price checks, informed customer consent, and separate audit. Long visit ongoing billed time and estimate ceiling trigger user consent before extra charges; clinical records may not be altered by invoice changes. Provider ends visit, report and itemized bill shown to customer for review; cash requires both parties' attestations (disagreement -> financial dispute), digital payment must be server-confirmed by provider webhook, not marked paid by client click.

### 5.5 Safety and incidents

SOS on both customer and provider apps. Hold plus countdown and cancellation of accidental trigger. Every SOS treated as highest priority; central staffed 24h operations desk with multiple fallback escalations; location, visit ID, emergency contacts, last known state; offline fallback phone and SMS only if device/network platform support, retry server submission when back online. Show emergency direct dial; do not promise automatic phone calls without OS/platform capability and lawful permissions. Optional manual audio/video evidence with consent and retention rules, not continuous secret recording. Incident close is typically after reporter acknowledgment; if reporter unreachable, senior reviewed evidence-based closure path with immutable log. SOS is not a substitute for national ambulance/police services.

### 5.6 Financial model

Pricing modes: platform default with provider-set approved services, fixed procedure, hourly/shift, visit fee + procedures, package and locality surcharges. Minute-based services: minimum **first 30 minutes** then charge by actual minutes; fixed price services remain fixed where agreed, clarify mixed packages in quote. Night/holiday uplift by percentage. Time overrun within explicitly agreed cap, later spend requires renewed customer consent. Provider pays platform commission; commission base configurable per service and line item, not necessarily including travel. Electronic prepays/deposits/capture vary by service and total.

Provider payout cycle configurable with minimum threshold and proper hold for disputed amounts; card settlement requires actual gateway. Cash provider collection enters financial ledger; unpaid platform commission is collectible receivable; notifications, credit limits, allowed offsets from future payouts and progressive account restriction. Every financial dispute escalates to financial manager; medical/safety dimension also triaged in parallel. Refund as platform credit **only on explicit customer consent and where legally permitted**; legally due original-method refund remains available.

### 5.7 Cancellation/refund

Free customer cancellation before provider starts traveling. Once traveling, cancellation fee by distance/phase; no-show: wait **15 minutes** with documented contact attempts, then applicable fee. Provider cancellation -> qualified substitute at no extra charge to client; lateness past admin-configured threshold -> client free cancel; provider absence -> urgent substitute or complete refund. Nonexecuted procedures refunded; agreed fixed-price service remains fixed upon early completion unless consumer-law/legal outcome dictates otherwise (time-billed services follow their actual-minute rules). Package cancellation debits/returns service units as package rule and phase dictate.

### 5.8 Operations, geography, quality and compliance

One operations hub with map, event stream, alarms, stats and manual actions; region managers with restricted scoped privileges; in-app support tickets tied to visit/evidence; actions require permission, reasons and audit. Sensitive disciplinary decisions and large refunds need managerial approval; immediate **temporary protective pause** may be authorized on imminent safety danger with retrospective management review.

New geographies auto-activate based on admin-defined minimum verified professionals **per eligible service**, after booking/dispatch simulator tests; protect confirmed bookings if staffing falls. Professionals may cross governorate borders if coverage/ETA/rules allow. Unavailable areas allow demand-interest registration and activation notifications. Geography data from map/geo sources, but reconciled administrative region references must be verified. Provider recruitment is open application plus field onboarding. All operating areas monitored for demand, efficiency, clinical quality, profitability and complaints.

Privacy: separate purpose-specific consents with revocation where lawful; patient record retention matrix approved by legal/privacy lead; encryption, access reviews and penetration testing; incident response including reporting timeframes; each external communications/payment/mapping/AI provider assessed before use. AI permitted for **administrative support and matching assistance only**, never autonomous medical decisions, issuance of orders or clinical screening decisions without deterministic/qualified policy governance.

## 6. Financial model examples (illustrative, not approved prices)

Billable line items: callout fee + fixed clinical procedure + [time units above 30-min minimum if time-based] + travel-by-distance + night/holiday percentage on configured eligible items + approved consumables − discounts/refunds. Commission on configured eligible items borne by provider; platform revenue recognition and tax need accountant review. Ledger posts debits and credits, never mutates cash totals as ad hoc counters.

Do **not** use fake user money balance as an unlicensed regulated payment instrument. Platform credit/refund entitlement should be implemented with accountant/lawyer-approved guardrails and possibly a licensed payment processor.

## 7. Rollout

Build complete product capabilities in code; launch operationally only when clinical/licensing/privacy and payments gates pass. Start with verified service/area combinations automatically enabled after test gates. Provider+patient apps, website, admin and safety processes are in baseline technical scope. Institutional B2B is a later commercial phase. Public deployment must not happen merely because local automated tests pass.

## 8. Pending external decisions and owners

| Item | Owner | Required output | Block? |
|---|---|---|---|
| Business licensing & intermediary obligations | Existing lawyer | Written legal applicability and contracts | Public launch |
| Scope-of-practice and pediatric eligibility catalog | Licensed medical lead | Signed catalog + red flags + training/credential evidence | Relevant clinical services |
| Data protection and cross-border processors | Lawyer/privacy lead | DPIA/records/consents/permits/retention | Public launch / sensitive records |
| Insurance fund applicability and evidence | Lawyer/clinical admin | Mandatory compliance workflow | Professional activation |
| Optional insurance #60 | Lawyer/accountant/insurance broker | Compare offers | Optional extra cover only |
| Commission, tax, provider payout and stored value | Chartered accountant/lawyer | Signed tax/ledger/refund/payment treatment | Real-money launch |
| Maps routing/vendor accuracy | Product/engineering | Egypt benchmark report + cost | Live routing launch |
| SOS escalation staffing and telecom permissions | Ops + lawyer | 24h rota, tested contact trees, legal terms | Safety feature launch |

## 9. Traceability

All customer-approved item-level decisions, including exceptions and overrides, are recorded in `05_DECISION_REGISTER_001_241.md`. Business interpretation and acceptance rules belong here and in the PRD; do not overwrite item-level decisions silently.
