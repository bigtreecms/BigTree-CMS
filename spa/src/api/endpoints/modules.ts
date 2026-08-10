import { api, crudEndpoints } from "@/api/client";

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
	item_parser?: string;
	name?: string;
	other_table?: string;
	title_field?: string;
}

export interface ModuleSummary {
	/**
	 * Caller's permission level for this module, returned by GET /modules/{id}.
	 * "p" unlocks "Save & Publish" on the module's forms. Optional because list
	 * responses don't include it.
	 */
	access?: "n" | "v" | "e" | "p";
	class: string;
	gbp: ModuleGbpConfig;
	group: string | null;
	group_name: string;
	icon: string;
	id: string;
	name: string;
	position: number;
	route: string;
	table: string;
}

export interface ModuleGroup {
	id: string;
	name: string;
	position?: number;
	route?: string;
}

/**
 * One category row for a GBP module, from `/modules/{id}/gbp-categories`.
 * `id` is the primary key of a row in the module's `gbp.other_table`; `title`
 * is the resolved (and optionally item_parser-processed) label.
 */
export interface GbpCategory {
	id: string;
	title: string;
}

/**
 * One row from `/modules/{id}/actions`. The legacy admin storage uses `class`
 * to hold the icon glyph and `in_nav` is the PHP "on" / "" toggle. We expose
 * `in_nav` as a boolean to the SPA but keep the rest of the shape pass-through.
 */
export interface ModuleAction {
	class: string;
	contract_version?: number;
	form: string | null;
	handler?: string;
	id: string;
	in_nav: boolean | string;
	level: number;
	name: string;
	position: number;
	/**
	 * Present on a custom (module) action — it draws its own UI from a JS module
	 * and submits to a declared server handler. Absent on auto (form/view/report)
	 * and legacy custom-PHP actions. See ModuleActionSchema for the full contract.
	 */
	render?: "module" | string;
	report: string | null;
	/** The landing action stores null/"" here; all others have a slug. */
	route: string | null;
	trust?: "local" | "core" | "verified" | "marketplace" | string;
	view: string | null;
}

/**
 * Render contract for one action from `/modules/{id}/actions/{sid}/schema`.
 * `render` is "module" for a custom JS action, "auto" for form/view/report, or
 * "server" for a legacy custom-PHP action the SPA can't run natively. A module
 * action carries either `module_source` (local, run in-context) or
 * `asset_url` + `integrity` (extension-delivered, sandboxed when untrusted).
 */
export interface ModuleActionSchema {
	asset_url?: string;
	contract_version: number;
	handler: string;
	id: string;
	integrity?: string;
	module_source?: string;
	name: string;
	render: "module" | "auto" | "server" | string;
	route: string;
	trust?: "local" | "core" | "verified" | "marketplace" | string;
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
	numeric?: string | boolean;
	parser?: string;
	title: string;
	/** Stored as a string in legacy JSONDB (px); empty string = auto. */
	width?: string | number;
}

export interface ModuleViewSettings {
	/**
	 * Per-view-type settings are a loose blob — grouped views carry
	 * `group_field` / `other_table` / `group_parser`, nested views
	 * `nesting_column`, image views `image` / `prefix`, etc. Each renderer reads
	 * the keys it needs; the designer edits them via ViewTypeSettingsControl.
	 */
	[key: string]: unknown;
	filter?: string;
	per_page?: string | number;
	sort_column?: string;
	sort_direction?: "ASC" | "DESC" | string;
}

export interface ModuleView {
	actions?: Record<string, string>;
	description?: string;
	exclude_from_search?: string | boolean;
	fields?: Record<string, ModuleViewFieldConfig>;
	id: string;
	preview_url?: string;
	related_form?: string | null;
	settings?: ModuleViewSettings;
	table: string;
	title: string;
	type: ModuleViewType;
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
	settings?: Record<string, unknown> | unknown[];
	subtitle?: string;
	title: string;
	type: string;
}

