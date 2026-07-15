# Admin Parity E2E Testing Plan

| Field | Value |
| --- | --- |
| **Author** | Engineering |
| **Date** | 2026-07-14 |
| **Status** | Ready for implementation |
| **Branch context** | Legacy reference = **`master`**; target under test = **`remaster`** SPA admin |
| **Primary surfaces** | `master:core/admin/modules/**`, `master:core/admin/auto-modules/**`, `remaster:spa/`, `remaster:core/inc/bigtree/api/`, `remaster:core/inc/bigtree/services/` |

---

## 1. Purpose

The `remaster` branch replaces the classic PHP admin UI with a React SPA. Persistence still flows through the same MySQL tables and JSON-DB config files, now via REST (`{admin}/api/v1`) and service classes instead of form POSTs to PHP module scripts.

This plan defines how to:

1. **Audit** every user-facing capability on `master`.
2. For each capability, capture **canonical test inputs** and **observable outputs** (DB rows, JSON-DB files, side effects).
3. **Map** those paths onto SPA routes + API endpoints so the same logical input produces the same database outcome.
4. Drive **automated** validation (API-first parity harness + browser E2E for critical UI paths).

**Success criterion:** For every mutative admin action on master that still exists as a product surface on remaster, feeding equivalent data through the SPA/API yields equivalent rows in SQL + JSON-DB (ignoring intentional format deltas listed in §6).

---

## 2. Testing strategy (three layers)

| Layer | What it proves | Primary tool | Priority |
| --- | --- | --- | --- |
| **L1 — Service / API contract** | Request body → service → DB row matches legacy semantics | PHP harness `core/inc/bigtree/api/_test/` + new parity suites | **P0** (do first) |
| **L2 — API smoke / integration** | Real HTTP against bootstrapped install; auth, CRUD happy paths | Extend `core/inc/bigtree/api/_smoke/*.sh` + CI job | **P0** |
| **L3 — Browser E2E** | SPA pages call correct APIs; permissions gate UI; multi-step flows | Playwright (recommended) against packaged SPA at `/admin` | **P1** |

Unit tests in Vitest (SPA) and existing PHP `_test` remain useful for pure logic, but **they do not replace parity**. The regression risk of the rewrite is *semantic drift* between classic `BigTreeAdmin` methods and new services.

### Why API-first, not UI-first

- Master and remaster UIs are not 1:1 (forms vs SPA components, field names may snake_case JSON vs form-urlencoded).
- Database outcome is the contract that matters for content, permissions, and upgrades.
- API routes already encode validation schemas (`body` / `query` / `permission` on each route) — the natural oracle for “correct input.”
- SPA E2E should assert *wiring* (route → form → API → toast/redirect), not re-prove field processing.

### Golden-path rule

For each mutative capability:

```
Fixture DB
  → apply INPUT (master POST shape OR SPA/API JSON shape)
  → snapshot OUTPUT tables / JSON-DB keys that should change
  → reset fixture
  → apply equivalent INPUT via remaster API
  → assert OUTPUT deep-equals (normalized)
```

Where dual-running master is impractical in CI, **service-level parity** against documented expected rows is acceptable if the expected rows were derived from a one-time master audit (record the commit SHA of `master` used for the audit).

---

## 3. Master audit methodology

### 3.1 Inventory sources (authoritative)

| Source on `master` | What it gives you |
| --- | --- |
| `core/admin/_nav-tree.php` | Full product surface + required levels |
| `core/admin/modules/**` | UI pages + POST create/update/delete handlers |
| `core/admin/ajax/**` | Async mutations (tags, 404, order, lock refresh, etc.) |
| `core/admin/auto-modules/**` | Module views/forms/reports runtime |
| `core/inc/bigtree/admin.php` | `create*` / `update*` / `delete*` implementations (true DB writers) |
| `core/setup/base.sql` | SQL schema for assertions |
| JSON-DB under `custom/json-db/` (and core defaults) | Templates, modules, callouts, settings defs, feeds, field-types |

### 3.2 Per-capability audit checklist

For every leaf action (e.g. “Create tag”, “Publish page”, “Reorder modules”):

| Field | Capture |
| --- | --- |
| **ID** | Stable key, e.g. `tags.create` |
| **Master URL** | e.g. `POST /admin/tags/create/` |
| **Permission** | Level 0/1/2 and/or page/module/folder rank |
| **Input shape** | POST field names, file uploads, CSRF |
| **Processing** | Calls into `BigTreeAdmin::*` / field process |
| **DB writes** | Tables + columns + related tables |
| **JSON-DB writes** | File + key path if any |
| **Side effects** | Audit trail, growls, redirects, caches, resource allocation, hooks |
| **Failure modes** | Empty input, duplicate ID, access denied |
| **SPA route** | e.g. `/tags/add` |
| **API route** | e.g. `POST /tags` |
| **SPA client** | e.g. `spa/src/api/endpoints/tags.ts` |
| **Service** | e.g. `TagService::create` |
| **Parity notes** | Intentional deltas (see §6) |

### 3.3 Role matrix (always cross-cut)

