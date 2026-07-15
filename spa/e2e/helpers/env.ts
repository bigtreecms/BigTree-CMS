/** Shared env for Playwright L3 journeys. */

export const e2eApiBase = () =>
	process.env.E2E_API_BASE ?? "http://127.0.0.1:8080/admin/api/v1";

export const e2eEmail = () =>
	process.env.E2E_EMAIL ?? process.env.BIGTREE_TEST_EMAIL ?? "smoke@ci.local";

export const e2ePassword = () =>
	process.env.E2E_PASSWORD ?? process.env.BIGTREE_TEST_PASSWORD ?? "SmokeTest-CI-2026!";

export const unique = (prefix = "e2e") =>
	`${prefix}-${Date.now().toString(36)}-${Math.floor(Math.random() * 1e4)}`;
