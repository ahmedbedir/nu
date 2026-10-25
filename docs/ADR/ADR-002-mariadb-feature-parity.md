# ADR-002 — MariaDB 10.4 local parity with MySQL 8.4 production

- **Status:** Accepted
- **Date:** 2026-10-25
- **Context:** Architecture references Oracle MySQL 8.4 spatial features (SRID column attribute, `SPATIAL INDEX`, `ST_Distance_Sphere`). Local XAMPP actually ships **MariaDB 10.4.32**. The master prompt forbids assuming PostgreSQL features and forbids installing/changing the DB engine to match assumptions.
- **Decision:**
  1. Feature-test every spatial/constraint construct against the **actual** engine before relying on it in a migration.
  2. Build the geo layer engine-agnostically:
     - Store coordinates as `DECIMAL(10,7)` latitude / `DECIMAL(10,7)` longitude columns (always present, nullable = "not supplied").
     - Add an optional `POINT` + `SPATIAL INDEX` column **only if** the engine accepts it in the feature test.
     - Compute distance via `ST_Distance_Sphere` when available, else a tested Haversine SQL/UDF; expose one `GeoDistance` contract so callers never branch on engine.
  3. Money = `DECIMAL(18,2)` (piasters exact), never float.
  4. "One winner" atomicity = transactional `SELECT ... FOR UPDATE` + unique index on a reserved-slot key (generated column if supported), **not** exclusion constraints.
  5. Record local↔production differences in `docs/SCHEMA_MAP.md`.
- **Rationale:** Requirements are met on the real engine without silently dropping essential constraints or breaking `nu`.
- **Consequences:** Some queries use a compatibility helper. Production MySQL 8.4 may switch the helper's internals to native SRID functions; the contract is unchanged.