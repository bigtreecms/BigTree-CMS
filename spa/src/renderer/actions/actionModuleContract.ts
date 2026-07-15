import type { ComponentType } from "react";

import type { ModuleAction } from "@/api/endpoints/modules";

/**
 * Contract between the SPA host and a custom module-action module (the action-side
 * mirror of fieldModuleContract.ts; see spa/.custom-module-actions-design.md).
 *
 * Unlike a field module — which is framework-agnostic imperative DOM — a custom
 * action is the novel-UI case, so it draws with the SPA's own React primitives and
 * field types. The module therefore default-exports a React component the host
 * *renders* (not an imperative mount), which means a React ErrorBoundary can catch
 * its render failures. Authors import `react`, `@bigtree/ui` and `@bigtree/fields`
 * via the import map rather than bundling their own copies.
 */

/** The contract version the host implements; a module needing a newer major is refused. */
export const HOST_CONTRACT_VERSION = 1;

/** Context about where/how the action was launched. */
export interface ActionContext {
	/** The action record being run. */
	action: ModuleAction;
	moduleId: string;
	/** Route params for the action page. */
	params: Record<string, string>;
	/** Entry ids selected in a view when the action was launched from one. */
	selection?: string[];
	/** The current user's access level (0 editor / 1 admin / 2 developer). */
	userLevel: number;
}

/** Everything the host hands a custom action's component. */
export interface ActionHost {
	context: ActionContext;
	/** Submit a payload to THIS action's declared server handler. */
	invoke: (payload?: unknown) => Promise<unknown>;
	/** Submit to ANOTHER action's handler (by route) on the same module. */
	invokeAction: (route: string, payload?: unknown) => Promise<unknown>;
	/** Navigate within the admin SPA. */
	navigate: (to: string) => void;
	/** Surface a toast notification. */
	toast: (message: string, kind?: "success" | "error" | "info" | "warning") => void;
}

/** The default export of a custom action module bundle. */
export interface ActionModule {
	/** The React component the host renders, handed the `host` as a prop. */
	Component: ComponentType<{ host: ActionHost }>;
	contractVersion: number;
}

/**
 * Identity helper so an author gets full typing on their default export:
 * `export default defineAction({ contractVersion: 1, Component({ host }) { … } })`.
 */
export const defineAction = (mod: ActionModule): ActionModule => mod;

/** Runtime shape-check before the host trusts an imported module. */
export const isActionModule = (value: unknown): value is ActionModule =>
	!!value &&
	typeof value === "object" &&
	typeof (value as ActionModule).Component === "function" &&
	typeof (value as ActionModule).contractVersion === "number";