export interface ModuleForm {
	default_position?: string;
	fields: ModuleFormField[];
	hooks?: unknown[] | Record<string, unknown>;
	id: string;
	open_graph?: boolean | string;
	return_url?: string;
	return_view?: string | null;
	table: string;
	tagging?: boolean | string;
	title: string;
}

/**
 * Per-filter config in a report's `filters` dict. The key on the parent dict is
 * the table column name; `type` controls the input the ReportRenderer draws.
 *
 * `dropdown` filters carry a parser/poplist config used only by the legacy
 * admin's PHP — server-side resolution is done by getReportResults, so the SPA
 * just needs to send the selected value back unchanged.
 */
export type ModuleReportFilterType = "search" | "dropdown" | "boolean" | "date-range";

export interface ModuleReportFilter {
	options?: Record<string, string> | string[];
	parser?: string;
	pop_description?: string;
	pop_table?: string;
	title: string;
	type: ModuleReportFilterType;
}

export interface ModuleReport {
	fields: Record<string, string> | string[] | string;
	filters: Record<string, ModuleReportFilter> | ModuleReportFilter[];
	id: string;
	module?: string;
	parser?: string;
	streaming?: boolean | string;
	table: string;
	title: string;
	type: "view" | "csv";
	view?: string | null;
}

export interface ModuleReportRunSort {
	field: string;
	order: "ASC" | "DESC";
}

export interface ModuleReportRunRequest {
	filters?: Record<string, unknown>;
	sort?: ModuleReportRunSort;
}

export interface ModuleReportRunResponse {
	form: ModuleForm | null;
	items: Array<Record<string, unknown>>;
	meta: { count: number };
	report: ModuleReport;
	view: ModuleView | null;
}

export interface ModuleReportFilterOption {
	label: string;
	value: string | number | null;
}

export interface ModuleReportPrepareResponse {
	filter_options: Record<string, ModuleReportFilterOption[]>;
	form: ModuleForm | null;
	report: ModuleReport;
	view: ModuleView | null;
}

export interface ModuleEmbedForm {
	css?: string;
	default_pending?: boolean | string;
	default_position?: string;
	fields: ModuleFormField[];
	hooks?: unknown[] | Record<string, unknown>;
	id: string;
	redirect_url?: string;
	table: string;
	thank_you_message?: string;
	title: string;
}

export interface RelationOption {
	id: number;
	title: string;
}

export interface ListOption {
	label: string;
	value: string;
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
	/**
	 * Many-to-many only. When set, the server queries the connecting table
	 * for the entry's current selections (in stored order) and returns those
	 * items instead of the candidate list. OneToManyField does not need this
	 * since its values live in the entry's own column.
	 */
	entry?: number;
	ids?: Array<number | string>;
	q?: string;
}

/**
 * Write bodies for the module designer. The server fills defaults and coerces
 * the legacy "on"/"" toggles, so these stay loose where the storage is loose.
 */
export interface ModuleCreateBody {
	class?: string;
	gbp?: ModuleGbpConfig;
	group?: string | null;
	icon?: string;
	name: string;
	route?: string;
	table?: string;
}

export type ModuleUpdateBody = Partial<ModuleCreateBody>;

/** One field row in the "build the table for me" scaffold wizard. */
export interface ModuleScaffoldField {
	subtitle?: string;
	title: string;
	type: string;
}

/**
 * Body for POST /modules/scaffold — the auto-build path. The server creates the
 * table + columns, the module, an add/edit form, and a landing view from this.
 */
export interface ModuleScaffoldBody {
	/** Builtin actions → extra status columns (approved/featured/archived). */
	actions?: { approve?: boolean; feature?: boolean; archive?: boolean };
	class?: string;
	fields: ModuleScaffoldField[];
	group?: string | null;
	icon?: string;
	/** Singular item title for the form ("Add Article"); derived from name if blank. */
	item_title?: string;
	name: string;
	route?: string;
	/** NEW table name (letters/numbers/underscore); must not already exist. */
	table: string;
	/** Plural view title ("Viewing Articles"); derived from name if blank. */
	view_title?: string;
	view_type?: "searchable" | "draggable";
}

