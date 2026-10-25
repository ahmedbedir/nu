# Engineering Architecture — Laravel + MySQL + Flutter + Next.js + Vue

**Status:** Reference architecture, design baseline for implementation · **Date:** 2026-10-25

## 1. Runtime and compatibility decision

- **API/business engine:** Laravel **13.x** on supported PHP **8.3+** (verify PHP extension/dependencies present), typed PHP, domain modules in a modular monolith. Pin Composer lockfile and tested runtime patch versions.
- **Transactional database:** **MySQL** — 8.4 LTS recommended for dedicated production installation with InnoDB, UTC server timestamps, `utf8mb4`, rigorous FK and CHECK/unique constraints; **local database is already named `nu`** in XAMPP and must not be recreated.
- **IMPORTANT:** XAMPP frequently bundles MariaDB instead of genuine Oracle MySQL; executor MUST inspect `SELECT VERSION(), @@version_comment;` and feature-test GIS, generated columns, JSON, constraints and index syntax. Do not install/change DB engine just to match assumptions or run incompatible SRID DDL. Document local-vs-production differences. If compatible schema can't be safely built on actual local server, stop schema mutation and report specific incompatibilities with alternatives; never silently break the existing DB.
- Redis for ephemeral cache, nonces, locks, queues (production; if absent in XAMPP, safe queue/database fallback for local *testing only* with clear behavior). Background Supervisor/systemd service and PHP CLI workers, reliable outbox.
- Realtime Laravel Reverb or compatible authenticated WebSocket broker (prove operation on selected host); web Next.js, admin Vue 3, Flutter app with dual role mode; private S3-compatible objects plus staging/local encrypted filesystem adapter.
- Container-free local quickstart in XAMPP supported where possible; CI uses version-matched MySQL + Redis + app services. Production may use containers or systemd, both permitted on managed VPS/Dedicated.

**Official references (verify at implementation):**
- Laravel release support: https://laravel.com/framework/docs/releases
- MySQL SRID and spatial indexes: https://dev.mysql.com/doc/refman/8.4/en/spatial-index-optimization.html
- MySQL ST_Distance_Sphere: https://dev.mysql.com/doc/refman/8.4/en/spatial-convenience-functions.html

## 2. Architecture diagram

```text
Flutter Android/iOS (Customer+Provider)  Next.js Customer Web  Vue Admin
                \                           |                  /
                     API Gateway / HTTPS / rate limiting
                                  |
                         Laravel REST API
  +------------------------------------------------------------------+
  | Identity | Delegation | Credentials | Catalog | Clinical Forms   |
  | Regions  | Scheduling | Matching    | Bookings| Visits / SOS     |
  | Pricing  | Payments   | Double-entry Ledger | Subscriptions     |
  | Support  | Communications | Audit | Compliance | Reporting      |
  +------------------------------------------------------------------+
            |              |                 |            |
      MySQL InnoDB       Redis        Transactional       Object
      business truth   queues/locks   Outbox → Workers     private files
            |              |                 |
          GIS index      ephemeral         Push/SMS/WA/Email/Maps/Gateway
            \________________ authenticated live channel ____________/
```

Modules may interact via application contracts/domain events; they share one transactional database initially, without pretending to be microservices. Never isolate critical payment state in Redis without DB durable copy.

## 3. Repository layout

```text
project/
  apps/api/                 # Laravel modular monolith
    app/Domain/{Identity,Patients,Professionals,Clinical,Geo,Matching,Booking,
      Visits,Safety,Financials,Subscriptions,Support,Operations,Notifications,Compliance}/
    routes/api.php
    database/migrations/
    tests/{Unit,Feature,Integration,Contracts,Security,Concurrency}/
  apps/customer-web/        # Next.js bilingual public/customer portal
  apps/admin/               # Vue 3 permission-scoped admin + live operations
  apps/mobile/              # Flutter role-aware customer/provider app
  packages/contracts/       # OpenAPI, event schemas, i18n keys and shared DTOs
  infrastructure/           # Docker for CI/production optional, sample proxy/worker configs
  docs/                     # copied discovery specs and ADRs
  tools/                    # guarded local setup and read-only DB inspection
```

