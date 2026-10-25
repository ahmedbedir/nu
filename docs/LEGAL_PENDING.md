# Legal / Clinical / Accounting Gates — OPEN

**This file is not legal advice.** It tracks the sign-offs required before any public
clinical or real-money launch. An available lawyer/accountant is **not** an approval.

Built into the code already (so nothing launches by accident):

- Nothing is claimed as "production ready".
- SOS is described as **not** a substitute for ambulance/police services.
- `PatientAccess` never treats account ownership as implied patient consent.
- GPS never starts billing; only a verified two-sided check-in does.
- Client-side "paid" clicks never settle money — only a verified server webhook or a
  dual-attested cash confirmation.
- AI is limited to administrative/matching assistance; no autonomous clinical decisions.

## Gates

| # | Gate | Owner | Required evidence | Blocks |
|---|---|---|---|---|
| G1 | Corporate activity & marketplace characterisation | Egyptian lawyer | Written applicability, contracts, permits | Public launch |
| G2 | Professional scope & licences | Lawyer + licensed clinical lead | Verification channels, permitted procedures, expiry block, board-approved catalog | Clinical services |
| G3 | Medical liability & insurance fund (Law 13/2025) | Lawyer + provider admin | Fund documentation, transitional rules, enforcement date | Professional activation |
| G4 | Optional professional insurance (#60) | Lawyer + accountant + insurer | Quotes / cover matrix | Optional cover only |
| G5 | Minor / guardian / incapable-adult consent | Lawyer + clinical lead | Proof of authority, emergency pathway | Paediatric & incapacity flows |
| G6 | Sensitive personal data (151/2020, 816/2025) | Privacy professional + lawyer | Registrations, lawful basis, DPIA, cross-border transfers, retention, breach workflow | Public launch / sensitive records |
| G7 | Family access & medical records | Clinical lead + privacy lead | Pre-booking disclosure scope, revocation, retention | Record access |
| G8 | SOS & field safety ops | 24h ops lead + lawyer | Staff roster, escalation reliability, dial procedures, closure policy | Safety feature |
| G9 | Clinical waste disposal | Clinical lead + licensed disposal provider | Procedure-specific PPE/sharps protocol + disposal record | Procedure activation |
| G10 | Money, tax, payout, stored value | Chartered accountant + lawyer | Cash commission/receivables, invoices/tax, gateway escrow, refund rights, prepaid liabilities | Real-money launch |
| G11 | Maps & communications vendors | Product engineer + privacy lead | Data minimisation, Egypt routing quality, SMS/WhatsApp DPAs | Live routing / comms |

## Sign-off record format

Approval entries must be recorded (e.g. `docs/APPROVALS.md`) with: decision id, document
version + hash, reviewer identity/role, date, applicable service/region, restrictions,
approved configuration, next review date. Until then, affected features stay behind
disabled feature flags rather than launching quietly.