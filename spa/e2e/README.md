# Phase 3 — Playwright L3 journeys

Critical admin SPA journeys against the **packaged SPA at `/admin`** (same path as production cutover).

## Prerequisites

1. PHP server with packaged SPA + API:

   ```bash
   # from repo root
   mkdir -p custom/json-db site/files/resources site/files/temporary cache
   touch cache/composer-check.flag
   REPO_ROOT="$(pwd)/" php -S 127.0.0.1:8080 \
     core/inc/bigtree/api/_smoke/ci-router.php
   ```

2. Level-2 admin user (same as smoke):

   ```bash
   export E2E_EMAIL=smoke@ci.local
   export E2E_PASSWORD='SmokeTest-CI-2026!'
   # seed via SQL / smoke job pattern
   ```

3. Browsers (once per machine):

   ```bash
   cd spa && npx playwright install chromium
   ```

## Run

### Local (recommended): Vite + PHP API

PHP must be up first (API at `/admin/api/v1`). Playwright starts Vite automatically:

```bash
# terminal 1 — API (from repo root)
REPO_ROOT="$(pwd)/" php -S 127.0.0.1:8080 \
  core/inc/bigtree/api/_smoke/ci-router.php

# terminal 2 — E2E
cd spa
export E2E_API_BASE=http://127.0.0.1:8080/admin/api/v1
export E2E_API_TARGET=http://127.0.0.1:8080/admin/api/v1
export E2E_EMAIL=...
export E2E_PASSWORD=...
npm run test:e2e:vite
```

### CI / packaged SPA

When `admin_root` is `/admin/` (CI env), point Playwright at the packaged SPA:

```bash
export E2E_BASE_URL=http://127.0.0.1:8080/admin
export E2E_API_BASE=http://127.0.0.1:8080/admin/api/v1
npm run test:e2e   # no E2E_USE_VITE
```

Note: if local `admin_root` is `/remaster/admin`, the packaged SPA at `/admin` will not load assets correctly — use Vite mode.

Interactive:

```bash
npm run test:e2e:ui
# or
E2E_USE_VITE=1 npm run test:e2e:ui
```

## Journeys

See `critical-journeys.spec.ts` — maps to plan Phase 3:

1. Login → dashboard  
2. Pages create & publish  
3. Tags add  
4. Editor denied developer  
5. Settings value edit  
6. Modules (scaffolded) open  
7. Files browser  
8. Developer template create/delete  
9. Pending draft (editor → admin)  
10. 301 redirect create  

## Selectors

Prefer `data-testid` on primary actions (`login-*`, `dashboard-page`, `page-create-publish`, `access-denied`). Otherwise role/label queries.

Convention: add `data-testid` when a control is not stable via role+name alone.