## 4. Developer XAMPP configuration — binding user instructions

**Development example only** (`apps/api/.env`, never commit real config file):

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nu
DB_USERNAME=root
DB_PASSWORD=
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

Use secure placeholder keys for any external services, never assume real credentials. Confirm `nu` exists with read-only query; never create or rename it without explicit need. If XAMPP actual MySQL uses a different port/driver, use inspected value and report. Disable sensitive real SMS/AI/payment calls in local until test credentials are configured. Never expose root/no-password access via public network or production. Set staging/prod `APP_DEBUG=false`, strong DB password least privilege, TLS, host firewall, encrypted backups, secrets manager, passwordless root forbidden.

**Required nondestructive preflight:**

1. Inspect code root and existing source using `git status`, `ls`, package manifests. Preserve user code and existing git branches.
2. Inspect binary versions: `php -v`, `php -m`, `composer -V`, `mysql --version`, `SELECT VERSION(), @@version_comment`, server timezones/collation.
3. Read-only query `SHOW TABLES FROM nu`; row counts/schema of potential collisions, plus migration history; detect if DB shared. Document snapshot WITHOUT dumping patient secrets into outputs.
4. Backup existing `nu` with least exposure, user permission if backup environment requires privileges; test backup file location and restorative plan.
5. Safe only-additive app migrations, in a transaction when supported and with backup/reversible migration documented; use prefix `hc_` for all new domain tables if `nu` not empty. Do not run destructive rollback on user tables.
6. Migrations checked via `migrate:status` and a new DB created **separately** for CI tests (`nu_test` or CI ephemeral; never test destructive reset against `nu`).

Never issue `DROP DATABASE`, `migrate:fresh`, `db:wipe`, blanket `truncate`, unscoped `delete`, `RESET MASTER` or `php artisan migrate:reset` against `nu`. For test fixtures use a separate isolated test schema.

## 5. MySQL data model (recommended normalized tables)

All listed names use prefix `hc_` and ULIDs where human correlation is needed. Large append-only tables use BIGINT PK + public ULID if volume benefits. Foreign keys and tenant/scope policies enforced in app and DB. Sensitive fields encrypted at application layer with separate key storage. JSON permitted for flexible form snapshots, **not** a substitute for normalized canonical finance and RBAC.