export interface ModuleActionBody {
	class?: string;
	contract_version?: number;
	form?: string | null;
	handler?: string;
	icon?: string;
	in_nav?: boolean;
	level?: number;
	module_source?: string;
	name: string;
	position?: number;
	/** Custom (module) action authoring — see ModuleAction / ModuleActionSchema. */
	render?: "module" | "";
	report?: string | null;
	route?: string;
	view?: string | null;
}

export interface ModuleFormBody {
	default_position?: string;
	fields?: ModuleFormField[];
	hooks?: Record<string, unknown> | unknown[];
	open_graph?: boolean;
	return_url?: string;
	return_view?: string | null;
	table: string;
	tagging?: boolean;
	title: string;
}

export interface ModuleViewBody {
	actions?: Record<string, string>;
	description?: string;
	exclude_from_search?: boolean;
	fields?: Record<string, ModuleViewFieldConfig>;
	preview_url?: string;
	related_form?: string | null;
	settings?: ModuleViewSettings;
	table: string;
	title: string;
	type?: ModuleViewType;
}

export interface ModuleReportBody {
	fields?: Record<string, string> | string[];
	filters?: Record<string, ModuleReportFilter>;
	parser?: string;
	streaming?: boolean;
	table: string;
	title: string;
	type?: "view" | "csv";
	view?: string | null;
}

export interface ModuleEmbedFormBody {
	css?: string;
	default_pending?: boolean;
	default_position?: string;
	fields?: ModuleFormField[];
	hooks?: Record<string, unknown> | unknown[];
	redirect_url?: string;
	table: string;
	thank_you_message?: string;
	title: string;
}

const enc = encodeURIComponent;

