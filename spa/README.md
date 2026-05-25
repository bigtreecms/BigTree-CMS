# BigTree Admin SPA

React + TypeScript + Tailwind admin frontend for BigTree's REST API.

## Prerequisites

- Node ≥ 20 (a `.nvmrc` is checked in — run `nvm use`)
- A running BigTree install with the REST API enabled

## Quick start

```bash
nvm use            # picks up Node 20 from .nvmrc
npm install
cp .env.example .env.local   # then edit VITE_API_TARGET
npm run dev
```

The dev server runs at <http://localhost:5173>. It proxies `/admin/api/v1/*` to
`VITE_API_TARGET` (your PHP server), so the SPA's requests look same-origin and
the JWT refresh cookie round-trips cleanly.

## Production build

```bash
npm run build      # outputs to spa/dist/
```

Apache serves `spa/dist/` at `/admin/spa/`. Add to your vhost:

```apache
Alias /admin/spa /path/to/bigtree/spa/dist
<Directory /path/to/bigtree/spa/dist>
    Require all granted
    Options -Indexes
    FallbackResource /admin/spa/index.html
</Directory>
```

The `FallbackResource` line is what makes React Router work — any URL that
doesn't match a file falls back to `index.html`, which boots the SPA and lets
the client-side router take over.

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
