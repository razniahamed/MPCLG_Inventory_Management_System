# MPCLG Inventory System — backend (Laravel API)

The single source of truth is `../MPCLG_Inventory_System_Blueprint.md`. Read the relevant section before changing anything, and update the blueprint first if a table, column, rule or endpoint changes.

## Rules that must not be broken
- Business rules live in `app/Services/*`. Controllers stay thin (validate → service → resource).
- Every protected route has exactly one `permission:<code>` middleware. No super-admin bypass.
- Never hard-delete users or business records; never update/delete append-only rows (`audit_logs`, `auth_events`, later `stock_ledger` …).
- Write an audit row (`AuditLogger`) inside the same transaction for every write.
- Bump `users.permission_version` (via `AccessCache::invalidateUser`) on any role/permission/override/status change.
- New permissions go in `app/Support/Access/Catalogue.php` and are seeded; never hard-code checks.

## Commands (Windows; PHP 8.4 + Composer installed via winget / ~/bin)
- Tests: `php vendor/bin/pest` (uses MySQL database `mpclg_inventory_test` from `.env.testing`)
- Style: `php vendor/bin/pint`
- Fresh dev DB: `php artisan migrate:fresh --seed`
- API docs: `php artisan scribe:generate` → `/docs`, copy `storage/app/private/scribe/openapi.yaml` to `docs/`
- Serve: `php artisan serve` → `http://localhost:8000/api/v1/health`

## Build order per step
migration → model → factory/seeder → service → request/resource/controller/route → permission → tests → API docs.
