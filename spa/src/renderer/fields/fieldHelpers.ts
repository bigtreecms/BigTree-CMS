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
