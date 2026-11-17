# MPCLG Inventory — Web UI (Tier 1)

React 19 + TypeScript + Vite, Material UI 9 (with MUI X DataGrid), React Router 7, TanStack Query, axios.
Talks only to the Laravel API in `../backend`. It holds no business rules: it hides buttons
the user cannot use (from `/auth/me`), but the API re-checks every permission.

## Run (development)

```bash
# terminal 1 — API
cd ../backend && php artisan serve          # http://127.0.0.1:8000

# terminal 2 — UI
npm install
npm run dev                                  # http://localhost:5173
```

The dev server proxies `/api` to the API (`VITE_API_PROXY_TARGET`, see `.env.example`), so there is no CORS setup in development.

## Build

```bash
npm run build      # type-check + production bundle in dist/
npm run lint
```

For a separately hosted build set `VITE_API_BASE_URL` to the full API URL (e.g. `https://inventory.ministry.lk/api/v1`)
and add the UI origin to the API's `FRONTEND_URL` (CORS).

## Screens (Phase 1)

| Area | Screens |
|---|---|
| Sign-in | Login, forced password change (temporary passwords), forgot / reset password |
| Home | Dashboard (counts, my access, build progress, recent activity), My account |
| Access control | Users (list, create with one-time temporary password, detail with lifecycle actions, role assignment with scope and end date, **per-user permission On / Off / Role default switches**, activity), Roles (list, permission matrix editor), Permission catalogue (global switches), Modules, Delegations, Audit trail, Settings |
| Organisation | Divisions, Departments, Officers (with assignment history and transfers), Sites, Stores (with store officers), Locations, Designations, Public services, Service grades |

Every change that the API audits asks for a reason in the UI. Times are shown in Asia/Colombo.

## Layout

```
src/api          axios client (token, error envelope → ApiError), response types
src/auth         AuthContext (/auth/me, can()), route guards
src/components   ServerTable (server paging/sort/search), FormDialog, ReasonDialog, ScopeFields …
src/layout       App shell and permission-filtered navigation
src/features/*   One folder per area
```
