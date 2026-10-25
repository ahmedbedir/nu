# Preflight Report — HomeCare Egypt

**Date:** 2026-10-25 · **Mode:** READ-ONLY inspection before any mutation

## 1. Workspace

| Item | Value |
|---|---|
| Workspace root | `D:\nu` |
| Package docset | `D:\nu\HomeCare_Egypt\` (8 docs + SHA256SUMS) |
| Git repo | none yet — will be initialized |
| Existing app code | none |

## 2. Runtime binaries (detected, not assumed)

| Tool | Version | Path | Verdict |
|---|---|---|---|
| PHP CLI | **8.2.12** (ZTS, VC16 x64) | `C:\xampp\php\php.exe` | Below the stack's requested 8.3+ → ADR-001 |
| Composer | 2.10.0 | on PATH | OK |
| DB client/server | **MariaDB 10.4.32** | `C:\xampp\mysql\bin\mysql.exe` | Not Oracle MySQL → ADR-002 |
| DB port | 3306 (default; `my.ini` sets no port) | | OK |

## 3. `nu` database (READ-ONLY)

| Check | Result |
|---|---|
| Exists | yes (`C:\xampp\mysql\data\nu\`) |
| Charset / collation | `utf8mb4` / `utf8mb4_general_ci` |
| Tables | **none** (only `db.opt`) — database is empty |
| Other schemas | `mysql`, `performance_schema`, `phpmyadmin`, `test` (XAMPP defaults) |

**Implication:** no existing application data inside `nu`, but the database itself is preserved exactly. Every application table will be prefixed `hc_` so the schema is unambiguous and future-safe.

## 4. Safety plan (binding)

1. **Backup first:** dump `nu` to `D:\nu\backups\nu_<timestamp>.sql` before the first migration; verify file size/header.
2. **Additive only:** all migrations create `hc_*` tables. No `DROP`, `TRUNCATE`, `db:wipe`, `migrate:fresh`, `migrate:reset` against `nu` in any script.
3. **Test isolation:** destructive/integration tests run on a separate database `nu_test`; `phpunit.xml` points there.
4. **Runner guard:** test bootstrap asserts the test connection is **not** `nu` before running destructive tests.
5. **No secrets in repo:** `.env` git-ignored; `.env.example` holds placeholders only.

## 5. Local-vs-production differences to carry forward

- Local engine is **MariaDB 10.4**, production target is **MySQL 8.4 LTS** → engine-parity ADR-002.
- Local PHP is **8.2**; production target **8.3+** → runtime ADR-001.
- Redis absent locally → database queue/cache/session fallback (local test only).