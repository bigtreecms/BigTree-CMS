# BigTree CMS 5

<http://www.bigtreecms.org/>

## Licensing

BigTree CMS is publicly licensed under the [GNU Lesser General Public License](http://www.gnu.org/copyleft/lesser.html).
If you would like to use BigTree under a different license, please [contact us](mailto:info@fastspot.com).

## Contributing

We would love to have the community work with us on BigTree. Guidelines are currently being created for how community contributions will be worked back into the project. For more information, please contact <contribute@bigtreecms.org>. If you would like to begin developing the BigTree core, follow the process below:

1. Fork it.
2. Create a branch (`git checkout -b 4.0_toms_branch`)
3. Commit your changes (`git commit -am "Fixed My Broken Foot"`)
4. Push to the branch (`git push origin 4.0_toms_branch`)
5. Create an [Issue][1] with a link to your branch

## Architecture (5.0+)

BigTree 5 adds three modernised layers on top of a frozen legacy core.
Requires **PHP 8.2+** (CI runs against 8.2).

```
core/                          ← core (god-classes + services; UI routing is SPA)
  admin/                       ← SPA dist, bar, field-types, upgrades, router
  inc/bigtree/
    api/                       ← REST API (JWT, /admin/api/v1)
    services/                  ← service layer
spa/                           ← admin SPA source (React 18 + TypeScript + Tailwind)
```

**Core**
`core/` contains the original god-classes (`BigTreeAdmin`, `BigTreeCMS`), the
global `SQL` helper, the REST API, and the service layer. The classic PHP admin
UI was removed in 5.0; historical UI is on the `master` branch if needed.

**REST API**
`core/inc/bigtree/api/` implements a JWT-authenticated REST API served at
`/admin/api/v1` by the standard BigTree front controller
(`site/index.php` → `core/launch.php`). A `Kernel` drives a middleware
pipeline (CORS → rate-limit → authenticate → permission → validate → audit →
JSON). Routes are declared in `core/inc/bigtree/api/routes/*.php` and merged
by `Manifest.php`; each route names a service method, its required permission,
and its request-validation rules. The full API surface is available as an
OpenAPI 3 document at `GET /admin/api/v1/openapi.json`.

**Service layer**
`core/inc/bigtree/services/` contains 27 plain PHP service classes
(e.g. `PageService`, `ModuleService`, `AuthService`). API routes dispatch
into these classes, which hold business logic and call the legacy core where
needed.

**Admin SPA**
`spa/` is a React 18 + TypeScript + Tailwind application that replaces the
legacy PHP admin UI, consuming the REST API above. Production serves the built
app from **`core/admin/dist/`** via `core/admin/router.php` at `{admin_root}`
(default `/admin`). Classic PHP admin UI routing has been removed.

See `spa/README.md` for dev setup (`npm run dev`, API proxy) and packaging:

```bash
cd spa && npm ci && npm run build:package
```

CI fails if `core/admin/dist` is stale relative to a fresh `spa` build. See
`scripts/package-admin-spa.sh`, `core/admin/README.md`, and
`docs/design/spa-admin-cutover.md`.

**Running the REST API locally**
The API is served automatically by the normal BigTree front controller — no
separate process is required. Once BigTree is running locally:

- `core/inc/bigtree/api/_smoke/README.md` — end-to-end smoke-test examples
  (curl commands that cover every route group).
- `core/inc/bigtree/api/_test/run.php` — lightweight PHP unit suite for the
  API internals.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.
