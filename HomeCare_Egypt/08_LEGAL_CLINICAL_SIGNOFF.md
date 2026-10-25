# Required Legal, Clinical, Privacy, Accounting & Safety Sign-off Packet

**Status:** OPEN GATES, not legal advice; owner has lawyer and chartered accountant available. Do not block local engineering, but do block public clinical / payments launch until required approvals are documented.

| Gate | Responsible reviewer | Minimum recorded decision / evidence |
|---|---|---|
| Corporate activity and healthcare marketplace characterization | Egyptian lawyer | Verify company activity, contracts, liability for platform operations, needed permits and proper regulated-care relationships. |
| Professional scope and licences | Lawyer + licensed clinical lead | Verification channels for each profession; permitted procedures and credential expiry block; medical-board approval of catalog. |
| Medical liability and insurance fund | Lawyer + provider admin | Applicability of Law 13/2025, fund documentation, transitional rules and coverage validation by provider and entity; date of enforcement. |
| Optional professional insurance #60 | Lawyer + accountant + insurer | Quotes/risk cover matrix; only supplemental decision deferred. |
| Minor/guardian and incapable adult consent | Lawyer + clinical lead | Proof of authority, emergency pathway, separate consent for medical procedure vs managing a profile. |
| Sensitive personal data under 151/2020 and 816/2025 | Data privacy professional + lawyer | Data controller/processor registrations/licences where needed, lawful basis/explicit consents, DPIA, third-party vendors and cross-border transfers, retention, breach notification workflow. |
| Family access and medical records | Clinical lead + privacy lead | Scope of data disclosed before booking, privacy audit, revocation, retained medical record rules. |
| SOS and field safety operations | 24h operations lead + lawyer | Staff roster, escalation reliability, direct emergency call procedures, mobile permissions, service area obligations, incident closure policy. |
| Clinical waste disposal | Clinical lead + licensed disposal provider if needed | Procedure-specific PPE/Sharps and regulated waste collection/disposal record. |
| Money, taxes, insurance and payout | Chartered accountant + lawyer | Cash commission/receivables, invoices/tax, provider income/tax, withholding where applicable, gateway escrow/settlement, refund rights, subscription prepaid liabilities. |
| Stored value / credit | Lawyer + accountant + regulated payment partner | Allowed definition of refund credit, money transmitter/wallet licensing assessment, expiry, customer consent, reversal. |
| Maps and communications vendor | Product engineer + privacy lead | Data minimization; routing quality by Egyptian geography; SMS/WhatsApp terms and provider DPA. |
| Incident response / penetration test | Security lead + independent auditor | Threat model, incident drill, pen test remediation, encrypted backup restoration. |

## External legal sources reviewed for scoping (not a substitute for counsel)

- Egyptian Official Gazette, medical liability Law 13/2025: https://alamiria.com/Sec/TashTxt?id=M66Eg7pJDlA%3D
- Medical Error Insurance Fund registration (FRA, 2026-08-27): https://fra.gov.eg/en/fra_news/%D9%82%D8%B1%D8%A7%D8%B1-%D9%85%D9%86-%D8%A7%D9%84%D8%B1%D9%82%D8%A7%D8%A8%D8%A9-%D8%A7%D9%84%D9%85%D8%A7%D9%84%D9%8A%D8%A9-%D8%A8%D8%AA%D8%B3%D8%AC%D9%8A%D9%84-%D8%A7%D9%84%D8%B5%D9%86%D8%AF/
- Egypt Personal Data Protection Law 151/2020 (State Information Service): https://sis.gov.eg/ar/%D8%A7%D9%84%D8%B1%D8%A6%D8%A7%D8%B3%D8%A9/%D8%B4%D8%A6%D9%88%D9%86-%D8%AF%D8%A7%D8%AE%D9%84%D9%8A%D8%A9/%D8%A7%D9%84%D9%82%D8%B1%D8%A7%D8%B1%D8%A7%D8%AA-%D8%A7%D9%84%D8%B1%D8%A6%D8%A7%D8%B3%D9%8A%D8%A9/%D8%A7%D9%84%D8%B1%D8%A6%D9%8A%D8%B3-%D8%A7%D9%84%D8%B3%D9%8A%D8%B3%D9%89%D9%8A-%D9%8A-%D8%B5%D8%AF-%D9%82-%D8%B9%D9%84%D9%89-%D9%82%D8%A7%D9%86%D9%88%D9%86-%D8%AD%D9%85%D8%A7%D9%8A%D8%A9-%D8%A7%D9%84%D8%A8%D9%8A%D8%A7%D9%86%D8%A7%D8%AA-%D8%A7%D9%84%D8%B4%D8%AE%D8%B5%D9%8A%D8%A9
- Personal Data Protection Law implementing regulation, decree 816/2025 (third-party reproduction): https://masaar.net/ar/egypt_laws/%D8%A7%D9%84%D9%84%D8%A7%D8%A6%D8%AD%D8%A9-%D8%A7%D9%84%D8%AA%D9%86%D9%81%D9%8A%D8%B0%D9%8A%D8%A9-%D9%84%D9%82%D8%A7%D9%86%D9%88%D9%86-%D8%AD%D9%85%D8%A7%D9%8A%D8%A9-%D8%A7%D9%84%D8%A8%D9%8A/

## Suggested sign-off format

Create `docs/APPROVALS.md` with decision ID, document version and hash, reviewer identity/role, date, applicable service/region, legal restrictions, approved configuration and next review date. An available lawyer or accountant is not equivalent to actual approval. Keep features locked until the specific applicable gate passes.
