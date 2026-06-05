import { api } from "@/api/client";
import { decodeHtmlEntities } from "@/lib/html";

/**
 * Field types — both built-ins (read-only, served from the core JSON DB) and
 * custom user-defined types (full CRUD via JSONDB).
 *
 * GET /field-types can come back in either of two shapes (see
 * FieldTypeRegistry) — nested by use case, or flat by type id — so consumers
 * that want "the types valid for use case X" must go through
 * `fieldTypesForUseCase()` / `fieldTypeName()` rather than walking the raw
 * object; a naive `Object.values()` breaks on one shape or the other. Passing
 * ?split=1 returns `{ default, custom }` for the "built-in vs custom" view.
 */

export type FieldUseCase = "templates" | "modules" | "settings" | "callouts" | "feeds";

/**
 * How the SPA draws a field type's input (see spa/.custom-field-types-design.md):
 *  - core-component: a first-party React component in the field registry.
 *  - declarative:    composed from `input_schema` primitives into one object value.
 *  - module:         a (sandboxed) third-party ES module bundle at `asset_url`.
 *  - server:         the POST /field-types/{id}/render bridge (legacy draw.php).
 */
export type FieldRenderMode = "core-component" | "declarative" | "module" | "server";

/** Trust level — gates in-context vs sandboxed execution for `module` types. */
export type FieldTrust = "core" | "verified" | "marketplace";

export interface FieldType {
	id: string;
	name: string;
	use_cases?: FieldUseCase[] | string[];
	self_draw?: boolean | string;
	extension?: string;
	render?: FieldRenderMode;
	value_type?: string;
	contract_version?: number;
	trust?: FieldTrust;
	asset_url?: string;
	/** Some entries carry their own draw/process/settings php paths — kept loose. */
	[key: string]: unknown;
}

/** One type as it appears inside a use-case bucket: just its display info. */
export interface FieldTypeInfo {
	name?: string;
	self_draw?: boolean | string | null;
	[key: string]: unknown;
}

/**
 * GET /field-types comes back in one of two shapes depending on the deployment:
 *
 *  - **Nested by use case** — `{ modules: { text: {name}, … }, templates: {…} }`
 *    (the cached legacy structure surfaced as-is).
 *  - **Flat by type id** — `{ text: { id, name, use_cases:["modules",…] }, … }`
 *    (the flattening `FieldTypeService::list` performs).
 *
 * `fieldTypesForUseCase` / `fieldTypeName` accept either; don't assume one.
 */
export type FieldTypeRegistryNested = Partial<Record<FieldUseCase, Record<string, FieldTypeInfo>>>;
export type FieldTypeRegistryFlat = Record<string, FieldType>;
export type FieldTypeRegistry = FieldTypeRegistryNested | FieldTypeRegistryFlat;

/** A flattened option, ready for a select/combobox. */
export interface FieldTypeOption {
	id: string;
	name: string;
	/** "default" for the core built-ins, "custom" for user/extension types. */
	group: "default" | "custom";
}

/**
 * The core's built-in field type ids — the exact set hardcoded in
 * BigTreeAdmin::getCachedFieldTypes()'s "default" bucket. The merged
 * GET /field-types response carries no per-type default/custom marker, so the
 * UI reconstructs the legacy "Default" vs "Custom" optgroups from this set.
 * (`route` is built-in but only offered for the "modules" use case.)
 */
export const BUILTIN_FIELD_TYPE_IDS = new Set<string>([
	"text",
	"textarea",
	"html",
	"link",
	"upload",
	"image",
	"video",
	"file-reference",
	"image-reference",
	"video-reference",
	"list",
	"checkbox",
	"date",
	"time",
	"datetime",
	"media-gallery",
	"callouts",
	"matrix",
	"one-to-many",
	"route",
]);

const isUseCaseKey = (key: string): key is FieldUseCase =>
	key === "templates" ||
	key === "modules" ||
	key === "settings" ||
	key === "callouts" ||
	key === "feeds";

/**
 * Whether a value looks like a single field-type record (flat shape) rather
 * than a use-case bucket. Flat records carry a string `name` and/or `id`; a
 * use-case bucket is a map of *those* records, so it won't.
 */
const looksLikeTypeRecord = (value: unknown): value is FieldTypeInfo =>
	!!value &&
	typeof value === "object" &&
	(typeof (value as Record<string, unknown>).name === "string" ||
		typeof (value as Record<string, unknown>).id === "string");

