import { defineConfig, devices } from "@playwright/test";

/**
 * Phase 3 L3 E2E.
 *
 * **CI / production parity:** packaged SPA at `/admin` via PHP (`ci-router.php`).
 * Set `E2E_BASE_URL=http://127.0.0.1:8080/admin` and ensure
 * `custom/environment.php` uses `admin_root` ending in `/admin/` (CI does).
 *
 * **Local (this repo often uses `/remaster/admin`):** prefer Vite so basename is
 * empty and the API is proxied. Start PHP for the API only, then:
 *
 *   E2E_USE_VITE=1 npm run test:e2e
 *
 * Env:
 *   E2E_BASE_URL   default Vite http://127.0.0.1:5173 or PHP http://127.0.0.1:8080/admin
 *   E2E_API_BASE   for setup helpers (create users/settings via REST)
 *   E2E_EMAIL / E2E_PASSWORD
 *   E2E_API_TARGET PHP origin+path for Vite proxy (default http://127.0.0.1:8080/admin/api/v1)
 */

const useVite = process.env.E2E_USE_VITE === "1" || process.env.E2E_USE_VITE === "true";
const baseURL =
	process.env.E2E_BASE_URL ??
	(useVite ? "http://127.0.0.1:5173" : "http://127.0.0.1:8080/admin");

export default defineConfig({
	testDir: "./e2e",
	fullyParallel: false,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: process.env.CI ? [["github"], ["list"]] : "list",
	timeout: 60_000,
	expect: { timeout: 15_000 },
	use: {
		baseURL,
		trace: "on-first-retry",
		screenshot: "only-on-failure",
		video: "off",
	},
	projects: [
		{
			name: "chromium",
			use: { ...devices["Desktop Chrome"] },
		},
	],
	// Vite only when explicitly requested — PHP API must already be up.
	webServer: useVite
		? {
				command: "npm run dev -- --host 127.0.0.1 --port 5173",
				url: "http://127.0.0.1:5173",
				reuseExistingServer: !process.env.CI,
				timeout: 120_000,
				env: {
					...process.env,
					VITE_API_TARGET:
						process.env.E2E_API_TARGET ??
						process.env.E2E_API_BASE ??
						"http://127.0.0.1:8080/admin/api/v1",
				},
			}
		: undefined,
});
