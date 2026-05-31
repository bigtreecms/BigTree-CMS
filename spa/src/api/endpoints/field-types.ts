import { api } from "@/api/client";

/**
 * Field types — both built-ins (read-only, served from the core JSON DB) and
 * custom user-defined types (full CRUD via JSONDB). The list endpoint serves
 * the cached registry as a single object keyed by type id; passing ?split=1
 * splits it into `{ default, custom }` for the UI's "built-in vs custom" view.
 */

export type FieldUseCase = "templates" | "modules" | "settings" | "callouts" | "feeds";

export interface FieldType {
	id: string;
	name: string;
	use_cases?: FieldUseCase[] | string[];
	self_draw?: boolean | string;
	extension?: string;
	/** Some entries carry their own draw/process/settings php paths — kept loose. */
	[key: string]: unknown;
}

export type FieldTypeRegistry = Record<string, FieldType>;

export interface FieldTypeRegistrySplit {
	default: FieldTypeRegistry;
	custom: FieldTypeRegistry;
}

export interface FieldTypeCreateBody {
	id: string;
	name?: string;
	use_cases?: string[];
	self_draw?: boolean;
}

/**
 * Schema for the per-field settings designer (replaces the raw-JSON editor).
 * Served by GET /field-types/{id}/schema from core's field-type-schemas.php.
 * `settings_schema` is faithful to each legacy settings.php — the `id` of each
 * descriptor is the exact key the field's draw/process php reads.
 */
export type SettingControl =
	| "string"
	| "int"
	| "textarea"
	| "bool"
	| "enum"
	| "note"
	| "heading"
	| "directory"
	| "db_table"
	| "db_column"
	| "db_column_sort"
	| "list_maker"
	| "source_fields"
	| "callout_groups"
	| "image_options"
	| "matrix_columns";

export interface SettingShowIf {
	field: string;
	equals?: string | number | boolean;
	in?: Array<string | number>;
	empty?: boolean;
	not_empty?: boolean;
}

export interface SettingDescriptor {
	id: string;
	control: SettingControl;
	label?: string;
	hint?: string;
	note?: string;
	heading?: string;
	placeholder?: string;
	default?: unknown;
	required?: boolean;
	options?: Array<{ value: string; label: string }>;
	columns?: string[];
	keys?: string[];
	depends_on?: string;
	context_defaults?: Record<string, string>;
	contexts?: string[];
	show_if?: SettingShowIf;
}

export interface FieldTypeSchema {
	id: string;
	name: string;
	category?: string;
	value_type?: string;
	settings_schema?: SettingDescriptor[];
	self_draw?: boolean;
	render_fallback?: boolean;
	[key: string]: unknown;
}

export const fieldTypesApi = {
	list: () => api.get<FieldTypeRegistry>("/field-types"),

	listSplit: () => api.get<FieldTypeRegistrySplit>("/field-types", { query: { split: true } }),

	get: (id: string) => api.get<FieldType>(`/field-types/${encodeURIComponent(id)}`),

	getSchema: (id: string) =>
		api.get<FieldTypeSchema>(`/field-types/${encodeURIComponent(id)}/schema`),

	create: (body: FieldTypeCreateBody) => api.post<FieldType>("/field-types", body),

	update: (id: string, body: Partial<FieldTypeCreateBody>) =>
		api.patch<FieldType>(`/field-types/${encodeURIComponent(id)}`, body),

	delete: (id: string) => api.delete<void>(`/field-types/${encodeURIComponent(id)}`),
};
