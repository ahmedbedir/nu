# ADR-005 — Local database engine confirmed by product owner

- **Status:** Accepted (explicit user decision, 2026-10-25)
- **Context:** The stack requires MySQL. Inspection proved the machine has **no Oracle MySQL** — XAMPP's phpMyAdmin is only a web client and its backing server on `127.0.0.1:3306` reports `10.4.32-MariaDB` (`@@version_comment = mariadb.org binary distribution`). Presented with the options, the product owner chose to **continue on XAMPP's MariaDB**.
- **Decision:**
  1. Keep the application on Laravel's **`mysql` driver** (`DB_CONNECTION=mysql`). No PostgreSQL, no PostGIS, no SQLite for application data.
  2. Write only SQL that is valid on **both MariaDB 10.4 and Oracle MySQL 8.4**: InnoDB, FK, CHECK, unique indexes, DECIMAL money, generated columns, `ST_Distance_Sphere`.
  3. Feature-test anything engine-sensitive before relying on it; never emit DDL that silently fails on one engine (master prompt requirement).
  4. Keep the existing `nu` database untouched and prefixed (`hc_`); production target remains Oracle MySQL 8.4 LTS on the business's own server.
- **Rationale:** MariaDB is a MySQL-compatible fork speaking the MySQL protocol; continuing on it is zero-risk for the user's existing database, matches what the project documents anticipate for XAMPP, and keeps the code production-portable.
- **Consequences:** `docs/SCHEMA_MAP.md` records local (MariaDB 10.4) vs production (MySQL 8.4) deltas. The adapter for geospatial distance stays behind one contract so production can use native SRID paths.