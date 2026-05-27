import { api } from "@/api/client";

/**
 * Module shapes returned by ModuleService::present and ::listGroups. Enough
 * fields here to drive the module landing, designer, and per-user permission
 * tree. The full action/form/view config is fetched separately via the
 * `/modules/{id}/...` sub-resources when needed.
 *
 * ID note: all of these resources have *string* slug IDs in the legacy JSON
 * storage (e.g. "modules-15c3df733b7e8c", "views-15c9cf24c0c358"). The API
 * passes them through as strings; do not Number()-coerce or parseInt them
 * on the client. Module entry IDs (eid) are the only genuine ints — those
 * come from the real auto-increment row of each module's table.
 */

export interface ModuleGbpConfig {
	enabled?: boolean;
	other_table?: string;
	title_field?: string;
	name?: string;
	item_parser?: string;
}

export interface ModuleSummary {
	id: string;
	name: string;
	group: string | null;
	group_name: string;
	class: string;
	table: string;
	gbp: ModuleGbpConfig;
	icon: string;
	route: string;
	position: number;
	graphql: boolean;
	graphql_type: string;
}

export interface ModuleGroup {
	id: string;
	name: string;
	route?: string;
	position?: number;
}

/**
 * One row from `/modules/{id}/actions`. The legacy admin storage uses `class`
 * to hold the icon glyph and `in_nav` is the PHP "on" / "" toggle. We expose
 * `in_nav` as a boolean to the SPA but keep the rest of the shape pass-through.
 */
export interface ModuleAction {
	id: string;
	name: string;
	route: string;
	class: string;
	in_nav: boolean | string;
	level: number;
	position: number;
	form: string | null;
	view: string | null;
	report: string | null;
}

/**
 * View config returned by `/modules/{id}/views`. The legacy storage uses dicts
 * for both `fields` (keyed by column name) and `actions` (keyed by action key,
 * with values either `"on"` for built-ins or a JSON-encoded string for custom
 * row actions). Per-column `parser` is a PHP eval string we cannot execute
 * client-side — the SearchableView renders raw values for now.
 *
 * View `type` controls which renderer subcomponent the dispatcher loads.
 */
export type ModuleViewType =
	| "searchable"
	| "nested"
	| "draggable"
	| "grouped"
	| "images"
	| "images-grouped";

export interface ModuleViewFieldConfig {
	title: string;
	parser?: string;
	/** Stored as a string in legacy JSONDB (px); empty string = auto. */
	width?: string | number;
	numeric?: string | boolean;
}

export interface ModuleViewSettings {
	sort_column?: string;
	sort_direction?: "ASC" | "DESC" | string;
	per_page?: string | number;
	filter?: string;
}

export interface ModuleView {
	id: string;
	title: string;
	description?: string;
	table: string;
	type: ModuleViewType;
	settings?: ModuleViewSettings;
	fields?: Record<string, ModuleViewFieldConfig>;
	actions?: Record<string, string>;
	related_form?: string | null;
	preview_url?: string;
	exclude_from_search?: string | boolean;
}

/**
 * One field row inside a module form config. `settings` is per-type and
 * intentionally loose — each FieldRenderer subcomponent reads the bits it
 * needs and ignores the rest.
 *
 * The legacy storage sometimes stores `settings` as an empty array (`[]`)
 * rather than an empty object — callers should normalize before reading
 * keys off it.
 */
export interface ModuleFormField {
	column: string;
	title: string;
	subtitle?: string;
	type: string;
	settings?: Record<string, unknown> | unknown[];
}

export interface ModuleForm {
	id: string;
	title: string;
	table: string;
	fields: ModuleFormField[];
	default_position?: string;
	return_view?: string | null;
	return_url?: string;
	open_graph?: boolean | string;
	tagging?: boolean | string;
	hooks?: unknown[] | Record<string, unknown>;
}

export interface RelationOption {
	id: number;
	title: string;
}

export interface RelationOptionsResponse {
	items: RelationOption[];
	relation: {
		type: "one-to-many" | "many-to-many";
		/**
		 * MTM only — true when the connecting table has a `position` column.
		 * Drives whether the field's selected-list shows reorder handles.
		 */
		sortable: boolean;
	};
}

export interface RelationOptionsParams {
	column: string;
	q?: string;
	ids?: Array<number | string>;
	/**
	 * Many-to-many only. When set, the server queries the connecting table
	 * for the entry's current selections (in stored order) and returns those
	 * items instead of the candidate list. OneToManyField does not need this
	 * since its values live in the entry's own column.
	 */
	entry?: number;
}

export const modulesApi = {
	list: () => api.get<ModuleSummary[]>("/modules"),

	get: (id: string) => api.get<ModuleSummary>(`/modules/${encodeURIComponent(id)}`),

	listGroups: () => api.get<ModuleGroup[]>("/module-groups"),

	actions: (id: string) => api.get<ModuleAction[]>(`/modules/${encodeURIComponent(id)}/actions`),

	views: (id: string) => api.get<ModuleView[]>(`/modules/${encodeURIComponent(id)}/views`),

	forms: (id: string) => api.get<ModuleForm[]>(`/modules/${encodeURIComponent(id)}/forms`),

	relationOptions: (moduleId: string, formId: string, params: RelationOptionsParams) =>
		api.get<RelationOptionsResponse>(
			`/modules/${encodeURIComponent(moduleId)}/forms/${encodeURIComponent(
				formId
			)}/relation-options`,
			{
				query: {
					column: params.column,
					q: params.q,
					ids: params.ids && params.ids.length > 0 ? params.ids.join(",") : undefined,
					entry: params.entry,
				},
			}
		),
};
