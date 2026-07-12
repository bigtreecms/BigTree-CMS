# BigTree admin (core)

## Production SPA assets (`dist/`)

The React admin UI is built from `spa/` and packaged into **`core/admin/dist/`**.
That directory is **committed** so installs and CI work without Node on the host.

Do not hand-edit files under `dist/`. After changing anything under `spa/`:

```bash
cd spa
npm ci
npm run build:package   # build + copy to core/admin/dist (strips *.map)
cd ..
git add core/admin/dist
```

Or from the repo root after a build:

```bash
./scripts/package-admin-spa.sh
```

CI runs `./scripts/package-admin-spa.sh --check` after `npm run build` and fails
if `core/admin/dist` does not match `spa/dist` (maps excluded).

## Serving

`core/admin/router.php` is the admin front controller:

| Path | Handler |
| --- | --- |
| `{admin}/api/v1/*` | REST API (`BigTree\Api\Kernel`) |
| `{admin}/ajax/bar.js` | Front-end BigTree bar script |
| `{admin}/ajax/bar-logout` | Session logout for the bar |
| `{admin}/css/bar.css`, bar images | Bar chrome |
| `{admin}/assets/*`, other dist files | Packaged SPA (placeholder rewrite) |
| other `GET`/`HEAD` | SPA `index.html` + `window.__BIGTREE_ADMIN__` injection |

Classic PHP admin UI routing (modules, pages, layouts, classic ajax) has been
**removed**. Historical UI lives on the `master` branch if you need to reference it.

## What remains under `core/admin/`

| Path | Purpose |
| --- | --- |
| `router.php`, `_spa-serve.php` | Admin front controller + SPA helpers |
| `dist/` | Packaged SPA |
| `ajax/bar.js.php`, `ajax/bar-logout.php` | Front-end bar |
| `ajax/developer/upgrade/**` | Core upgrade revision scripts (CLI / SystemService) |
| `field-types/**` | Field process/draw/settings used by services + API |
| `css/bar.css`, `images/` | Bar assets |
| `email/` | Transactional email templates |
| `migrate-run.php`, `migrate-status.php` | CLI migrations |

## Custom admin router overrides

If an install provides `custom/admin/router.php`, it **replaces** this front
controller entirely. Overrides must implement the same contract:

1. Hand off `{admin}/api/v1/*` to `BigTree\Api\Kernel`
2. Serve the front-end bar endpoints above
3. Serve `core/admin/dist` (or equivalent) with SPA fallback for the UI

There is no runtime warning if a custom router is present — document overrides
in your deploy notes. See `docs/design/spa-admin-cutover.md`.
