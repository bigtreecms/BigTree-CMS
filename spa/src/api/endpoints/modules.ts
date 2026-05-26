import { api } from "@/api/client";

/**
 * Module shapes returned by ModuleService::present and ::listGroups. Enough
 * fields here to drive the module landing, designer, and per-user permission
 * tree. The full action/form/view config is fetched separately via the
 * `/modules/{id}/...` sub-resources when needed.
 */

export interface ModuleGbpConfig {
	enabled?: boolean;
	other_table?: string;
	title_field?: string;
	name?: string;
	item_parser?: string;
}

export interface ModuleSummary {
	id: number;
	name: string;
	group: number | null;
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
	id: number;
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
	id: number;
	name: string;
	route: string;
	class: string;
	in_nav: boolean | string;
	level: number;
	position: number;
	form: number | null;
	view: number | null;
	report: number | null;
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
	id: number;
	title: string;
	description?: string;
	table: string;
	type: ModuleViewType;
	settings?: ModuleViewSettings;
	fields?: Record<string, ModuleViewFieldConfig>;
	actions?: Record<string, string>;
	related_form?: number | null;
	preview_url?: string;
	exclude_from_search?: string | boolean;
}

export const modulesApi = {
	list: () => api.get<ModuleSummary[]>("/modules"),

	get: (id: number) => api.get<ModuleSummary>(`/modules/${id}`),

	listGroups: () => api.get<ModuleGroup[]>("/module-groups"),

	actions: (id: number) => api.get<ModuleAction[]>(`/modules/${id}/actions`),

	views: (id: number) => api.get<ModuleView[]>(`/modules/${id}/views`),
};
