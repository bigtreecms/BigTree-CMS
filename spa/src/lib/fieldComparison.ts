/**
 * Helpers for the form "pending changes" indicators: detecting whether a
 * field's draft value differs from its published value, and rendering either
 * value as a human-readable string for the side-by-side comparison.
 *
 * Shared by module entry forms (FormRenderer) and the page editor, both of
 * which receive a published baseline from the API alongside the (overlaid)
 * draft values.
 */

/** Stable structural equality for loose form values (scalars, arrays, objects). */
export const fieldValuesEqual = (a: unknown, b: unknown): boolean => {
	if (a === b) {
		return true;
	}

	// Treat the "absent" values the user can't distinguish on screen as equal so
	// a draft that never touched a null column isn't flagged as changed.
	if (isEmptyValue(a) && isEmptyValue(b)) {
		return true;
	}

	return JSON.stringify(normalizeForCompare(a)) === JSON.stringify(normalizeForCompare(b));
};

/**
 * Heading for the "draft" side of a field comparison, attributed to whoever
 * created the pending change: "Your draft" when it's the current user, "Draft by
 * {name}" when it's someone else, and a neutral fallback when the owner is
 * unknown. Never assumes ownership.
 */
export const draftOwnerLabel = (
	ownerId: number | null | undefined,
	ownerName: string | null | undefined,
	currentUserId: number | null | undefined
): string => {
	if (ownerId != null && currentUserId != null && ownerId === currentUserId) {
		return "Your draft";
	}

	if (ownerName) {
		return `Draft by ${ownerName}`;
	}

	return "Pending draft";
};

/** Whether a value reads as "no content" (null/undefined/empty string/empty array). */
export const isEmptyValue = (value: unknown): boolean => {
	if (value === null || value === undefined || value === "") {
		return true;
	}

	if (Array.isArray(value)) {
		return value.length === 0;
	}

	return false;
};

/**
 * Render a stored field value as a display string for the comparison panel.
 * Returns null for "empty" so the caller can show a muted placeholder.
 */
export const formatFieldValue = (value: unknown): string | null => {
	if (isEmptyValue(value)) {
		return null;
	}

	if (typeof value === "boolean") {
		return value ? "Yes" : "No";
	}

	if (typeof value === "number") {
		return String(value);
	}

	if (typeof value === "string") {
		// BigTree stores checkbox-style toggles as "on"; surface that legibly.
		if (value === "on") {
			return "Yes";
		}

		return value;
	}

	try {
		return JSON.stringify(value, null, 2);
	} catch {
		return String(value);
	}
};

/**
 * Pretty-print a value as indented JSON for the comparison panel. Accepts both
 * already-parsed arrays/objects and raw JSON strings (BigTree stores some
 * complex field types — e.g. media galleries — as a JSON string), parsing the
 * latter so the diff shows formatted structure rather than one long line.
 */
export const prettyJsonValue = (value: unknown): string => {
	let target = value;

	if (typeof value === "string") {
		try {
			target = JSON.parse(value);
		} catch {
			return value;
		}
	}

	try {
		return JSON.stringify(target, null, 2);
	} catch {
		return String(value);
	}
};

// JSON.stringify is order-sensitive for objects; sort keys so two equal objects
// with different key orders compare equal.
const normalizeForCompare = (value: unknown): unknown => {
	if (Array.isArray(value)) {
		return value.map(normalizeForCompare);
	}

	if (value && typeof value === "object") {
		const out: Record<string, unknown> = {};

		for (const key of Object.keys(value as Record<string, unknown>).sort()) {
			out[key] = normalizeForCompare((value as Record<string, unknown>)[key]);
		}

		return out;
	}

	return value;
};