| Role | `bigtree_users.level` | Typical master access |
| --- | --- | --- |
| Editor | `0` | Pages/modules per permission grants; no Users/Settings/Tags admin; no Developer |
| Administrator | `1` | Users, Settings values, Tags, 404s, Integrity, Analytics |
| Developer | `2` | Full Developer section + configure + debug |

Every mutative test plan must include at least one **allowed** and one **denied** actor where the master gate is non-trivial.

---

## 4. Feature inventory & path mapping

Legend: **SQL** = MySQL tables; **JSON** = `custom/json-db/*.json` (or install defaults).

### 4.1 Auth & session

| Capability | Master | SPA | API | Writes |
| --- | --- | --- | --- | --- |
| Login | `POST login/process` | `/login` | `POST /auth/login` | `bigtree_refresh_tokens`, login attempts/bans |
| 2FA verify | `login/2fa/*` | `/login` (MFA step) | `POST /auth/2fa` | tokens, clear 2fa login token |
| 2FA enroll | profile / forced setup | Profile + login flow | `GET/POST /auth/2fa/*` | `bigtree_users.2fa_secret` |
| Passkey login | `login/passkey/*` | `/login` | `GET/POST /auth/passkey/*` | challenges, sessions |
| Passkey manage | `users/profile/passkeys/*` | Profile | `GET/POST/DELETE /auth/passkeys*` | `bigtree_user_passkeys` |
| Forgot / reset password | `login/forgot*`, `reset*` | `/login/forgot`, `/login/reset/:token` | `POST /auth/forgot-password`, `reset-password` | `change_password_hash`, password hash |
| Logout | `login/logout` | shell logout | `POST /auth/logout` | revoke refresh token |
| Logout all | developer security | debug/security | `POST /auth/logout-all` | all refresh tokens, `token_version` |
| PHP session bridge (front-end bar) | classic session cookie | post-login | `POST /auth/php-session` | `bigtree_sessions` / PHP session |
| Emulate user | `developer/user-emulator` | `/developer/debug/emulator` | `POST /auth/emulate` | token as other user (dev only) |
| Me / policy | implicit session | boot | `GET /auth/me`, `GET /auth/login-policy` | none |

### 4.2 Dashboard & vitals

| Capability | Master | SPA | API | Writes |
| --- | --- | --- | --- | --- |
| Overview | `dashboard/` | `/dashboard` | `GET /dashboard/summary`, `content-alerts` | none |
| Pending changes list | `dashboard/pending-changes` | `/pending-changes` | `GET /pending-changes` | none |
| Approve / reject change | ajax `dashboard/approve|reject-change` | `/pending-changes/:id` | `POST .../approve`, `.../reject` | target table + `bigtree_pending_changes` delete |
| Messages | `dashboard/messages/*` | `/messages`, `/messages/:id` | `GET/POST /messages*` | `bigtree_messages` |
| 404 active / ignored / 301 | `dashboard/vitals-statistics/404/*` | `/dashboard/404s/*` | `GET/POST/DELETE /404s*` | `bigtree_404s` |
| Create 301 | `404/create-301` | `/dashboard/404s/301/add` | `POST /404s` or redirect action | `bigtree_404s` |
| Import 301 CSV | `404/upload-csv` → process | `/dashboard/404s/301/import` | `POST /404s/import` | bulk `bigtree_404s` |
| Clear dead 404s | `404/clear` | UI action | `POST /404s/clear-dead` | deletes |
| Site integrity | `vitals-statistics/integrity/*` | `/dashboard/integrity` | `GET/POST /dashboard/integrity*` | integrity cache state |
| Analytics view/cache | `vitals-statistics/analytics/*` | `/analytics` | `GET /dashboard/analytics*`, `POST .../cache` | cache rows |

### 4.3 Pages

| Capability | Master | SPA | API | Writes |
| --- | --- | --- | --- | --- |
| Tree list | `pages/`, `view-tree/{id}` | `/pages`, `/pages/:parentId` | `GET /pages?parent=` | none |
| Search | quick-search / pages | pages UI | `GET /pages/search` | none |
| Add (publish) | `POST pages/create` (`ptype=Create & Publish`) | `/pages/add/:parentId` | `POST /pages` `{ publish: true }` | `bigtree_pages`, tags_rel, open_graph, resource_allocation, route_history |
| Add (draft) | `POST pages/create` (else) | same, Save draft | `POST /pages` without publish / non-publisher | `bigtree_pending_changes` |
| Edit publish / draft | `POST pages/update` | `/pages/:id/edit` | `PATCH /pages/{id}` | pages or pending |
| Edit pending draft | draft edit | `/pages/draft/:pcid/edit` | `GET/PATCH /pages/pending/{pcid}` | pending |
| Archive / restore | `pages/archive`, `restore` | page actions | `POST .../archive`, `unarchive` | `archived` flags on subtree |
| Delete | `pages/delete` | page actions | `DELETE /pages/{id}` | page + children effects |
| Duplicate | `pages/duplicate` | action | `POST .../duplicate` | new page row |
| Move | `pages/move` + update | action | `POST .../move` `{ parent }` | parent/path |
| Reorder | ajax `pages/order` | drag-drop | `POST /pages/{parent}/reorder` `{ ids }` | `position` |
| Revisions list/save/delete/restore | `pages/revisions` + ajax | `/pages/:id/edit/revisions` | `GET/POST/DELETE .../revisions*` | `bigtree_page_revisions` |
| SEO rating | ajax `get-seo-score` | editor | `GET .../seo-rating` | none |
| Access levels | `pages/access-levels` | editor (admin) | `GET .../access-levels` | none |
| Locks | ajax `refresh-lock` | editor | `POST /locks`, refresh, `DELETE` | `bigtree_locks` |

