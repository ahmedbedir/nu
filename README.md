# HomeCare Egypt

Bilingual (Arabic RTL / English LTR) home-healthcare marketplace for Egypt:
an intermediary platform connecting families with **independently verified**
nurses and home-health professionals.

> **Status:** engineering build in progress. **Not approved for public clinical or
> payments launch** — legal, clinical, privacy, accounting and SOS-staffing gates
> are still open (see `docs/LEGAL_PENDING.md`).

## Monorepo layout

```
apps/api/            Laravel API — modular monolith (modules, migrations, tests)
apps/customer-web/   Next.js bilingual customer/public site
apps/admin/          Vue 3 admin + live operations room
apps/mobile/         Flutter shared customer/professional app
packages/contracts/  OpenAPI, event schemas, shared i18n keys
docs/                ADRs, progress, schema map, test report, sign-off gates
tools/               guarded local setup / read-only DB inspection
HomeCare_Egypt/      Original BRD/PRD/architecture/decision-register package
```

## Local prerequisites (this machine)

| Component | Detected | Notes |
|---|---|---|
| PHP | 8.2.12 (XAMPP) | Laravel 12 is used locally; production target is Laravel 13 / PHP 8.3+ (ADR-001) |
| Composer | 2.10.0 | |
| Database | MariaDB 10.4.32 via XAMPP on `127.0.0.1:3306` | MySQL-compatible; `phpMyAdmin` is only a web client (ADR-005) |
| DB name | `nu` (existing, preserved) | every table is prefixed `hc_` (ADR-003) |

## Quick start (API)

```bash
cd apps/api
composer install
cp .env.example .env            # or restore the provided local .env
php artisan key:generate
php artisan migrate             # additive only — never migrate:fresh on `nu`
php artisan homecare:sync-rbac  # idempotent reference data
php artisan serve
```

Health check: `GET http://localhost:8000/api/v1/health`

## Database safety (binding)

`nu` is protected. These are **forbidden** against it and are blocked in code by
`App\Support\Console\DestructiveCommandGuard`:

`migrate:fresh` · `migrate:reset` · `migrate:refresh` · `migrate:rollback` · `db:wipe` · `db:seed`

All application migrations are **additive** and use the `hc_` prefix. Destructive and
integration tests run on the isolated **`nu_test`** database (`phpunit.xml`), and the
test bootstrap refuses to run against `nu`.

## Tests

```bash
cd apps/api
php artisan test        # runs against nu_test
```

See `docs/TEST_REPORT.md` for the current pass/fail record.