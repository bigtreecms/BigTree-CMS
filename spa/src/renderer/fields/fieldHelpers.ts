import type { ModuleFormField } from "@/api/endpoints/modules";

/**
 * Shared value-coercion helpers for the repeater/media field renderers
 * (`MatrixField`, `MediaGalleryField`, `RelationField`, `CalloutsField`, …).
 * These normalize the loosely-typed settings/values that arrive from the API
 * (numbers-as-strings, truthy flags as `"0"`/`"off"`, column settings as a JSON
 * string) into the shapes the renderers expect.
 */

/** Narrow an unknown to a plain object (not null, not an array). */
export const isRecord = (raw: unknown): raw is Record<string, unknown> =>
	Boolean(raw) && typeof raw === "object" && !Array.isArray(raw);

/**
 * Coerce a field value to a string for a controlled text input: strings pass
 * through, `null`/`undefined` become `""`, everything else stringifies. The
 * idiom every text-like field renderer (`TextField`, `TextareaField`,
 * `RouteField`, `ColorField`, `LinkField`, `HTMLField`, `SelectField`,
 * `RadioField`) hand-rolled inline.
 */
export const toStringValue = (value: unknown): string =>
	typeof value === "string" ? value : value == null ? "" : String(value);

/** Coerce a value to a positive integer, or `0` when it isn't one. */
export const toInt = (raw: unknown): number => {
	const n = typeof raw === "number" ? raw : Number(raw);

	return Number.isFinite(n) && n > 0 ? Math.floor(n) : 0;
};

/** Interpret a loosely-typed flag (`"0"`, `"false"`, `"off"`, `0` → false). */
export const isTruthyFlag = (raw: unknown): boolean => {
	if (typeof raw === "boolean") {
		return raw;
	}

	if (typeof raw === "number") {
		return raw !== 0;
	}

	if (typeof raw === "string") {
		const lower = raw.toLowerCase();

		return lower !== "" && lower !== "0" && lower !== "false" && lower !== "off";
	}

	return false;
};

/**
 * Reduce an arbitrary cell value to a short string suitable for a collapsed
 * row's title/subtitle. Strings pass through; numbers/booleans stringify; a
 * record contributes its `title`/`name`/`id` (in that order); anything else
 * yields `""`.
 */
export const stringifyForTitle = (raw: unknown): string => {
	if (typeof raw === "string") {
		return raw;
	}

	if (typeof raw === "number" || typeof raw === "boolean") {
		return String(raw);
	}

	if (isRecord(raw)) {
		const candidate =
			(typeof raw.title === "string" && raw.title) ||
			(typeof raw.name === "string" && raw.name) ||
			(typeof raw.id === "string" && raw.id);

		return candidate || "";
	}

	return "";
};

/** Parse a column-settings value (a JSON string or an object) into a record. */
export const normalizeColumnSettings = (raw: unknown): Record<string, unknown> => {
	if (typeof raw === "string" && raw.trim().length > 0) {
		try {
			const parsed = JSON.parse(raw);

			return isRecord(parsed) ? parsed : {};
		} catch {
			return {};
		}
	}

	if (isRecord(raw)) {
		return raw;
	}

	return {};
};

/**
 * The per-column config a repeater/gallery field stores for each sub-field.
 * Structurally matches both `MatrixField`'s and `MediaGalleryField`'s column
 * shapes, so either can feed {@link columnToFormField}.
 */
export interface RepeaterColumn {
	id: string;
	title: string;
	subtitle?: string;
	type: string;
	settings?: unknown;
	display_title?: boolean | string | number;
}

/**
 * Build the synthetic `ModuleFormField` a repeater sub-field renders from — the
 * identical mapping `MatrixField` and `MediaGalleryField` each inlined. Falls
 * back to the column id for a missing title and parses its settings.
 */
export const columnToFormField = (column: RepeaterColumn): ModuleFormField => ({
	column: column.id,
	title: column.title || column.id,
	subtitle: column.subtitle,
	type: column.type,
	settings: normalizeColumnSettings(column.settings),
});

/** A single entry of a static `settings.list` (BigTree's legacy list shape). */
export interface StaticListItem {
	key?: string;
	value?: string;
	description?: string;
	label?: string;
}

/** A resolved `{ value, label }` option, as `SelectField`/`RadioField` render. */
export interface StaticOption {
	value: string;
	label: string;
}

/**
 * Parse a static `settings.list` into `{ value, label }` options, applying
 * BigTree's `key ?? value` / `description ?? label` coalescing (label finally
 * falls back to the value). Shared by `SelectField` and `RadioField`.
 */
export const normalizeStaticOptions = (raw: unknown): StaticOption[] =>
	(Array.isArray(raw) ? (raw as StaticListItem[]) : []).map((item) => ({
		value: String(item.key ?? item.value ?? ""),
		label: String(item.description ?? item.label ?? item.key ?? item.value ?? ""),
	}));