| Domain | Tables / key links |
|---|---|
| People | `hc_users`, `hc_user_sessions`, `hc_auth_identities`, `hc_phone_otps`, `hc_identity_verifications`, `hc_kyc_documents` |
| Role / privacy | `hc_roles`, `hc_permissions`, `hc_role_permissions`, `hc_user_roles`, `hc_consents`, `hc_consent_versions`, `hc_access_audits`, `hc_privacy_requests` |
| Patients | `hc_patients`, `hc_patient_user_links`, `hc_patient_delegations`, `hc_patient_conditions`, `hc_patient_medications`, `hc_patient_allergies`, `hc_patient_emergency_contacts`, `hc_patient_addresses` |
| Providers | `hc_professional_profiles`, `hc_professional_credentials`, `hc_credential_reviews`, `hc_provider_service_approvals`, `hc_provider_areas`, `hc_provider_availability`, `hc_provider_exceptions`, `hc_provider_presence`, `hc_provider_location_samples` |
| Catalog | `hc_professions`, `hc_specialties`, `hc_service_categories`, `hc_services`, `hc_service_versions`, `hc_service_procedures`, `hc_service_eligibility_rules`, `hc_clinical_form_templates`, `hc_medical_orders`, `hc_clinical_attachments` |
| Geo | `hc_regions`, `hc_region_geometries`, `hc_coverage_policies`, `hc_coverage_snapshots`, `hc_route_estimates` |
| Booking/matching | `hc_booking_requests`, `hc_booking_services`, `hc_booking_quotes`, `hc_bookings`, `hc_booking_events`, `hc_dispatch_waves`, `hc_dispatch_offers`, `hc_booking_holds`, `hc_scheduling_reservations`, `hc_booking_idempotency` |
| Recurrence | `hc_recurring_series`, `hc_recurring_occurrences`, `hc_shift_assignments`, `hc_clinical_handovers` |
| Visits | `hc_visits`, `hc_visit_checkins`, `hc_visit_time_events`, `hc_visit_procedures`, `hc_visit_procedure_corrections`, `hc_visit_documents`, `hc_visit_events` |
| Emergency | `hc_sos_incidents`, `hc_sos_delivery_attempts`, `hc_sos_acknowledgements`, `hc_sos_escalations`, `hc_safety_case_events` |
| Financial | `hc_price_rules`, `hc_price_versions`, `hc_commission_rules`, `hc_invoices`, `hc_invoice_lines`, `hc_payment_intents`, `hc_payment_events`, `hc_ledger_accounts`, `hc_ledger_entries`, `hc_payout_batches`, `hc_payout_items`, `hc_refunds`, `hc_financial_disputes`, `hc_cash_collection_confirmations`, `hc_provider_receivables` |
| Plans | `hc_subscription_plans`, `hc_subscription_versions`, `hc_customer_subscriptions`, `hc_subscription_usage`, `hc_subscription_pauses`, `hc_subscription_invoice_links` |
| Ops / comms | `hc_support_tickets`, `hc_support_ticket_events`, `hc_chat_conversations`, `hc_chat_messages`, `hc_notification_templates`, `hc_notification_dispatches`, `hc_delivery_receipts`, `hc_admin_approvals`, `hc_audit_events`, `hc_outbox_events`, `hc_idempotency_keys` |

**Relationships:** booking account user → `hc_booking_requests.customer_user_id`; patient separate FK; `visit_address_id` references explicit patient/visit destination; one confirmed booking may have provider assignment and several authorized procedure lines; provider account → many credentials → approved service constraints; recurring series → occurrences → optionally booking; visit → zero or many procedure events; payment → invoices → balanced ledger journal entries; SOS → visit or pre-visit booking + reporter.

**Key invariants and constraints:**

- No physician/nurse role privileged by self-declared profession: permissions must come from approved verified credentials and service-specific license scope records.
- `hc_booking_holds` includes expiry and unique active assignment per booking; DB atomic transaction+unique keys prevent double award. MySQL doesn't provide native exclusion constraints over arbitrary time ranges: use transactional overlapping-slot reservations with provider/date locking and consistent ORDER BY to prevent races; verify contention tests.
- `hc_ledger_entries`: journal ID, debit/credit side, exact `DECIMAL(18,2)` (or integer piasters) with CHECK positive, currency, business reference, immutable posted entries, correction via compensating journal. Per-journal balanced sum guaranteed by posting service in DB transaction and verified by reconciliation query/test; don't claim MySQL has a declarative cross-row CHECK.
- `hc_visit_time_events` append-only UTC with actor and proof; computed billed minutes from verified check-in to stop, not from untrusted client clock alone; manual overrides require reason/approval and compensating event.
- Provider GPS presence TTL in Redis for fast dispatch, durable bounded samples in MySQL with retention and consent; live positions only shared with booking authorized actors.
- Sensitive patient records cannot be joined against other accounts via raw `patient_id`; policy scope joins enforce delegation status and consent.
- `hc_outbox_events` written in same DB transaction as state change; worker uses at-least-once + idempotent consumers, retries and dead-letter queue, for SMS, live broadcasts, payment confirmations.
- `hc_audit_events` append-only at application layer, tamper-evident chain/immutable offsite copy for high stakes; MySQL privileged admins can still change local rows, so append-only DB alone is not cryptographic immutability.

## 6. Geospatial design (MySQL, NOT PostGIS)

