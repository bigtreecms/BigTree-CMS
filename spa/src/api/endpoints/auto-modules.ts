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
	view: { id: string | null; title: string };
	items: ModuleEntryRow[];
	meta: {
		page: number;
		per_page: number;
		pages: number;
	};
	// Grouped/images-grouped only: maps the cached `group_field` value (often a
	// foreign-key id) to the display title resolved from the view's `other_table`.
	groups?: Record<string, string>;
}

export interface ModuleEntriesListParams {
	page?: number;
	q?: string;
	sort?: string;
	view?: string;
}

export interface ModuleEntryDetail {
	item: ModuleEntryRow;
	// Pending changes / mtm / tags etc. live alongside `item` — kept loose for
	// the form runtime to interpret.
	[key: string]: unknown;
}

/**
 * Note on IDs: `moduleId` is a string slug ("modules-..."); `entryId` is a
 * real auto-increment row id from the module's underlying database table.
 *
 * Note on `viewId`: the table correlation lives on the view, not the module.
 * Entry-level calls pass the active view as `?view=` so the service can
 * resolve the underlying table — modules with no top-level `table` will 404
 * otherwise.
 */
const viewQuery = (viewId?: string) => (viewId ? { view: viewId } : undefined);

/**
 * The table a module entry belongs to is resolved server-side from either the
 * active view (list/edit screens) or the form (form-only actions, which have no
 * view). Exactly one of these is the authoritative reference depending on how
 * the screen was reached — pass whichever one applies.
 */
export interface EntryTableRef {
	view?: string;
	form?: string;
}

const refQuery = (ref?: EntryTableRef) => {
	if (ref?.form) {
		return { form: ref.form };
	}

	if (ref?.view) {
		return { view: ref.view };
	}

	return undefined;
};

export const autoModulesApi = {
	list: (moduleId: string, params: ModuleEntriesListParams = {}) =>
		api.get<ModuleEntriesListResponse>(`/modules/${encodeURIComponent(moduleId)}/entries`, {
			query: {
				page: params.page,
				q: params.q,
				sort: params.sort,
				view: params.view,
			},
		}),

	get: (moduleId: string, entryId: number, ref?: EntryTableRef) =>
		api.get<ModuleEntryDetail>(`/modules/${encodeURIComponent(moduleId)}/entries/${entryId}`, {
			query: refQuery(ref),
		}),

	create: (
		moduleId: string,
		body: Record<string, unknown>,
		ref?: EntryTableRef,
		publish = false
	) =>
		api.post<ModuleEntryRow | { pending_id: number; pending: true }>(
			`/modules/${encodeURIComponent(moduleId)}/entries`,
			publish ? { ...body, __publish__: true } : body,
			{ query: refQuery(ref) }
		),

	update: (
		moduleId: string,
		entryId: number,
		body: Record<string, unknown>,
		ref?: EntryTableRef,
		publish = false
	) =>
		api.patch<ModuleEntryDetail | { pending: true }>(
			`/modules/${encodeURIComponent(moduleId)}/entries/${entryId}`,
			publish ? { ...body, __publish__: true } : body,
			{ query: refQuery(ref) }
		),

	delete: (moduleId: string, entryId: number, ref?: EntryTableRef) =>
		api.delete<void>(`/modules/${encodeURIComponent(moduleId)}/entries/${entryId}`, undefined, {
			query: refQuery(ref),
		}),

	reorder: (moduleId: string, ids: Array<number | string>, viewId?: string) =>
		api.post<void>(`/modules/${encodeURIComponent(moduleId)}/entries/reorder`, {
			ids,
			...(viewId ? { view: viewId } : {}),
		}),

	archive: (moduleId: string, entryId: number, viewId?: string) =>
		api.post<ModuleEntryFlagToggleResponse>(
			`/modules/${encodeURIComponent(moduleId)}/entries/${entryId}/archive`,
			{},
			{ query: viewQuery(viewId) }
		),

	approve: (moduleId: string, entryId: number, viewId?: string) =>
		api.post<ModuleEntryFlagToggleResponse>(
			`/modules/${encodeURIComponent(moduleId)}/entries/${entryId}/approve`,
			{},
			{ query: viewQuery(viewId) }
		),

	feature: (moduleId: string, entryId: number, viewId?: string) =>
		api.post<ModuleEntryFlagToggleResponse>(
			`/modules/${encodeURIComponent(moduleId)}/entries/${entryId}/feature`,
			{},
			{ query: viewQuery(viewId) }
		),
};

export interface ModuleEntryFlagToggleResponse {
	id: number;
	column: "archived" | "approved" | "featured";
	value: "" | "on";
}
