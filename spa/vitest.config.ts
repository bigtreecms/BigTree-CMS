import { defineConfig, mergeConfig } from "vitest/config";

import viteConfig from "./vite.config";

// Reuse the app's Vite config (notably the "@" → src alias) so tests resolve
// imports the same way the app does. Tests run in jsdom so both pure-logic
// helpers and React component tests can share the same environment.
export default mergeConfig(
	viteConfig({ mode: "test", command: "serve" }),
	defineConfig({
		test: {
			include: ["src/**/*.test.ts", "src/**/*.test.tsx"],
			environment: "jsdom",
			setupFiles: ["src/test/setup.ts"],
		},
	})
);
