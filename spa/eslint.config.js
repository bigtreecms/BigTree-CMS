import js from "@eslint/js";
import tseslint from "typescript-eslint";
import reactHooks from "eslint-plugin-react-hooks";
import react from "eslint-plugin-react";
import prettierPlugin from "eslint-plugin-prettier";
import configPrettier from "eslint-config-prettier";

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
		ignores: ["node_modules/**", "dist/**", "**/*.tsbuildinfo", "public/**"],
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
		languageOptions: {
			parserOptions: {
				project: ["./tsconfig.app.json", "./tsconfig.node.json"],
				tsconfigRootDir: import.meta.dirname,
			},
		},
	}
);
