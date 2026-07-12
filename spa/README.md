# BigTree Admin SPA

React + TypeScript + Tailwind admin frontend for BigTree's REST API.

## Prerequisites

- Node ≥ 20 (a `.nvmrc` is checked in — run `nvm use`)
- A running BigTree install with the REST API enabled

## Quick start

```bash
nvm use            # picks up Node 20 from .nvmrc
npm ci
cp .env.example .env.local   # then edit VITE_API_TARGET
npm run dev
```

Use `npm ci`, not `npm install` — this repo's `node_modules` is pnpm-tainted and
`npm install` corrupts it (you'll see a duplicate-vite `TS2769` build error).
Node 20 is required (`.nvmrc` is provided; run `nvm use`).

The dev server runs at <http://localhost:5173>. It proxies `/admin/api/v1/*` to
`VITE_API_TARGET` (your PHP server), so the SPA's requests look same-origin and
the JWT refresh cookie round-trips cleanly.

## Admin boot config (`window.__BIGTREE_ADMIN__`)

All install-specific paths flow through `src/lib/adminBoot.ts`:

| Field | Purpose |
| --- | --- |
| `basename` | React Router basename (no trailing slash) |
| `apiBase` | REST prefix including `/api/v1` (no trailing slash) |
| `wwwRoot` | Optional public site root from PHP config |
| `assetBase` | Optional asset root (trailing slash) |

**Dev:** `getAdminBoot()` always returns `{ basename: "", apiBase: "/admin/api/v1" }` so
the Vite proxy is used. Injection is ignored in dev.

**Production:** `core/admin/router.php` injects boot config before the bundle:

```html
<script>
window.__BIGTREE_ADMIN__={"basename":"/remaster/admin","apiBase":"/remaster/admin/api/v1","wwwRoot":"...","assetBase":"/remaster/admin/"};
</script>
```

If injection is missing, the SPA falls back to `basename: "/admin"` and
`apiBase: "/admin/api/v1"`.

API callers must use `apiBase()` from `@/lib/adminBoot` (via the shared `api` client
or `useUploads`) — do not hardcode `/admin/api/v1`.

## Production build

```bash
npm run build           # outputs to spa/dist/ (gitignored scratch)
npm run package         # copies to ../core/admin/dist/ (committed; no *.map)
# or both:
npm run build:package
```

**Shipped artifacts** live in `core/admin/dist/` (committed continuously). After
SPA source changes, run `build:package` and commit `core/admin/dist` or CI will
fail the staleness check (`scripts/package-admin-spa.sh --check`).

Production builds use Vite `base: "/__BIGTREE_ADMIN_BASE__/"` (rewritten by PHP to
the install admin path) and `sourcemap: false`. The package script also strips
any `*.map` files.

### Serving

The admin SPA is served by **`core/admin/router.php`** at `{admin_root}` (default
`/admin`). No Apache Alias is required. The REST API remains at
`{admin_root}/api/v1`. See `docs/design/spa-admin-cutover.md` and
`core/admin/README.md`.

If you ship a **`custom/admin/router.php`**, it fully replaces the core admin
router and must implement API hand-off, bar endpoints, and SPA dist serving
(see `core/admin/README.md`).

## Project layout

```
src/
  api/         REST API client + per-domain endpoint wrappers
  auth/        Token store, refresh logic, login flow
  components/  Reusable UI (buttons, tables, modals, etc.)
  hooks/       Shared React hooks
  lib/         Pure utilities (no React)
  pages/       Route components — one per screen
  routes/      Route tree + protected-route guard
  styles/      Tailwind entry + design tokens
  types/       Shared TypeScript types
```

## Stack
- **Vite 5** — bundler
- **React 18** + **TypeScript 5** (strict)
- **Tailwind CSS v4** — design tokens via `@theme`, OKLCH-native
- **React Router v7** (library mode)
- **TanStack Query v5** — server state caching + refetching
- **TanStack Table v8** — dense tables
- **Radix UI** — accessible primitives
- **lucide-react** — icons
- **react-hook-form + zod** — typed forms with schema validation
- **zustand** — small client-state stores (auth, theme, etc.)
