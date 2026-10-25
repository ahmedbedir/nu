# Realtime / outbox event catalogue

Events are **facts in the past tense**. Every event is written to `hc_outbox_events`
inside the same transaction as the state change, then fanned out at least once.
Consumers must be idempotent on `event_id`.

## Booking

| Event | Payload (no PHI) | Emitted when |
|---|---|---|
| `booking.search_started` | `booking_id` | a wave is opened |
| `booking.offer_sent` | `booking_id`, `professional_profile_id`, `expires_at` | offers are created |
| `booking.provider_accepted` | `booking_id`, `professional_profile_id` | a provisional hold is created |
| `booking.confirmed` | `booking_id`, `quote_version` | **the customer** confirms the quote |
| `booking.confirmation_expired` | `booking_id` | the hold lapsed |
| `booking.cancelled` | `booking_id`, `actor_role`, `reason_code` | either side cancels |

## Visit

| Event | Payload | Emitted when |
|---|---|---|
| `visit.departure_eta_set` | `visit_id`, `eta_minutes` | provider sets travel intent |
| `visit.departed` | `visit_id` | provider actually departs (tracking starts) |
| `visit.arrival_claimed` | `visit_id` | provider taps "arrived" (never billing) |
| `visit.arrival_confirmed` | `visit_id` | **customer** confirms (billing starts) |
| `visit.completed` | `visit_id`, `billed_minutes` | provider ends the visit |
| `visit.time_corrected` | `visit_id`, `reason` | an authorised correction is recorded |

## Safety

| Event | Payload | Emitted when |
|---|---|---|
| `sos.initiated` | `sos_id`, `reporter_role` | durable intake succeeds |
| `sos.delivery_failed` | `sos_id`, `channel` | a channel reports no delivery |
| `sos.acknowledged` | `sos_id`, `staff_id` | ops acknowledges |
| `sos.escalated` | `sos_id`, `level` | SLA elapsed without ACK |
| `sos.closed` | `sos_id`, `closure_reason` | reporter or senior review closes |

## Finance

| Event | Payload | Emitted when |
|---|---|---|
| `invoice.finalized` | `invoice_id`, `total`, `commission_total` | lines are frozen |
| `payment.settled_electronic` | `invoice_id`, `amount` | **verified** webhook only |
| `payment.settled_cash` | `invoice_id`, `amount` | both parties attested |
| `payment.disputed` | `invoice_id`, `category` | cash mismatch or refund dispute |
| `refund.approved` | `refund_id`, `amount`, `approver_id` | financial manager approval |

## Never emitted

- Clinical text, diagnoses, medication names, national IDs, phone numbers, exact
  addresses or live coordinates in a broadcast payload.
- Any event claiming delivery that a channel did not confirm.
