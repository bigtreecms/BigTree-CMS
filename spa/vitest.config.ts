import { defineConfig, mergeConfig } from "vitest/config";

import viteConfig from "./vite.config";

// Reuse the app's Vite config (notably the "@" → src alias) so tests resolve
// imports the same way the app does. Tests run in the default node environment;
// the first suites are pure-logic helpers with no DOM dependency.
export default mergeConfig(
	viteConfig({ mode: "test", command: "serve" }),
	defineConfig({
		test: {
			include: ["src/**/*.test.ts"],
			environment: "node",
		},
	})
);
