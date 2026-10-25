# contracts — shared API + design contracts

This package is the single source of truth that keeps the three front-ends and the
Laravel API honest with each other. It contains no build step and no runtime code; it is
consumed by copy or by path.

```
contracts/
  openapi.yaml      # the versioned /api/v1 surface
  design-tokens.json # brand + semantic tokens consumed by web, admin and mobile
  events.md          # realtime / outbox event catalogue
  i18n-keys.md       # the shared translation key contract
```

## Rules

1. **The API is the contract.** A front-end may not invent a field that is not in
   `openapi.yaml`. When the API changes, this file changes in the same commit.
2. **Tokens are shared, not duplicated.** `design-tokens.json` is the origin of the
   values in `apps/customer-web/tailwind.config.ts` and `apps/admin/src/style.css`.
   The brand is not re-invented per surface.
3. **Events are named for facts, not commands.** `booking.confirmed`, not
   `confirmBooking`. A consumer must be able to replay the stream and rebuild state.
4. **No PHI in events or translations.** Event payloads and i18n strings carry
   identifiers and status only, never clinical text or direct identifiers.