/**
 * Reduce either registry shape (see FieldTypeRegistry) to the `{ typeId: info }`
 * map of types valid for one use case.
 *
 * - Nested: return that use case's bucket directly.
 * - Flat: keep entries whose `use_cases` array includes the slug. Older flat
 *   payloads without `use_cases` are treated as valid everywhere so nothing is
 *   hidden.
 */
const typesForUseCase = (
	registry: FieldTypeRegistry | undefined,
	useCase: FieldUseCase
): Record<string, FieldTypeInfo> => {
	if (!registry || typeof registry !== "object") {
		return {};
	}

	const entries = Object.entries(registry as Record<string, unknown>);
	const isNested = entries.some(
		([key, value]) =>
			isUseCaseKey(key) && !!value && typeof value === "object" && !looksLikeTypeRecord(value)
	);

	if (isNested) {
		const bucket = (registry as FieldTypeRegistryNested)[useCase];

		return bucket && typeof bucket === "object" ? bucket : {};
	}

	const out: Record<string, FieldTypeInfo> = {};

	for (const [id, value] of entries) {
		if (!value || typeof value !== "object") {
			continue;
		}

		const record = value as FieldType;
		const cases = record.use_cases;
		const valid = !Array.isArray(cases) || (cases as string[]).includes(useCase);

		if (valid) {
			out[id] = record;
		}
	}

	return out;
};

/**
 * Flatten the registry into the de-duplicated list of types valid for one use
 * case, sorted by display name and tagged default/custom. Falls back to the
 * type id when an entry is missing its `name`, so an option is never blank.
 * Handles both the nested and flat response shapes.
 */
export const fieldTypesForUseCase = (
	registry: FieldTypeRegistry | undefined,
	useCase: FieldUseCase
): FieldTypeOption[] =>
	Object.entries(typesForUseCase(registry, useCase))
		.map(([id, info]) => ({
			id,
			name: typeof info?.name === "string" && info.name ? decodeHtmlEntities(info.name) : id,
			group: BUILTIN_FIELD_TYPE_IDS.has(id) ? ("default" as const) : ("custom" as const),
		}))
		.sort((a, b) => a.name.localeCompare(b.name));

/**
 * Resolve a field type's display name within a use case, falling back to the id
 * when unknown (e.g. an entry referencing a type the catalog hasn't loaded or no
 * longer offers). Used for the collapsed-row badge so it reads "Text" not "text".
 */
export const fieldTypeName = (
	registry: FieldTypeRegistry | undefined,
	useCase: FieldUseCase,
	id: string
): string => {
	const info = typesForUseCase(registry, useCase)[id];

	return typeof info?.name === "string" && info.name ? decodeHtmlEntities(info.name) : id;
};

/**
 * ?split=1 response. Unlike the default (use-case-nested) listing, each bucket
 * here is a flat `{ typeId: FieldType }` map carrying the full type record
 * (`id`, `name`, `use_cases[]`), so the field-types admin can render one row
 * per type and mark built-ins as un-deletable.
 */
export interface FieldTypeRegistrySplit {
	default: Record<string, FieldType>;
	custom: Record<string, FieldType>;
}

export interface FieldTypeCreateBody {
	id: string;
	name?: string;
	use_cases?: string[];
	self_draw?: boolean;
	/** Render contract — set to "declarative" when an input_schema is supplied. */
	render?: FieldRenderMode;
	/** Stored value shape; "object" for declarative composites. */
	value_type?: string;
	/** Tier 1 composite definition — see InputDescriptor. */
	input_schema?: InputDescriptor[];
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

/**
 * One sub-field of a `declarative` field type. Each descriptor is rendered with
 * a primitive field component (by `type`) and its value stored under `id` in the
 * composite object value. The shape intentionally mirrors a form field so the
 * declarative renderer can reuse the built-in components verbatim.
 */
export interface InputDescriptor {
	id: string;
	type: string;
	title?: string;
	subtitle?: string;
	required?: boolean;
	settings?: Record<string, unknown>;
}

export interface FieldTypeSchema {
	id: string;
	name: string;
	category?: string;
	render?: FieldRenderMode;
	value_type?: string;
	contract_version?: number;
	settings_schema?: SettingDescriptor[];
	input_schema?: InputDescriptor[];
	/** (render "module") URL of the field type's ES module bundle. */
	asset_url?: string;
	/** (render "module") SRI hash ("sha384-…") the sandbox verifies before exec. */
	integrity?: string;
	/** Gates in-context vs sandboxed execution for module types. */
	trust?: FieldTrust;
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
