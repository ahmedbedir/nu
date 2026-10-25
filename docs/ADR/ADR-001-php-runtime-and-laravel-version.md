# ADR-001 — PHP runtime & Laravel version for local development

- **Status:** Accepted (pending runtime confirmation once Composer runs)
- **Date:** 2026-10-25
- **Context:** Architecture asks for Laravel 13 on PHP 8.3+. Detected local XAMPP PHP is **8.2.12**. Laravel 12 already requires PHP >= 8.2; Laravel 13 is expected to require PHP >= 8.3.
- **Decision:**
  1. Attempt `composer create-project laravel/laravel:^13.0` first.
  2. If Composer refuses due to the `php` platform constraint, install the highest Laravel major that **this** PHP 8.2 runtime supports (Laravel 12) and continue the full build on it.
  3. Keep production target as **Laravel 13 + PHP 8.3+**; do not modify the user's XAMPP install without permission.
- **Rationale:** Master prompt: when a package is unavailable in the environment, prove the failure, choose a supported equivalent without corrupting requirements, and record why. Application code stays version-portable (no 13-only APIs; standard Eloquent/HTTP/queue features), so the upgrade path is a dependency bump, not a rewrite.
- **Consequences:** Local tests run on PHP 8.2/Laravel 12. `docs/LEGAL_PENDING.md` / deployment checklist record the runtime gap. Feature code must avoid APIs introduced only in Laravel 13.