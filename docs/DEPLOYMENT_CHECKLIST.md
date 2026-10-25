# Deployment Checklist — HomeCare Egypt

**Rule:** no production deploy without explicit authorisation and completed gates
(`docs/LEGAL_PENDING.md`). Local XAMPP settings must never reach production.

## Before first production deploy

- [ ] All Phase 7 security items done: threat model, OWASP ASVS baseline, dependency scan,
      secrets scan, privacy impact assessment, incident drill, backup **restore** drill.
- [ ] Legal/clinical/accounting/SOS gates signed (see `docs/LEGAL_PENDING.md`).
- [ ] Maps benchmark on real Egyptian routes; GPS accuracy measured on real devices.
- [ ] UAT across Arabic RTL, English, Android, iOS, web, admin, offline, degraded network.

## Production infrastructure

- [ ] Production DB is **Oracle MySQL 8.4 LTS** with a least-privilege app user.
- [ ] **Root with a blank password is forbidden**; TLS required for DB connections.
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, strong `APP_KEY`, secrets in a manager.
- [ ] Redis for cache/queue/locks; Supervisor/systemd workers with restart policy.
- [ ] Authenticated WebSocket broker (Reverb or proven equivalent) validated on the host.
- [ ] Private S3-compatible object storage for PHI; presigned, short-lived downloads.
- [ ] Encrypted backups + tested restore to a clean database (never overwrite live `nu`).
- [ ] Host firewall, fail2ban/rate limits, 2FA for privileged staff.

## Application

- [ ] `php artisan migrate --force` (additive migrations only).
- [ ] `php artisan homecare:sync-rbac`.
- [ ] Queue workers running; outbox worker running; dead-letter queue monitored.
- [ ] `GET /api/v1/health` green from the public edge.
- [ ] Structured logs with correlation IDs; PHI/PII redaction verified.
- [ ] Observability: ledger-imbalance alarm, SOS delivery/ACK, queue lag, offer funnel.

## Rollback

- [ ] Every migration reversible; backout plan documented per staged feature.
- [ ] Feature flags per governorate / profession / service / payment channel.
- [ ] Previous release artifact retained for instant rollback.