Preferred: `POINT NOT NULL SRID 4326` with `SPATIAL INDEX` for non-null accurate coordinates on MySQL 8.4, and bounding box initial filtering; use separate geocoded address and optional lat/lon for verified vs not supplied, because indexed NOT NULL point cannot simply represent missing GPS. Example illustrative SQL only after engine/version verification:

```sql
CREATE TABLE hc_geo_points_example (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  position POINT NOT NULL SRID 4326,
  SPATIAL INDEX sp_position (position)
) ENGINE=InnoDB;
-- Illustrative query: ST_Distance_Sphere(A.position, B.position) in metres
```

Store point using explicit, validated longitude and latitude order with SRID=4326 in implementation. Validate incoming coordinates in Egyptian bounds by configured policy; spatial query first bounding envelope for selectivity, then precise spherical candidate distance, then route ETA via map provider. Check `EXPLAIN` and real query benchmarks in populated representative data. **Do not confuse spherical proximity with routed ETA, or GPS of booking device with address where patient is located.** On MariaDB XAMPP test actual supported SRID/index syntax before applying migrations.

## 7. Transaction strategy and concurrency

Booking flow: `BEGIN; SELECT booking FOR UPDATE; verify quote/offer TTL; lock candidate provider scheduling date; assert no overlaps incl route/rest; INSERT booking_hold and reservation with unique idempotency key; update state + append booking_event + outbox; COMMIT`. Enforce one-winner via unique `(booking_id, active_assignment)` mechanism compatible with actual MySQL; use generated column/unique if needed and feature-tested. Late accept returns conflict and preserves original winner. On customer confirmation expiry release by worker, but worker race rechecks TTL/state in locked transaction. Retry lock deadlocks bounded, with idempotency keys.

For price holds snapshot pricing rules/currency/fees before confirmation. Payment webhook signature verification precedes any transaction; dedupe by gateway event ID. For cash confirmations record each party independently; contradictory claims become dispute, not paid. SOS writing uses fast durable path not blocked by slow map/notifications API, and retry outbox for deliveries.

## 8. Privacy, health data and file transport

PII/PHI roles and policy checks on every read, write, export, real-time room join and attachment download. Patient record accesses audited by purpose+actor. Last-known coords from SOS only as needed. Never include identity numbers, PHI or keys in OpenAI/other AI prompts without explicit privacy/legal approval and minimum necessary/redaction; AI is admin assistant only.

Use server-side encryption of restricted columns and attachments, expiring signed upload/download, malware scan, secrets and key rotation, signed URL not predictable names; encrypted backups and periodic restore drills. Strict Content Security Policy, CSRF where relevant, 2FA for privileged staff, rate limits/IP abuse controls, consent management, data subject request workflow, breach runbook, incident drill.

## 9. Offline strategy

App local encrypted cache of *minimum necessary* scheduled patient/service data and safe procedure drafts; operation journal has ULID, local monotonic time, server ingest time, actor, causal version and sync status. Offline actions don't authoritatively change booking state, approval, identity verification, payout, refund, online-paid flag, or SOS delivered state. SOS UI uses OS dial intents and SMS fallback only where technically supported; server receipt after recovery clearly indicates when alert actually arrived. Conflict queue for human review of divergent check-in/times; no double count.

## 10. Observability and safe operations

Metrics: route latency/accuracy, GPS freshness, offers/sec, queue lag, successful confirmation funnel, location permission errors, pending wallet/refund liabilities, ledger imbalance alarms, 24h operations acknowledgment, SOS delivery attempts/ACK, chargebacks, clinical policy misses, privacy exports, audit write failures. Logs structured with correlation ID and masked PII. Alerts use distinct owners (finance/clinical/security/ops). Feature flags by governorate, profession, service, payments channel and provider type. Backups tested restoring to separate clean database, never restore by overwriting live `nu` during local QA.

## 11. Scalability boundary

Start single DB modular monolith with queuing, indexes, selective caching, read replicas only after evidence, and shard/extract service only when workload demonstrates need. No premature microservices or extra databases without objective cost/performance reason. Production cannot use empty root password or shared XAMPP settings.
