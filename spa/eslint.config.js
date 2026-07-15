import js from "@eslint/js";
import tseslint from "typescript-eslint";
import reactHooks from "eslint-plugin-react-hooks";
import react from "eslint-plugin-react";
import prettierPlugin from "eslint-plugin-prettier";
import configPrettier from "eslint-config-prettier";
import storybook from "eslint-plugin-storybook";
import betterTailwind from "eslint-plugin-better-tailwindcss";
import perfectionist from "eslint-plugin-perfectionist";

export default tseslint.config(
	js.configs.recommended,
	...tseslint.configs.recommended,
	{
		plugins: {
			"react-hooks": reactHooks,
			prettier: prettierPlugin,
		},
		rules: {
			...reactHooks.configs.recommended.rules,
			"prettier/prettier": "error",
			// Relax some strict rules for initial port; tighten later
			"@typescript-eslint/no-unused-vars": [
				"warn",
				{ argsIgnorePattern: "^_", varsIgnorePattern: "^_" },
			],
			"@typescript-eslint/no-explicit-any": "warn",
			"no-undef": "off", // TS handles
		},
	},
	configPrettier,
	{
		ignores: [
			"node_modules/**",
			"dist/**",
			"storybook-static/**",
			"**/*.tsbuildinfo",
			"public/**",
		],
	},
	{
		files: ["**/*.{ts,tsx}"],
		plugins: {
			react,
		},
		rules: {
			"react/jsx-no-target-blank": "error",
		},
	},
	{
		files: ["**/*.{ts,tsx}"],
		plugins: {
			"better-tailwindcss": betterTailwind,
		},
		settings: {
			"better-tailwindcss": {
				entryPoint: "src/styles/index.css",
			},
		},
		rules: {
			// Replace arbitrary/legacy utilities with their canonical Tailwind
			// equivalent (e.g. `h-[22px]` → `h-5.5`, `flex-shrink-0` → `shrink-0`).
			"better-tailwindcss/enforce-canonical-classes": "error",
		},
	},
	{
		files: ["**/*.{ts,tsx}"],
		plugins: {
			perfectionist,
		},
		rules: {
			// Sort JSX props: shorthand booleans first, event handlers last,
			// everything else natural-alphabetical in between.
			"perfectionist/sort-jsx-props": [
				"error",
				{
					type: "natural",
					order: "asc",
					customGroups: [{ groupName: "callback", elementNamePattern: "^on[A-Z]" }],
					groups: ["shorthand-prop", "unknown", "callback"],
				},
			],
			// Sort interface / type-literal members natural-alphabetical.
			"perfectionist/sort-interfaces": [
				"error",
				{
					type: "natural",
					order: "asc",
				},
			],
		},
	},
	{
		files: ["**/*.{ts,tsx}"],
		languageOptions: {
			parserOptions: {
				project: ["./tsconfig.app.json", "./tsconfig.node.json", "./tsconfig.e2e.json"],
				tsconfigRootDir: import.meta.dirname,
			},
		},
	},
	storybook.configs["flat/recommended"],
	{
		// E2E setup/teardown helpers talk to the REST API and legitimately handle
		// untyped JSON responses; `any` is expected here.
		files: ["e2e/**/*.{ts,tsx}"],
		rules: {
			"@typescript-eslint/no-explicit-any": "off",
		},
	}
);
