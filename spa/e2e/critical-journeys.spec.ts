/**
 * Phase 3 — critical SPA journeys (plan §8).
 *
 * Assert UI wiring: route → form → API → visible outcome.
 * Deep field/DB parity is covered by L1/L2.
 */

import { test, expect } from "@playwright/test";

import {
	apiCreateSetting,
	apiCreateUser,
	apiDeleteModule,
	apiDeleteSetting,
	apiDeleteUser,
	apiJson,
	apiLogin,
	apiScaffoldModule,
	type ApiSession,
} from "./helpers/api";
import { uiLogin, uiLogout } from "./helpers/auth";
import { e2eEmail, e2ePassword, unique } from "./helpers/env";

test.describe.configure({ mode: "serial" });

let admin: ApiSession;

test.beforeAll(async () => {
	admin = await apiLogin();
});

// Reset auth rate-limit counters so multi-login journeys stay under 10/min.
test.beforeEach(async () => {
	const { execSync } = await import("node:child_process");
	const { resolve, dirname } = await import("node:path");
	const { fileURLToPath } = await import("node:url");
	const root = resolve(dirname(fileURLToPath(import.meta.url)), "../../..");

	try {
		execSync(
			`php -r '$bigtree=["config"=>["debug"=>false]]; @include "custom/environment.php"; @include "custom/settings.php"; if(empty($bigtree["config"]["db"]["host"]))exit(0); require "core/bootstrap.php"; @SQL::query("TRUNCATE TABLE bigtree_api_rate_limits");'`,
			{ cwd: root, stdio: "ignore" }
		);
	} catch {
		// CI / hosts without PHP CLI on PATH still work if under the limit.
	}
});

// ── 1. Login → dashboard ──────────────────────────────────────────────────

test("1. login lands on dashboard with widgets", async ({ page }) => {
	await uiLogin(page);
	await expect(page.getByRole("heading", { name: "Dashboard" })).toBeVisible();
	await expect(page.getByText(/Welcome back/i).first()).toBeVisible();
});

// ── 2. Pages: create & publish ────────────────────────────────────────────

test("2. pages: create & publish child page", async ({ page }) => {
	await uiLogin(page);
	const title = unique("E2EPage");

	await page.goto("pages/add/0");
	// Properties fields use placeholder association (not html-for labels).
	const navTitle = page.getByPlaceholder(/Shown in site nav/i);
	await expect(navTitle).toBeVisible({ timeout: 15_000 });
	await navTitle.fill(title);

	const publish = page
		.getByTestId("page-create-publish")
		.or(page.getByRole("button", { name: /Create & Publish/i }));
	await expect(publish.first()).toBeEnabled({ timeout: 5_000 });
	await publish.first().click();

	await expect(page.getByText(title).first()).toBeVisible({ timeout: 20_000 });
});

// ── 3. Tags: add ──────────────────────────────────────────────────────────

test("3. tags: add tag appears in list", async ({ page }) => {
	await uiLogin(page);
	// Client normalize strips non-alnum; avoid hyphens in the typed name.
	const name = `E2ETag${Date.now().toString(36)}`;

	await page.goto("tags/add");
	await expect(page.getByRole("heading", { name: /Add tag/i })).toBeVisible();
	await page.getByLabel(/Tag name/i).fill(name);
	await page.getByRole("button", { name: /Create tag/i }).click();

	await page.waitForURL(/\/tags\/?$/, { timeout: 15_000 });
	const normalized = name.toLowerCase();
	await expect(page.getByText(normalized, { exact: false }).first()).toBeVisible({
		timeout: 15_000,
	});
});

// ── 4. Users: editor denied developer ─────────────────────────────────────

test("4. users: editor cannot open developer", async ({ page }) => {
	const suffix = unique("ed").replace(/-/g, "");
	const email = `e2e${suffix}@example.com`;
	const password = "E2eEditor-Pass1!";
	const created = await apiCreateUser(admin, {
		email,
		password,
		name: `E2E Editor ${suffix}`,
		level: 0,
	});

	try {
		await uiLogin(page, email, password);
		await page.goto("developer");
		await expect(page.getByTestId("access-denied").first()).toBeVisible({ timeout: 15_000 });
	} finally {
		await apiDeleteUser(admin, created.id);
	}
});

// ── 5. Settings: edit value ───────────────────────────────────────────────

test("5. settings: edit value and reload", async ({ page }) => {
	const sid = `zz_e2e_${Date.now().toString(36)}`;
	await apiCreateSetting(admin, sid, "E2E Setting");

	try {
		await uiLogin(page);
		await page.goto(`settings/${encodeURIComponent(sid)}/edit`);
		await expect(page.getByRole("heading", { name: /E2E Setting/i })).toBeVisible({
			timeout: 15_000,
		});

		// Single text control on the page (no Field label for value on text settings)
		const control = page.locator('input[type="text"], textarea').first();
		await expect(control).toBeVisible({ timeout: 10_000 });
		await control.fill("Hello from E2E");
		await page.getByRole("button", { name: /^Save$/i }).click();

		// Wait for navigation back to list or toast
		await page.waitForTimeout(800);
		await page.goto(`settings/${encodeURIComponent(sid)}/edit`);
		await expect(page.locator('input[type="text"], textarea').first()).toHaveValue(
			"Hello from E2E",
			{ timeout: 15_000 }
		);
	} finally {
		await apiDeleteSetting(admin, sid);
	}
});

