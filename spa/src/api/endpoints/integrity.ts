import { api } from "@/api/client";

/**
 * Site Integrity — the broken link/image checker. Mirrors the legacy dashboard
 * "Site Integrity" tool: an incremental scan of every page and every auto-module
 * entry for dead links, broken internal-page/resource links, and missing images
 * (external links optionally). The scan is driven client-side: build the work
 * list once via `start`, then check one page or one module entry per request so
 * a long run stays responsive and can be resumed.
 *
 * Backed by /dashboard/integrity/* (BigTree\Services\IntegrityService).
 */

export type IntegrityErrorType = "link" | "image";

export interface IntegrityError {
	field: string;
	type: IntegrityErrorType;
	url: string;
}

export interface IntegrityModule {
	/** A view id under the module, used to build entry edit links. */
	edit_view_id: string | number | null;
	/** Module form id (string in JSONDB). */
	id: string;
	/** Entry ids to scan. */
	items: Array<number | string>;
	/** Owning module id — used to build SPA edit links. */
	module_id: string | number;
	/** Human-readable breadcrumb, e.g. "Modules › News › Add/Edit Article". */
	name: string;
}

export interface IntegrityState {
	external_session: boolean;
	internal_session: boolean;
}

export interface IntegritySession {
	current_item: number;
	current_module: number;
	current_page: number;
	external: boolean;
	module_errors: Record<string, Record<string, IntegrityError[]>>;
	modules: IntegrityModule[];
	/** Errors already discovered (only populated when resuming). */
	page_errors: Record<string, { nav_title: string; errors: IntegrityError[] }>;
	pages: number[];
	resumed: boolean;
}

export interface PageCheckResult {
	errors: IntegrityError[];
	id: number;
	nav_title: string;
}

export interface ModuleItemCheckResult {
	errors: IntegrityError[];
	form: string;
	id: number | string;
}

export interface IntegrityExportRow {
	field: string;
	location: string;
	title: string;
	type: IntegrityErrorType;
	url: string;
}

export const integrityApi = {
	state: () => api.get<IntegrityState>("/dashboard/integrity/state"),

	start: (external: boolean) =>
		api.post<IntegritySession>("/dashboard/integrity/start", { external }),

	checkPage: (external: boolean, id: number, index: number, signal?: AbortSignal) =>
		api.post<PageCheckResult>(
			"/dashboard/integrity/check-page",
			{ external, id, index },
			{ signal }
		),

	checkModuleItem: (
		params: {
			external: boolean;
			form: string;
			id: number | string;
			module: number;
			index: number;
		},
		signal?: AbortSignal
	) =>
		api.post<ModuleItemCheckResult>("/dashboard/integrity/check-module-item", params, {
			signal,
		}),

	reset: () => api.post<void>("/dashboard/integrity/reset"),

	export: (external: boolean) =>
		api.get<IntegrityExportRow[]>("/dashboard/integrity/export", {
			query: { external: external ? "true" : "false" },
		}),
};
