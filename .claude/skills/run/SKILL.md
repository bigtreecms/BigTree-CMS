---
name: run
description: Launch BigTree CMS (MySQL, PHP API, admin SPA) and drive it to see a change working. Use when asked to run, start, or screenshot the app or the admin, or to confirm a change works in the real app rather than only in tests.
---

# Run BigTree

## 1. Bring the stack up

```bash
scripts/dev-up.sh            # API + packaged SPA on :8080
scripts/dev-up.sh --vite     # also Vite on :5173, for SPA source changes
```

The script is idempotent, so run it even if you think the stack is already up. If it
says MySQL or `spa/node_modules` is missing, run the prerequisites in
`docs/cloud-sessions.md` first (`apt-get install -y mysql-server-8.0 jq`, then
`cd spa && npm ci`).

If it refuses because `custom/environment.php` belongs to a real site, don't work
around it. Use a `git worktree` in a scratch directory and run it there.

Pick the URL to check:

- **SPA source changed:** use Vite at `http://127.0.0.1:5173/` (start with `--vite`).
  Alternatively, run `cd spa && npm run build:package` and use `:8080/admin/`.
- **PHP or API only:** use `http://127.0.0.1:8080/admin/` or the API at
  `http://127.0.0.1:8080/admin/api/v1`.

The login is `dev@local.test` / `DevPassword-2026!`, a developer-level account.

## 2. Look at it

Write a short Playwright script in `spa/`, so it resolves `@playwright/test`. Delete
the script afterwards. Chromium is already installed, so don't run
`npx playwright install` in a cloud session.

```js
import { chromium } from "@playwright/test";

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
await page.goto("http://127.0.0.1:5173/login");
await page.getByLabel(/email/i).fill("dev@local.test");
await page.getByLabel(/password/i).fill("DevPassword-2026!");
await page.getByTestId("login-submit").click();
await page.waitForURL(/dashboard/);
await page.goto("http://127.0.0.1:5173/pages"); // the screen you changed
await page.screenshot({ path: "/tmp/bigtree-shot.png" });
await browser.close();
```

On the packaged SPA, the paths sit under `/admin/`, for example
`http://127.0.0.1:8080/admin/login`. Open the screenshot and check it before you
report what it shows.

## 3. Report

Say which URL and port you used, what you checked, and what you saw. If the server
misbehaves, look in `/tmp/bigtree-php.log` and `/tmp/bigtree-vite.log`. Stop the
servers with `scripts/dev-up.sh --stop` when you're done, unless the user wants them
left running.
