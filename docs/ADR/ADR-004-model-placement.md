# ADR-004 — Model placement and domain module boundaries

- **Status:** Accepted
- **Date:** 2026-10-25
- **Context:** Architecture §3 proposes `app/Domain/{Identity,Patients,Professionals,Clinical,Geo,Matching,Booking,Visits,Safety,Financials,Subscriptions,Support,Operations,Notifications,Compliance}/`. Laravel conventions expect models discoverable and `config/auth.php` pointing at `App\Models\User`.
- **Decision:**
  1. **Eloquent models** live in `App\Models\*` (framework-idiomatic; factories, route binding, policy discovery all work by convention).
  2. **Domain behaviour** — actions, services, engines, state machines — lives in `App\Domain\{Module}\` (e.g. `App\Domain\Identity\Actions\RequestOtp`, `App\Domain\Patients\Actions\GrantDelegation`).
  3. **Authorization** lives in `App\Policies\*` and is registered explicitly.
  4. Each domain module owns its tests under `tests/Feature/{Module}` and `tests/Unit/{Module}`.
- **Rationale:** Keeps the modular-monolith intent (behaviour grouped by domain) without fighting Laravel's model conventions, which reduces wiring risk and keeps `route:list`, policy auto-discovery and factories working.
- **Consequences:** A model may be touched by several domain modules; that is acceptable at this scale and mirrors the shared-database design in Architecture §2.