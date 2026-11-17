# MPCLG Inventory & Asset Management — API (Tier 2)

Laravel 13 / PHP 8.4 / MySQL 8 backend, built from `../MPCLG_Inventory_System_Blueprint.md`.
Status: **Phase 0 (Foundation) and Phase 1 (Access & Organisation) complete.**

## Setup

```bash
composer install
cp .env.example .env            # set DB_PASSWORD and ADMIN_PASSWORD
php artisan key:generate
php artisan migrate --seed      # modules, permissions, roles, org chart, settings, bootstrap admin
php artisan serve               # http://localhost:8000/api/v1/health
```

Sign in as the bootstrap admin (`ADMIN_USERNAME` / `ADMIN_PASSWORD` from `.env`). The first call must be
`POST /api/v1/auth/change-password`; every other endpoint answers `403 PASSWORD_CHANGE_REQUIRED` until then.

Scheduler (`php artisan schedule:work` or cron `schedule:run`): expires delegations hourly, prunes expired tokens daily.

## Tests

```bash
cp .env.example .env.testing    # APP_ENV=testing, DB_DATABASE=mpclg_inventory_test, CACHE_STORE=array
php vendor/bin/pest
```

101 tests: envelopes and request ids, login/lockout/password flows, user lifecycle, permission
resolution (deny beats allow, expiry, scope, delegation, cache refresh), module/permission switches,
last-admin protection, per-role permission matrix, and organisation master data.

## API docs

`php artisan scribe:generate`, then open `/docs`. A copy of the spec is in `docs/openapi.yaml`
and a Postman collection in `docs/postman_collection.json`.

## Layout

| Path | Contents |
|---|---|
| `app/Services/Access` | `PermissionResolver` (Section 5.2), `AuthService`, `UserService`, `RoleService`, `DelegationService`, `AccessControlService`, `AdminGuard` |
| `app/Services/Organisation` | Master data, officers + assignment history, store officers |
| `app/Services/Audit/AuditLogger.php` | Append-only, hash-chained audit trail |
| `app/Support/Access/Catalogue.php` | Permission catalogue and seeded role mappings |
| `app/Http/Middleware` | `RequestId`, `ForceJson`, `EnsureUserActive`, `EnsurePasswordChanged`, `CheckPermission` |
| `routes/api_v1.php` | All endpoints with their permission codes |
| `database/migrations` | One migration per table, in blueprint order; forward FKs added last |