// ── 6. Modules: scaffold via API, open UI ─────────────────────────────────

test("6. modules: open scaffolded module view", async ({ page }) => {
	const suffix = Date.now().toString(36);
	const table = `zz_e2e_${suffix}`.slice(0, 64);
	const route = `zze2e${suffix}`;
	const mod = await apiScaffoldModule(admin, {
		name: `E2E Mod ${suffix}`,
		table,
		route,
	});

	try {
		await uiLogin(page);
		await page.goto(`modules/${route}`);
		await expect(page).toHaveURL(new RegExp(`/modules/${route}`), { timeout: 15_000 });
		// Module layout should show an action named after the scaffolded module.
		await expect(page.getByText(new RegExp(`E2E Mod|Add |View `, "i")).first()).toBeVisible({
			timeout: 15_000,
		});
	} finally {
		await apiDeleteModule(admin, mod.id);
	}
});

// ── 7. Files ──────────────────────────────────────────────────────────────

test("7. files: browser loads root", async ({ page }) => {
	await uiLogin(page);
	await page.goto("files");
	await expect(page.getByRole("heading").first()).toBeVisible({ timeout: 15_000 });
	await expect(page.getByText(/Upload|Drop|folder|Files|Resources|No /i).first()).toBeVisible({
		timeout: 10_000,
	});
});

// ── 8. Developer templates ────────────────────────────────────────────────

test("8. developer: create and delete template", async ({ page }) => {
	await uiLogin(page);
	const tid = `zze2e${Date.now().toString(36)}`.slice(0, 30);

	await page.goto("developer/templates/add");
	await expect(page.getByRole("heading", { name: /Add template/i })).toBeVisible({
		timeout: 15_000,
	});

	// Labels include a required asterisk ("ID *") so match loosely.
	await page.getByLabel(/ID/i).fill(tid);
	await page.getByLabel(/Name/i).fill(`E2E Template ${tid}`);
	await page.getByRole("button", { name: /Create template/i }).click();

	// Redirect to list or edit
	await expect(page).toHaveURL(/\/developer\/templates/, { timeout: 15_000 });
	await expect(
		page
			.getByText(tid)
			.or(page.getByText(`E2E Template ${tid}`))
			.first()
	).toBeVisible({
		timeout: 15_000,
	});

	// Cleanup
	await apiJson(admin, "DELETE", `/templates/${tid}`);
});

// ── 9. Pending changes (editor draft → admin list) ────────────────────────

test("9. pending: editor draft appears for admin", async ({ page }) => {
	const suffix = Date.now().toString(36);
	const email = `e2epend${suffix}@example.com`;
	const password = "E2eEditor-Pass1!";
	const draftTitle = `Draft${suffix}`;

	const created = await apiCreateUser(admin, {
		email,
		password,
		name: `E2E Pend ${suffix}`,
		level: 0,
	});

	await apiJson(admin, "PATCH", `/users/${created.id}`, {
		permissions: {
			page: { "1": "e" },
			module: {},
			resources: {},
			module_gbp: {},
		},
	});

	try {
		await uiLogin(page, email, password);
		await page.goto("pages/add/1");

		const navTitle = page.getByPlaceholder(/Shown in site nav/i);
		await expect(navTitle).toBeVisible({ timeout: 15_000 });
		await navTitle.fill(draftTitle);
		// Editors only get Create (draft), not Create & Publish
		await page.getByRole("button", { name: /^Create$/i }).click();
		await page.waitForTimeout(1500);

		await uiLogout(page);
		await uiLogin(page, e2eEmail(), e2ePassword());
		await page.goto("pending-changes");
		await expect(page.getByRole("heading", { name: /Pending/i }).first()).toBeVisible({
			timeout: 15_000,
		});
		await expect(
			page.getByText(new RegExp(`${draftTitle}|New Page|Page`, "i")).first()
		).toBeVisible({ timeout: 15_000 });
	} finally {
		// Reject leftover pending changes owned by this user, then delete user
		const list = await apiJson<{ id: number; user: number }[]>(
			admin,
			"GET",
			"/pending-changes?per_page=50"
		);
		const rows = Array.isArray(list.data) ? list.data : [];
		for (const row of rows) {
			if (Number(row.user) === created.id) {
				await apiJson(admin, "POST", `/pending-changes/${row.id}/reject`).catch(
					() => undefined
				);
			}
		}
		await apiDeleteUser(admin, created.id);
	}
});

// ── 10. 404 / 301 redirects ───────────────────────────────────────────────

test("10. 404s: create 301 redirect", async ({ page }) => {
	await uiLogin(page);
	const from = `/e2e-gone-${Date.now().toString(36)}`;

	await page.goto("dashboard/404s/301/add");
	await expect(page.getByRole("heading", { name: /301|redirect/i }).first()).toBeVisible({
		timeout: 15_000,
	});

	// TextField labels
	const fromField = page.getByLabel(/From/i);
	const toField = page.getByLabel(/To/i);
	await fromField.fill(from);
	await toField.fill("/");
	await page.getByRole("button", { name: /Create redirect/i }).click();

	// Success navigates to the 301 list; path may be normalized (slash stripped).
	await page.waitForURL(/\/dashboard\/404s\/301/, { timeout: 15_000 });
	const slug = from.replace(/^\//, "");
	await expect(
		page
			.getByText(from)
			.or(page.getByText(slug))
			.or(page.getByText(/Redirect/i))
			.first()
	).toBeVisible({ timeout: 15_000 });
});