### 4.4 Modules (runtime / auto-modules)

| Capability | Master | SPA | API | Writes |
| --- | --- | --- | --- | --- |
| Module list | nav modules | `/modules` | `GET /modules` | none |
| View entries | auto-modules `view.php` + ajax views | `/modules/:route/...` | `GET /modules/{id}/entries` | none |
| Add entry | auto-modules form process | ModuleEntryAdd | `POST /modules/{id}/entries` | module table + pending if editor |
| Edit entry | form process | ModuleEntryEdit | `PATCH /modules/{id}/entries/{eid}` | same |
| Delete | ajax views/delete | UI | `DELETE .../entries/{eid}` | row / pending |
| Reorder / nest | ajax order, set-nest-state | draggable views | `POST .../entries/reorder` | position |
| Archive / approve / feature | ajax views/* | row actions | `POST .../archive|approve|feature` | flags + view cache |
| Reports | auto-modules report | ModuleReport | `GET/POST .../reports/{sid}/*` | none (export) |
| Embed form public | ajax embeddable-form | `/embed/:hash` | `GET/POST /embed-forms/{hash}*` | module table (often pending) |
| Custom PHP actions | module action PHP | ModuleAction / invoke | `POST .../actions/{sid}/invoke` | module-defined |

**Fixture module:** example-site **News** (`route: news`, table `timber_news`, forms/views in `custom/json-db/modules.json`) is the canonical auto-module golden path.

### 4.5 Files / resources

| Capability | Master | SPA | API | Writes |
| --- | --- | --- | --- | --- |
| Browse folders | `files/`, `folder` | `/files`, `/files/folder/:id` | `GET /resource-folders`, `GET /resources/search` | none |
| Create folder | `files/process/folder` | UI | `POST /resource-folders` | `bigtree_resource_folders` |
| Rename folder | update folder | UI | `PATCH /resource-folders/{id}` | name/parent |
| Delete folder | delete folder | UI | `DELETE /resource-folders/{id}` | folder |
| Upload file/image | dropzone / process | UI | `POST /resources/upload` | `bigtree_resources` + filesystem |
| Add video | process video | UI | `POST /resources/video` | resources |
| Edit metadata | edit file | UI | `PATCH /resources/{id}` | metadata |
| Replace / crop | replace, crop | UI | `POST .../replace`, `.../crop` | file + crops |
| Delete resource | delete file | UI | `DELETE /resources/{id}` | resources + file |
| Allocations / usage | implied | UI | `GET/POST/DELETE .../allocations`, usage | `bigtree_resource_allocation` |

### 4.6 Users & profile

| Capability | Master | SPA | API | Writes |
| --- | --- | --- | --- | --- |
| List / search | `users/` | `/users` | `GET /users` | none |
| Create | `POST users/create` | `/users` (add flow) | `POST /users` | `bigtree_users` |
| Edit | `POST users/update` | `/users/:id/edit` | `PATCH /users/{id}` | users |
| Delete | ajax delete | UI | `DELETE /users/{id}` | users |
| Password change | profile / edit | Profile / UserEdit | `POST /users/{id}/password` | password hash |
| Profile self | `users/profile` | `/profile` | `GET/PATCH /users/me` or self id | users |
| Remove 2FA (dev) | security | UserEdit / security | `POST /users/{id}/2fa/remove` | clear secret |

### 4.7 Settings (value edit — admin)

| Capability | Master | SPA | API | Writes |
| --- | --- | --- | --- | --- |
| List | `settings/` | `/settings` | `GET /settings` | none |
| Edit value | `POST settings/update` | `/settings/:id/edit` | `PATCH /settings/{id}` | `bigtree_settings.value` (+ encryption), resource_allocation |

**Note:** Defining setting *schema* (type, locked, encrypted) is Developer → Settings (`POST/PATCH/DELETE /settings` at level 2 for create/delete; metadata in JSON-DB + value row).

### 4.8 Tags

| Capability | Master | SPA | API | Writes |
| --- | --- | --- | --- | --- |
| List / search | `tags/` | `/tags` | `GET /tags`, `/tags/search` | none |
| Create | `POST tags/create` (+ optional merge_tags) | `/tags/add` | `POST /tags` `{ tag }` | `bigtree_tags` |
| Delete | ajax `tags/delete` | UI | `DELETE /tags/{id}` | tags + `bigtree_tags_rel` |
| Merge | `POST tags/merge-process` | `/tags/merge` | `POST /tags/merge` `{ into, from[] }` | retarget rel, delete sources, recompute usage |
| Inline create from forms | ajax `tags/create-tag` | TagInput | `POST /tags` (level 0) | tags |

### 4.9 Developer — create/configure surfaces

| Capability | Master | SPA | API | Writes |
| --- | --- | --- | --- | --- |
| Templates CRUD + reorder | `developer/templates/*` | `/developer/templates/*` | `GET/POST/PATCH/DELETE /templates`, `POST /templates/reorder` | **JSON** `templates.json` |
| Callouts CRUD | `developer/callouts/*` | `/developer/callouts/*` | `/callouts*` | **JSON** `callouts.json` |
| Callout groups | `developer/callouts/groups/*` | `/developer/callout-groups/*` | `/callout-groups*` | **JSON** `callout-groups.json` |
| Field types CRUD | `developer/field-types/*` | `/developer/field-types/*` | `/field-types*` | **JSON** `field-types.json` + files under `custom/admin/field-types/` |
| Feeds CRUD | `developer/feeds/*` | `/developer/feeds/*` | `/feeds*` | **JSON** `feeds.json` |
| Settings definitions | `developer/settings/*` | `/developer/settings/*` | `POST/PATCH/DELETE /settings` (level 2) | **JSON** `settings.json` + `bigtree_settings` |
| Modules designer CRUD | `developer/modules/*` | `/developer/modules/*` | `/modules*` subresources | **JSON** `modules.json` + optional SQL table create (scaffold) |
| Module groups | `developer/modules/groups/*` | `/developer/module-groups/*` | `/module-groups*` | **JSON** `module-groups.json` |
| Extensions install/build/delete | `developer/extensions/*` | `/developer/extensions/*` | `/extensions*` | **JSON** `extensions.json` + filesystem |
| Configure email/geo/cloud/payment/analytics/services/media/file-metadata | `developer/{email,geocoding,...}` | `/developer/configure/*` | `GET/PUT /system/configure/*` | encrypted settings / config |
| Configure AI | n/a on old master (new) | `/developer/configure/ai` | `GET/PUT /system/configure/ai` | settings |
| Security policy | `developer/security` | `/developer/debug/security` | `GET/PATCH /system/security-policy` | security policy setting |
| Audit trail | `developer/audit` | `/developer/debug/audit` | `GET /audit` | none (read) |
| Status / upgrade / migrations | `developer/status`, `upgrade/*` | debug + `/developer/migrations` | `/system/status`, `/system/upgrade/*` | migrations ledger |
| Backups | (varies) | `/developer/backups` | `POST/GET/DELETE /system/backup*` | backup files + meta |
| User emulator | `developer/user-emulator` | `/developer/debug/emulator` | `POST /auth/emulate` | session/token |

### 4.10 Cross-cutting

| Capability | Master | SPA | API | Writes |
| --- | --- | --- | --- | --- |
| Global search | ajax / search | shell search | `GET /search`, `POST /search/ai` | none |
| Image process/crop | various form flows | field renderer | `POST /images/*` | files + resources |
| DB introspection (designer) | ajax load-table-columns | designer UI | `GET /db/tables*` | none |
| OpenAPI | n/a | n/a | `GET /openapi.json` | none |

---

## 5. Detailed test plans (inputs → outputs → SPA mapping)

Each plan below is **self-contained**. Fixture names use a recommended seed set (§7).

### 5.1 Auth — `auth.login`

| | Master | Remaster |
| --- | --- | --- |
| Path | `POST /admin/login/process/` form `user`, `password` | SPA `/login` → `POST /auth/login` JSON |
| **Input (happy)** | email of level-2 seed user; valid password; `remember` optional | `{ "email": "dev@example.com", "password": "…", "remember": true }` |
| **Output (happy)** | PHP session cookie; redirect dashboard | `200` `{ access_token, refresh_token, user }`; row in `bigtree_refresh_tokens` |
| **Input (fail)** | wrong password | same |
| **Output (fail)** | redisplay login; login attempt row | `401`; `bigtree_login_attempts` increment; ban after policy threshold |
| **DB assert** | no user password change; attempts/bans tables | same |
| **E2E** | Login form → land on `/dashboard`; unauthorized `/developer` redirects or AccessDenied for editor token |

### 5.2 Tags — `tags.create`

| | Master | Remaster |
| --- | --- | --- |
| Path | `POST /admin/tags/create/` | SPA `/tags/add` → `POST /tags` |
| **Input** | `tag=Hello World`, optional `merge_tags[]` | `{ "tag": "Hello World" }` |
| **Output row** | `bigtree_tags`: `tag` lowercased/normalized per admin rules, `metaphone`, unique `route`, `usage_count=0` | Same via `TagService::normalize` (alphanumeric only, lowercased): **`helloworld`** — **verify parity of normalization** (§6.1) |
| **Duplicate** | Master: growl error, no second row | API: returns existing row (idempotent) — **intentional UX delta**; assert still **one** row |
| **Empty** | redirect error | `400 empty_tag` |
| **Permission** | Tags admin UI level 1; ajax create-tag level 0 | `POST /tags` is **level 0** (inline editors); SPA Tags section UI level 1 |
| **E2E** | Create from `/tags/add`; also create from page TagInput as editor |

### 5.3 Tags — `tags.merge`

| | Master | Remaster |
| --- | --- | --- |
| Path | `POST /admin/tags/merge-process/` `tag_id`, `merge_to` | SPA `/tags/merge` → `POST /tags/merge` |
| **Input** | merge tag A into B | `{ "into": B_id, "from": [A_id] }` |
| **Output** | All `bigtree_tags_rel.tag=A` → `B`; A deleted; B.usage_count recomputed | same |
| **Self-merge** | growl error | `400 invalid_merge` |
| **Permission** | level 1 | level 1 |

### 5.4 Users — `users.create`

| | Master | Remaster |
| --- | --- | --- |
| Path | `POST /admin/users/create/` | SPA Users → `POST /users` |
| **Input** | `email`, `name`, `company`, `level`, `password`, `timezone`, `daily_digest`, permission tree fields | JSON matching route body schema (`email`, `name`, `company`, `level` 0\|1\|2, `password` min 8, `timezone`, `daily_digest`, `alerts`, `permissions`) |
| **Output** | new `bigtree_users` row; password hashed; permissions serialized; redirect edit | `201` user entity; same columns |
| **Fail: bad password** | session error `password` | `422` validation / policy |
| **Fail: duplicate email** | session error `email` | `409` or validation error |
| **Permission** | level ≥ 1; cannot create higher than self (legacy rule — **assert both**) | same in `UserService` |
| **Audit** | trail entry | route `audit: created` → `bigtree_audit_trail` |

### 5.5 Users — `users.update` / `users.password` / `users.delete`

| ID | Input | Expected DB | API |
| --- | --- | --- | --- |
| `users.update` | change name, company, level, permissions | updated columns only; no password change | `PATCH /users/{id}` |
| `users.password` | `current_password` (if self), `new_password` | new hash; optionally bump `token_version` | `POST /users/{id}/password` |
| `users.delete` | target id ≠ self | row removed; sessions/tokens cleaned per service | `DELETE /users/{id}` |

### 5.6 Settings (value) — `settings.update_value`

| | Master | Remaster |
| --- | --- | --- |
| Path | `POST /admin/settings/update/` | SPA `/settings/:id/edit` → `PATCH /settings/{id}` |
| **Input** | setting `id`, field-processed `value` (may include files) | JSON value shape after SPA field renderer (same processed structure as `FieldProcessingService`) |
| **Output** | `bigtree_settings.value` updated (AES if encrypted); `allocateResources("bigtree_settings", id)` | same tables |
| **Gates** | system settings denied; locked requires level 2 | same in `SettingService` |
| **Fixture** | seed a non-system text setting `test_site_notice` | create via developer API in setup |

### 5.7 Pages — `pages.create_publish` / `pages.create_draft`

| | Master | Remaster |
| --- | --- | --- |
| Path | `POST /admin/pages/create/` | SPA `/pages/add/:parentId` → `POST /pages` |
| **Publisher input** | parent, nav_title, title, route, in_nav, template, resources…, `ptype=Create & Publish` | same fields + `"publish": true` |
| **Publisher output** | live `bigtree_pages` row; path composed; position set; tags/open_graph; resource_allocation; optional hooks | `201` page; same tables; `mode: published` |
| **Editor input** | same without publish access / non-publish button | omit `publish` or user lacks publisher rank |
| **Editor output** | pending id `p{n}`; row in `bigtree_pending_changes` (`type` new page, `pending_page_parent`) | pending change entity; **no** live page until approve |
| **Access denied** | no e/p on parent | `403` |
| **External / redirect_lower** | template forced empty or `!` | service must mirror template coercion |

**Follow-on plans:** `pages.update_*`, `pages.archive`, `pages.duplicate`, `pages.move`, `pages.reorder`, `pages.revisions_*`, `pages.delete` — each with publisher vs editor split and permission matrix on fixture tree:

```
Home (id=0 virtual root)
 └─ About (publisher grant for editor fixture optional)
     └─ Team (child for move/reorder)
```

### 5.8 Pending changes — `pending.approve` / `pending.reject`

| ID | Input | Master | API | Output |
| --- | --- | --- | --- | --- |
| approve | pending change id for page/module edit | ajax approve | `POST /pending-changes/{id}/approve` | target row updated; pending deleted; audit |
| reject | same | ajax reject | `POST .../reject` | pending deleted; live unchanged |

Cross-link: create draft as editor → login publisher → approve → assert live row equals intended resources.

### 5.9 Modules (News fixture) — `modules.news.*`

| ID | Input | Expected | API |
| --- | --- | --- | --- |
| `list` | module id/route news | entries from `timber_news` + view cache columns | `GET /modules/{id}/entries` |
| `create_publish` | form fields: date, title, … (from form JSON) | insert `timber_news`; view cache refresh | `POST /modules/{id}/entries` |
| `create_draft` | editor without p | `bigtree_pending_changes` | same POST |
| `update` / `delete` | eid | row change/delete | PATCH/DELETE |
| `reorder` | ordered ids | `position` column | `POST .../reorder` |
| `archive|approve|feature` | eid | flag columns + cache | POST actions |
| `report` | filters | CSV/text result; no DB write | report endpoints |
| `embed_submit` | public hash + fields | pending or live per embed settings | `POST /embed-forms/{hash}/submit` |

### 5.10 Files — `resources.upload` / folders

| ID | Input | Output | API |
| --- | --- | --- | --- |
| `folder.create` | `{ parent, name }` | `bigtree_resource_folders` | `POST /resource-folders` |
| `upload.file` | multipart file + folder | `bigtree_resources` type file; file on disk under storage root | `POST /resources/upload` |
| `upload.image` | image bytes | is_image, width/height, thumbs/crops metadata | same |
| `metadata` | name, metadata fields | updated row | `PATCH /resources/{id}` |
| `delete` | id | row gone; file removed if unallocated | `DELETE` |
| `permission` | folder with `n` for editor | 403 | same |

### 5.11 Developer — templates `dev.templates.create`

| | Master | Remaster |
| --- | --- | --- |
| Path | `POST developer/templates/create` | SPA `/developer/templates/add` → `POST /templates` |
| **Input** | `id`, `name`, `routed`, `level`, `module`, `resources[]`, `hooks` | JSON equivalent (see OpenAPI / TemplateService) |
| **Output** | new key in templates JSON store; invalid/duplicate id → error session | `201`; templates.json contains id; duplicate → 409 |
| **Permission** | level 2 | level 2 |

Mirror plans for: callouts, callout-groups, field-types, feeds, settings definitions, module groups, full module designer (module + form + view + action + report + embed).

### 5.12 Developer — module designer golden path `dev.modules.scaffold`

End-to-end designer path (highest regression value after pages):

1. `POST /module-groups` — group “QA Group”
2. `POST /modules` or `POST /modules/scaffold` — module “QA Items”, table `qa_items`, group set
3. Assert **JSON** module entry + **SQL** table exists (if scaffold creates table)
4. `POST /modules/{id}/forms` — fields: text title, checkbox featured
5. `POST /modules/{id}/views` — searchable view on title
6. `POST /modules/{id}/actions` — view + add + edit wired to form/view
7. Runtime: create entry via auto-module API; list via view; edit; delete
8. Cleanup: delete module; assert JSON gone; decide table drop policy (document actual service behavior)

### 5.13 Dashboard 404s — `404s.redirect`

| Input | Output | API |
| --- | --- | --- |
| broken URL row exists; set redirect | `redirect_url` set; requests preserved | `POST /404s/{id}/redirect` |
| ignore | `ignored` flag | `POST .../ignore` |
| import CSV of from,to | N rows | `POST /404s/import` |
| clear dead | rows without hits removed per service rules | `POST /404s/clear-dead` |

### 5.14 Configure — `configure.email` (representative)

| Input | Output | API |
| --- | --- | --- |
| SMTP host, port, user, from address | encrypted/internal settings updated; `GET` returns redacted secrets | `PUT /system/configure/email` |

Apply same pattern to geocoding, cloud storage, payment gateway, analytics credentials, media presets, file metadata, AI, security policy.

### 5.15 Messages — `messages.create`

| Input | Output | API |
| --- | --- | --- |
| subject, message, recipients[] | `bigtree_messages` row; unread counts for recipients | `POST /messages` |
| reply | `response_to` set | POST with thread semantics per service |

### 5.16 Locks — `locks.lifecycle`

| Steps | Assert |
| --- | --- |
| `POST /locks` on page edit | lock row for user/table/item |
| second user `POST` same item | conflict / existing lock info |
| `POST refresh` | `last_accessed` updates |
| `DELETE` | lock gone |

---

## 6. Known / intentional deltas (do not fail parity on these)

Documented so tests normalize before compare:

| Area | Master | Remaster | Test handling |
| --- | --- | --- | --- |
| Transport | form-urlencoded + CSRF | JSON + JWT Bearer | Map field names; ignore CSRF |
| Success UX | growl + redirect | JSON + SPA toast/navigation | Ignore; assert DB only |
| Tag create duplicate | error | return existing | Assert single row, not HTTP code parity |
| Tag create permission | admin UI level 1 | API create level 0 | Separate UI gate tests from API |
| Tag normalization | `createTag` legacy | `preg_replace` alnum + lower | **Must verify** strings match; if not, either fix service or document as product change |
| Empty permission string | `getAccessLevel` → `""` | service → `"n"` | Use *capability questions* (can edit?) — already in `LegacyParityTest` |
| Auth session | PHP session primary | JWT + optional php-session bridge | Assert API tokens; bar tests need php-session |
| Module designer multi-step redirects | many intermediate PHP pages | single SPA editor | Assert final JSON-DB, not intermediate URLs |
| Image crop multi-step | session crop_key redirects | API crop endpoints | Assert final resource crops JSON |
| AI features | absent on master | new SPA/API | Out of master parity scope; separate suite |

Any new delta discovered during audit must be added here with a product decision (fix vs accept).

---

## 7. Fixtures & environment

### 7.1 Database

- Base: `core/setup/base.sql` (CI already loads this).
- Optional content: `core/setup/example-site.sql` for pages + News module realism.
- Seed users:

| Email | Level | Password (CI) | Purpose |
| --- | --- | --- | --- |
| `dev@example.com` | 2 | fixed in CI secrets/env | full access |
| `admin@example.com` | 1 | fixed | admin without developer |
| `editor@example.com` | 0 | fixed | grants on specific page + news module only |

### 7.2 Config

Smoke README requirements still apply:

- `settings_key`
- `api.jwt_secret`
- `domain` / `www_root` / `admin_root`
- `cache/composer-check.flag`

### 7.3 Filesystem

- Writable storage for uploads (`site/files/resources` or configured cloud — use **local** in CI).
- Isolated `custom/json-db` copy per test run (copy from fixture, never mutate shared developer json-db without restore).

### 7.4 Snapshot helpers

Implement shared assertion helpers:

```text
assertRow('bigtree_tags', ['id' => $id], ['tag' => '…', 'usage_count' => 0])
assertJsonDbKey('templates.json', $id, subset)
assertAudit('bigtree_tags', 'created', $id)
assertNoRow(...)
normalizePageResources($resources) // strip volatile upload paths timestamps if needed
```

---

## 8. Automation implementation plan

### Phase 0 — Audit spreadsheet (1–2 days)

- Script: walk `master:core/admin/modules` file list + `_nav-tree.php` → generate checklist CSV/Markdown with columns from §3.2.
- Manually fill input/output for **P0 domains** first: Auth, Pages, Tags, Users, Settings values, Auto-module News, Pending changes.
- Mark each row: Implemented in SPA (Y/N), API complete (Y/N), Test status.

### Phase 1 — L1 PHP parity suites (P0)

Location: `core/inc/bigtree/api/_test/parity/` (new) or extend existing tests.

Suggested files:

| File | Covers |
| --- | --- |
| `ParityTagsTest.php` | create, delete, merge, usage_count |
| `ParityUsersTest.php` | create, update, password, delete, permission caps |
| `ParityPagesTest.php` | publish vs draft, update, archive, move, reorder, revisions |
| `ParityPendingTest.php` | approve/reject page + module |
| `ParitySettingsTest.php` | value update, locked/system gates |
| `ParityAutoModuleNewsTest.php` | CRUD, flags, reorder |
| `ParityResourcesTest.php` | folder + upload + delete (local storage) |
| `ParityDeveloperJsonTest.php` | template/callout/feed/setting definition CRUD on temp json-db |
| `ParityMessagesTest.php` | create/read |
| `ParityFourOhFoursTest.php` | redirect/ignore/import |

Run via existing `core/inc/bigtree/api/_test/run.php` in CI `php` job.

**Optional dual-oracle:** if `BigTreeAdmin` is loadable, call legacy `createTag` / `createUser` etc. and compare row to service path (strongest parity). Prefer this for pure data methods still present on the admin class.

### Phase 2 — L2 HTTP smoke expansion

Extend `_smoke/`:

| Script | Paths |
| --- | --- |
| `auth.sh` | already solid — keep as gate |
| `tags.sh` | create, list, merge, delete |
| `pages.sh` | create publish, patch, reorder, delete |
| `users.sh` | already partial — complete CRUD |
| `modules-news.sh` | entries CRUD |
| `settings.sh` | patch value |
| `developer-templates.sh` | create/delete template (restore json-db) |
| `resources.sh` | already present — ensure CI runs it |
| `system-backup.sh` | already present |

Wire a **matrix job** or sequential smoke after server boot (pattern in `ci.yml` auth smoke).

### Phase 3 — L3 Playwright E2E (P1)

Stack recommendation:

- Playwright in `spa/e2e/` (or repo-root `e2e/`).
- Boot: PHP built-in server + `ci-router.php` + packaged SPA (same as cutover serve path).
- Auth: API login to seed storage **or** UI login once per project.

**Critical UI journeys (minimum):**

1. Login → dashboard widgets render.
2. Pages: create child → edit content field → publish → front-end shows content (optional front-end assert).
3. Tags: add + merge UI.
4. Users: create editor → logout → login as editor → denied developer.
5. Settings: edit text setting → reload shows value.
6. Modules: News add → appears in view → edit → delete.
7. Files: upload image → appears in folder.
8. Developer: create template → appears in list → delete.
9. Pending: editor draft page → admin approves → page live.
10. 404: create 301 → list shows redirect.

Selectors: prefer `data-testid` attributes added intentionally on primary actions (document convention in `spa/README.md`).

### Phase 4 — Continuous enforcement

| Gate | When |
| --- | --- |
| PHP parity suite | every PR (CI `php` job) |
| Smoke tags/pages/auth | every PR |
| Playwright critical | nightly or PR if runtime acceptable |
| Full smoke matrix | nightly / pre-release |
| Master re-audit | when pulling security fixes from master, or release candidate |

---

## 9. Priority order (execution backlog)

| Priority | Domain | Why |
| --- | --- | --- |
| P0 | Auth + permissions | Everything else depends on it |
| P0 | Pages + pending + revisions | Core CMS; complex publisher/editor split |
| P0 | Auto-modules (News) | Primary content model for most sites |
| P0 | Tags + Users + Settings values | High admin traffic; simple DB assertions |
| P1 | Files/resources + allocations | Binary + permission tree |
| P1 | Developer JSON-DB CRUD (templates, callouts, modules, settings defs) | Defines site structure |
| P1 | 404s + messages + locks | Secondary but user-visible |
| P2 | Configure/* providers | Often mock credentials; still assert persistence shape |
| P2 | Extensions install/build | Heavy filesystem; sample zip fixture |
| P2 | Integrity, analytics, backups, upgrade | Operational; partial mocks |
| P3 | AI chat/proposals | New surface; no master baseline |
| P3 | Custom `render: server` module actions | Policy surface; site-specific |

---

## 10. Coverage matrix template (use for tracking)

Copy into a working checklist (or generate in Phase 0):

```markdown
| ID | Master path | SPA route | API | Tables | Roles tested | L1 | L2 | L3 | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| tags.create | POST tags/create | /tags/add | POST /tags | bigtree_tags | 0,1 | ☐ | ☐ | ☐ | |
| pages.create_publish | POST pages/create | /pages/add/:p | POST /pages | bigtree_pages, … | 0,1,2 | ☐ | ☐ | ☐ | |
```

Target: **100% P0 L1+L2**; **≥80% P1 L1**; **L3** for §8 Phase 3 journeys only.

---

## 11. How to run (target end state)

```bash
# PHP unit + parity
php core/inc/bigtree/api/_test/run.php

# Smoke (server already up via ci-router)
export BIGTREE_API_BASE=http://127.0.0.1:8080/admin/api/v1
export BIGTREE_TEST_EMAIL=dev@example.com
export BIGTREE_TEST_PASSWORD='…'
core/inc/bigtree/api/_smoke/auth.sh
core/inc/bigtree/api/_smoke/tags.sh
# …

# SPA unit
cd spa && npm test

# E2E
cd spa && npx playwright test
```

---

## 12. Deliverables checklist

- [ ] Phase 0 audit artifact committed under `plans/parity-audit/` (or spreadsheet export)
- [ ] L1 parity tests for all P0 domains green in CI
- [ ] L2 smoke scripts for P0 domains in CI
- [ ] Playwright skeleton + 10 critical journeys
- [ ] §6 deltas reviewed and signed off (product)
- [ ] Release candidate: full matrix run against example-site DB; sign-off document

---

## 13. Appendix A — Master module file index (audit seed)

Top-level master areas under `core/admin/modules/`:

- `dashboard/` — overview, messages, pending-changes, vitals (404, analytics, integrity)
- `pages/` — tree, CRUD, revisions, move, duplicate, archive, crops
- `files/` — folders, upload, crop, delete
- `users/` — CRUD, profile, passkeys
- `settings/` — value edit + crops
- `tags/` — list, add, merge
- `login/` — login, 2fa, passkey, forgot/reset, cors
- `developer/` — templates, modules (+ forms/views/actions/reports/embeds/groups/designer), callouts, field-types, feeds, settings, extensions, configure (analytics, cloud, payment, geocoding, email, services, media, files), security, audit, status, upgrade, user-emulator

Auto-modules: `form(s)`, `view(s)`, `report(s)`, embeddable forms under `ajax/auto-modules/`.

## 14. Appendix B — Remaster API route inventory (complete)

See route files under `core/inc/bigtree/api/routes/`. Full method+path list extracted 2026-07-14 includes ~200 endpoints across: `404s`, `ai`, `audit`, `auth`, `auto-modules`, `callouts`, `dashboard`, `db`, `extensions`, `feeds`, `field-types`, `images`, `locks`, `messages`, `modules`, `openapi`, `pages`, `pending-changes`, `resources`, `search`, `settings`, `system`, `tags`, `templates`, `users`.

Every mutative endpoint in that list that maps to a master capability should have an L1 or L2 test; endpoints with no master equivalent (AI, some system/configure AI) get product tests only.

## 15. Appendix C — SPA route inventory (complete)

From `spa/src/routes/index.tsx`:

- Public: `/login`, `/login/forgot`, `/login/reset/:token`, `/embed/:hash`
- App shell: `/dashboard`, `/dashboard/404s/*`, `/dashboard/integrity`, `/analytics`
- `/pages/*`, `/modules/*`, `/files/*`, `/users/*`, `/profile`, `/settings/*`, `/tags/*`, `/messages/*`, `/pending-changes/*`
- `/developer/*` — templates, callouts, callout-groups, field-types, feeds, settings, modules, module-groups, configure/*, debug/*, extensions, backups, migrations

Any master nav item without a SPA route is either **dropped by product decision** (document in cutover design) or a **gap** — flag during Phase 0.

---

## 16. Immediate next steps

1. Approve this plan and §6 product deltas (especially tag normalization + duplicate create behavior).
2. Run Phase 0 generator against `master` file tree; fill P0 I/O rows with real POST field dumps from master forms (`_form.php` / `_form-content.php`).
3. Implement `ParityTagsTest` + `ParityPagesTest` as pattern-setting suites.
4. Add `tags.sh` + `pages.sh` smoke; hang on CI.
5. Scaffold Playwright with login + pages create journey.

Once those five land, remaining domains are copy-paste of the same input/output/mapping pattern.