export const modulesApi = {
	...crudEndpoints<ModuleSummary, ModuleCreateBody, ModuleUpdateBody>("/modules"),

	scaffold: (body: ModuleScaffoldBody) => api.post<ModuleSummary>("/modules/scaffold", body),

	reorder: (ids: string[]) => api.post<void>("/modules/reorder", { ids }),

	/**
	 * The fixed module-icon vocabulary for the icon picker. Served from the one
	 * PHP source (BigTree\Api\ModuleIcons) so the SPA never keeps its own copy of
	 * the slug list — see useModuleIcons / legacyIcons.
	 */
	icons: () => api.get<{ icons: string[] }>("/module-icons"),

	listGroups: () => api.get<ModuleGroup[]>("/module-groups"),

	actions: (id: string) => api.get<ModuleAction[]>(`/modules/${enc(id)}/actions`),

	actionSchema: (id: string, sid: string) =>
		api.get<ModuleActionSchema>(`/modules/${enc(id)}/actions/${enc(sid)}/schema`),

	/**
	 * Run a custom action's server handler. Optional `selection` is the view-row
	 * (or route-command) ids the host already knows — handlers can fall back to it
	 * when the client payload omits an id.
	 */
	invokeAction: (id: string, sid: string, payload: unknown, selection?: string[]) =>
		api.post<unknown>(`/modules/${enc(id)}/actions/${enc(sid)}/invoke`, {
			payload,
			...(selection && selection.length > 0 ? { selection } : {}),
		}),

	createAction: (id: string, body: ModuleActionBody) =>
		api.post<ModuleAction>(`/modules/${enc(id)}/actions`, body),

	updateAction: (id: string, sid: string, body: Partial<ModuleActionBody>) =>
		api.patch<ModuleAction>(`/modules/${enc(id)}/actions/${enc(sid)}`, body),

	deleteAction: (id: string, sid: string) =>
		api.delete<void>(`/modules/${enc(id)}/actions/${enc(sid)}`),

	reorderActions: (id: string, ids: string[]) =>
		api.post<void>(`/modules/${enc(id)}/actions/reorder`, { ids }),

	views: (id: string) => api.get<ModuleView[]>(`/modules/${enc(id)}/views`),

	createView: (id: string, body: ModuleViewBody) =>
		api.post<ModuleView>(`/modules/${enc(id)}/views`, body),

	updateView: (id: string, sid: string, body: Partial<ModuleViewBody>) =>
		api.patch<ModuleView>(`/modules/${enc(id)}/views/${enc(sid)}`, body),

	deleteView: (id: string, sid: string) =>
		api.delete<void>(`/modules/${enc(id)}/views/${enc(sid)}`),

	forms: (id: string) => api.get<ModuleForm[]>(`/modules/${enc(id)}/forms`),

	createForm: (id: string, body: ModuleFormBody) =>
		api.post<ModuleForm>(`/modules/${enc(id)}/forms`, body),

	updateForm: (id: string, sid: string, body: Partial<ModuleFormBody>) =>
		api.patch<ModuleForm>(`/modules/${enc(id)}/forms/${enc(sid)}`, body),

	deleteForm: (id: string, sid: string) =>
		api.delete<void>(`/modules/${enc(id)}/forms/${enc(sid)}`),

	reports: (id: string) => api.get<ModuleReport[]>(`/modules/${enc(id)}/reports`),

	createReport: (id: string, body: ModuleReportBody) =>
		api.post<ModuleReport>(`/modules/${enc(id)}/reports`, body),

	updateReport: (id: string, sid: string, body: Partial<ModuleReportBody>) =>
		api.patch<ModuleReport>(`/modules/${enc(id)}/reports/${enc(sid)}`, body),

	deleteReport: (id: string, sid: string) =>
		api.delete<void>(`/modules/${enc(id)}/reports/${enc(sid)}`),

	gbpCategories: (id: string) => api.get<GbpCategory[]>(`/modules/${enc(id)}/gbp-categories`),

	embedForms: (id: string) => api.get<ModuleEmbedForm[]>(`/modules/${enc(id)}/embed-forms`),

	createEmbedForm: (id: string, body: ModuleEmbedFormBody) =>
		api.post<ModuleEmbedForm>(`/modules/${enc(id)}/embed-forms`, body),

	updateEmbedForm: (id: string, sid: string, body: Partial<ModuleEmbedFormBody>) =>
		api.patch<ModuleEmbedForm>(`/modules/${enc(id)}/embed-forms/${enc(sid)}`, body),

	deleteEmbedForm: (id: string, sid: string) =>
		api.delete<void>(`/modules/${enc(id)}/embed-forms/${enc(sid)}`),

	prepareReport: (id: string, reportId: string) =>
		api.get<ModuleReportPrepareResponse>(
			`/modules/${encodeURIComponent(id)}/reports/${encodeURIComponent(reportId)}/prepare`
		),

	runReport: (id: string, reportId: string, body: ModuleReportRunRequest = {}) =>
		api.post<ModuleReportRunResponse>(
			`/modules/${encodeURIComponent(id)}/reports/${encodeURIComponent(reportId)}/run`,
			body
		),

	createGroup: (body: { name: string; route?: string }) =>
		api.post<ModuleGroup>("/module-groups", body),

	updateGroup: (id: string, body: { name?: string; route?: string; position?: number }) =>
		api.patch<ModuleGroup>(`/module-groups/${encodeURIComponent(id)}`, body),

	deleteGroup: (id: string) => api.delete<void>(`/module-groups/${encodeURIComponent(id)}`),

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

	/** Resolve dynamic list-field options (db / state / country) for SelectField. */
	listOptions: (moduleId: string, formId: string, column: string) =>
		api.get<{ options: ListOption[] }>(
			`/modules/${encodeURIComponent(moduleId)}/forms/${encodeURIComponent(
				formId
			)}/list-options`,
			{ query: { column } }
		),
};
