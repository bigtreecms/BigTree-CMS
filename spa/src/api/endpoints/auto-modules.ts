import { api } from "@/api/client";

/**
 * Auto-modules — generic CRUD for module entries via /modules/{id}/entries.
 *
 * The list endpoint returns the raw row data plus the view envelope and
 * pagination meta. The PHP service strips rows the current user can't see via
 * per-row gbp permissions; the SPA never needs to re-filter.
 *
 *   {
 *     "view":  { id, title },
 *     "items": [ ... row data ... ],
 *     "meta":  { page, per_page, pages }
 *   }
 *
 * Items are intentionally loose — the legacy admin has no schema-level
 * guarantee about which columns are present; each module's table dictates the
 * shape. The view config (from modulesApi.views) is the source of truth for
 * which columns to display.
 *
 * Phase 7 wires `list` into the SearchableView; other methods are exported
 * here so later phases (form runtime, reorder DnD, row delete) don't have to
 * touch this file again.
 */

export type ModuleEntryRow = Record<string, unknown> & { id: number | string };

export interface ModuleEntriesListResponse {
	view: { id: number | null; title: string };
	items: ModuleEntryRow[];
	meta: {
		page: number;
		per_page: number;
		pages: number;
	};
}

export interface ModuleEntriesListParams {
	page?: number;
	q?: string;
	sort?: string;
	view?: number;
}

export interface ModuleEntryDetail {
	item: ModuleEntryRow;
	// Pending changes / mtm / tags etc. live alongside `item` — kept loose for
	// the form runtime to interpret.
	[key: string]: unknown;
}

export const autoModulesApi = {
	list: (moduleId: number, params: ModuleEntriesListParams = {}) =>
		api.get<ModuleEntriesListResponse>(`/modules/${moduleId}/entries`, {
			query: {
				page: params.page,
				q: params.q,
				sort: params.sort,
				view: params.view,
			},
		}),

	get: (moduleId: number, entryId: number) =>
		api.get<ModuleEntryDetail>(`/modules/${moduleId}/entries/${entryId}`),

	create: (moduleId: number, body: Record<string, unknown>) =>
		api.post<ModuleEntryRow>(`/modules/${moduleId}/entries`, body),

	update: (moduleId: number, entryId: number, body: Record<string, unknown>) =>
		api.patch<ModuleEntryDetail | { pending: true }>(
			`/modules/${moduleId}/entries/${entryId}`,
			body
		),

	delete: (moduleId: number, entryId: number) =>
		api.delete<void>(`/modules/${moduleId}/entries/${entryId}`),

	reorder: (moduleId: number, ids: Array<number | string>) =>
		api.post<void>(`/modules/${moduleId}/entries/reorder`, { ids }),
};
