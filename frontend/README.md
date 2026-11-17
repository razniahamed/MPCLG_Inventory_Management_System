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

## Screens

| Area | Screens |
|---|---|
| Sign-in | Login, forced password change (temporary passwords), forgot / reset password |
| Home | Dashboard (welcome hero, stock/asset figures, "Waiting for you" task list, assets by status), My account |
| Requests | Requests (create, submit, certify, approve/reduce, return, reject, cancel, approval timeline), Issues (prepare, post, acknowledge receipt), Returns to stores |
| Stores & stock | Stock balances, Stock ledger, Stock counts (count, recount, close), Adjustments, Discrepancies |
| Receiving | Purchase orders, Goods received (inspect, approve and post, reverse), Supplier returns |
| A4 paper | Machines, Meter readings (flags, verify), Shared usage, Paper statements, A4 exceptions |
| Assets | Asset register (QR label printing), Transfers & loans, Scan QR |
| Maintenance | Warranties, Repairs, Service schedules, Verification, Valuations, Disposals |
| Catalogue | Items (with pack conversions), Categories, Units, Suppliers |
| Access control | Users (list, create with one-time temporary password, detail with lifecycle actions, role assignment with scope and end date, **per-user permission On / Off / Role default switches**, activity), Roles (list, permission matrix editor), Permission catalogue (global switches), Modules, Delegations, Audit trail, Settings |
| Organisation | Divisions, Departments, Officers (with assignment history and transfers), Sites, Stores (with store officers), Locations, Designations, Public services, Service grades |

Every change that the API audits asks for a reason in the UI. Times are shown in Asia/Colombo.
The look (gradient blue sidebar and top bar, rounded cards, glass sign-in) follows `../referance system dashboard.png`
and `../referance system login.png`; colours live in `src/theme.ts`.

## Demo accounts

After `php artisan migrate:fresh --seed` and `php artisan db:seed --class=DemoDataSeeder` in `../backend`, sign in with
any of these (`<name>@mpclg.gov.lk`, password `Demo@12345`): `stores.keeper`, `receiving.officer`, `accountant.supply`,
`chief.accountant`, `cfo`, `it.clerk`, `it.director`, `legal.clerk`, `legal.head`, `repair.coord`, `auditor`, `staff.user`.

## Layout

```
src/api          axios client (token, error envelope → ApiError), response types
src/auth         AuthContext (/auth/me, can()), route guards
src/components   ServerTable (server paging/sort/search), FormDialog, ReasonDialog, ScopeFields …
src/layout       App shell and permission-filtered navigation
src/features/*   One folder per area
```
