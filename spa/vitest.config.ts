import path from "node:path";
import { fileURLToPath } from "node:url";

import { storybookTest } from "@storybook/addon-vitest/vitest-plugin";
import { defineConfig, mergeConfig } from "vitest/config";

import viteConfig from "./vite.config";

const dirname =
	typeof __dirname !== "undefined" ? __dirname : path.dirname(fileURLToPath(import.meta.url));

// Reuse the app's Vite config (notably the "@" → src alias) so tests resolve
// imports the same way the app does. Two workspace projects:
//   - "unit"      → jsdom; the existing logic + component tests (`npm test`).
//   - "storybook" → Playwright browser; smoke-renders every story via the
//                   Storybook Vitest addon (`npm run test-storybook`). Kept
//                   separate so the core `npm test` gate stays fast and does not
//                   depend on a browser being available.
// More info: https://storybook.js.org/docs/writing-tests/integrations/vitest-addon
export default mergeConfig(
	viteConfig({ mode: "test", command: "serve" }),
	defineConfig({
		test: {
			projects: [
				{
					extends: true,
					test: {
						name: "unit",
						include: ["src/**/*.test.ts", "src/**/*.test.tsx"],
						environment: "jsdom",
						setupFiles: ["src/test/setup.ts"],
					},
				},
				{
					extends: true,
					plugins: [storybookTest({ configDir: path.join(dirname, ".storybook") })],
					test: {
						name: "storybook",
						browser: {
							enabled: true,
							headless: true,
							provider: "playwright",
							instances: [{ browser: "chromium" }],
						},
					},
				},
			],
		},
	})
);
