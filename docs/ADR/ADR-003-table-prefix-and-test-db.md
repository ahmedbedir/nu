# ADR-003 — Table prefix, schema namespace and test-database isolation

- **Status:** Accepted
- **Date:** 2026-10-25
- **Context:** The existing local database `nu` must never be wiped or reinitialized. `nu` is currently empty, but it is a shared-looking XAMPP database and the README asks for a prefix when the DB "is not empty or might be shared".
- **Decision:**
  1. **All** application tables are prefixed `hc_` (e.g. `hc_users`, `hc_bookings`). Laravel's `DB_PREFIX` is set to `hc_` so migration/model code uses unprefixed logical names while physical tables stay namespaced.
  2. Framework tables that Laravel creates (jobs/cache/sessions) are also created **only** explicitly and remain under the `hc_` prefix.
  3. Destructive/integration tests run on a separate database **`nu_test`** (`phpunit.xml` + `.env.testing`). The test bootstrap aborts if the active DB name is `nu`.
  4. Migrations are additive and reversible; no migration may `DROP` or `TRUNCATE`.
- **Rationale:** Guarantees the user's `nu` cannot be damaged, keeps app objects visually separable, and gives destructive tests their own sandbox.
- **Consequences:** Raw SQL must reference `hc_`-prefixed names; a helper/constant centralizes the prefix. Production uses the same prefix for consistency.