# Schema Map — HomeCare Egypt

**Local:** MariaDB 10.4.32 (XAMPP) · **Production target:** Oracle MySQL 8.4 LTS (business VPS)
**Prefix:** every application table is `hc_` (Laravel `DB_PREFIX=hc_`).
**Convention:** non-enumerable `public_id` ULID on externally referenced entities;
internal FKs stay `BIGINT`. Money is `DECIMAL(18,2)` EGP. Times stored UTC.

## Domains and tables (84 tables created)

| Domain | Tables |
|---|---|
| Framework | `hc_users`, `hc_sessions`, `hc_cache`, `hc_cache_locks`, `hc_jobs`, `hc_job_batches`, `hc_failed_jobs`, `hc_migrations`, `hc_password_reset_tokens`, `hc_personal_access_tokens` |
| RBAC | `hc_roles`, `hc_permissions`, `hc_role_permissions`, `hc_user_roles` |
| Identity | `hc_phone_otps`, `hc_auth_identities`, `hc_identity_verifications`, `hc_kyc_documents`, `hc_consents`, `hc_audit_events` |
| Geo | `hc_regions` |
| Patients | `hc_patients`, `hc_patient_user_links`, `hc_patient_delegations`, `hc_patient_conditions`, `hc_patient_medications`, `hc_patient_allergies`, `hc_patient_emergency_contacts`, `hc_patient_addresses` |
| Providers | `hc_professions`, `hc_professional_profiles`, `hc_professional_credentials`, `hc_credential_reviews`, `hc_provider_service_approvals`, `hc_provider_areas`, `hc_provider_presence` |
| Catalog | `hc_service_categories`, `hc_services`, `hc_service_versions`, `hc_service_procedures`, `hc_clinical_form_templates`, `hc_medical_orders` |
| Booking / dispatch | `hc_booking_requests`, `hc_booking_idempotency`, `hc_bookings`, `hc_booking_events`, `hc_dispatch_waves`, `hc_dispatch_offers`, `hc_booking_holds`, `hc_scheduling_reservations`, `hc_booking_quotes` |
| Visits | `hc_visits`, `hc_visit_checkins`, `hc_visit_time_events`, `hc_visit_procedures`, `hc_visit_procedure_corrections`, `hc_visit_documents` |
| Emergency | `hc_sos_incidents`, `hc_sos_delivery_attempts`, `hc_sos_acknowledgements`, `hc_sos_escalations` |
| Financial | `hc_price_rules`, `hc_invoices`, `hc_invoice_lines`, `hc_payment_intents`, `hc_payment_events`, `hc_ledger_accounts`, `hc_ledger_entries`, `hc_cash_collection_confirmations`, `hc_refunds`, `hc_financial_disputes`, `hc_provider_receivables`, `hc_payout_batches` |
| Recurrence / plans | `hc_provider_availability`, `hc_provider_exceptions`, `hc_recurring_series`, `hc_recurring_occurrences`, `hc_shift_assignments`, `hc_clinical_handovers`, `hc_subscription_plans`, `hc_customer_subscriptions`, `hc_subscription_usage` |
| Support / ops | `hc_support_tickets`, `hc_admin_approvals` |

## Key invariants enforced in the schema

1. **Identity separation** — a booking owner, a patient and a visit address are three
   different rows linked explicitly; `hc_patient_addresses` is the visit destination and
   is never derived from a booker's device GPS (`booking_device_lat/lng` is recorded but
   is not a destination).
2. **Single active hold** — `hc_booking_holds.active_key` is a stored generated column,
   non-null only when `status='active'`, with a UNIQUE index. Combined with a transactional
   `SELECT … FOR UPDATE` this yields exactly one winner.
3. **Append-only clinical/time records** — `hc_visit_time_events`,
   `hc_visit_procedure_corrections` (original preserved), `hc_audit_events`.
4. **Money** — `hc_ledger_entries` is a double-entry journal (`journal_id`, `side`,
   positive `amount`); corrections are compensating entries only.
5. **Payment idempotency** — `hc_payment_events.gateway_event_id` is UNIQUE, so a replayed
   webhook posts once.

## Local ↔ production differences (deliberate, documented)

| Concern | MariaDB 10.4 (local) | MySQL 8.4 (production) | Handling |
|---|---|---|---|
| `TIMESTAMP NOT NULL` w/o default | rejected (`1067`) | tolerated | columns are nullable or `useCurrent()` |
| Identifier length | 64 chars | 64 chars | short explicit index names everywhere |
| DDL transactions | none | none | migrations are re-runnable from a verified-empty state |
| Spatial SRID attribute | limited | full | geo distance behind a `GeoDistance` contract; no SRID-dependent DDL required |
| JSON | `LONGTEXT` alias | native JSON | app uses Laravel `array` casts; DB checks kept simple |