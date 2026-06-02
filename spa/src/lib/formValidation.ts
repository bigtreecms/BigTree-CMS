/**
 * Lightweight client-side required-field validation shared across the static
 * edit forms (developer section, 404s, etc.).
 *
 * The house style surfaces field problems inline via a `fieldErrors` map
 * (`Record<column, message>`) that each form primitive already renders through
 * its `error` prop — the same shape the API returns for a 422. These helpers
 * let a submit handler produce that same map *before* the network call so the
 * mutation can be blocked and the offending fields highlighted, instead of
 * relying on the server to reject an empty payload.
 */

export interface RequiredRule {
	/** Field key — must match the `error={fieldErrors[field]}` wiring. */
	field: string;
	/** Human label used in the generated message, e.g. "ID is required." */
	label: string;
	/** Current value to test for emptiness. */
	value: unknown;
}

/**
 * A value counts as "missing" when it is null/undefined, an empty/whitespace
 * string, or an empty array. Numbers (including 0) and booleans are always
 * considered present.
 */
export const isEmptyValue = (value: unknown): boolean => {
	if (value === null || value === undefined) {
		return true;
	}

	if (typeof value === "string") {
		return value.trim() === "";
	}

	if (Array.isArray(value)) {
		return value.length === 0;
	}

	return false;
};

/**
 * Returns a `fieldErrors`-shaped map containing one message per empty required
 * field. An empty object means everything passed.
 */
export const validateRequired = (rules: RequiredRule[]): Record<string, string> => {
	const errors: Record<string, string> = {};

	for (const rule of rules) {
		if (isEmptyValue(rule.value)) {
			errors[rule.field] = `${rule.label} is required.`;
		}
	}

	return errors;
};
