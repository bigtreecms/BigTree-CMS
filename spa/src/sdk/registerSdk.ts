import React from "react";
import * as ReactJSXRuntime from "react/jsx-runtime";

import * as BigTreeFields from "./fields";
import * as BigTreeUI from "./ui";

/**
 * The singletons custom action modules resolve their bare imports to, via the
 * SDK import map (index.html → public/sdk/*.js, which read this global). Keeping
 * one React instance + the app's own components is what makes a custom action
 * look and behave native. See spa/.custom-module-actions-design.md.
 */
export interface BigTreeSdkGlobals {
	react: typeof React;
	"react/jsx-runtime": typeof ReactJSXRuntime;
	"@bigtree/ui": typeof BigTreeUI;
	"@bigtree/fields": typeof BigTreeFields;
}

declare global {
	var __BIGTREE_SDK__: BigTreeSdkGlobals | undefined;
}

/** Populate the SDK global. Call once at startup, before any module action runs. */
export const registerSdk = (): void => {
	globalThis.__BIGTREE_SDK__ = {
		react: React,
		"react/jsx-runtime": ReactJSXRuntime,
		"@bigtree/ui": BigTreeUI,
		"@bigtree/fields": BigTreeFields,
	};
};
