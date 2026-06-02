import type { ModuleFormField } from "@/api/endpoints/modules";

/**
 * Client-side validation for the dynamic form runtime (module entry add/edit,
 * embed forms, single-setting editors).
 *
 * BigTree stores a field's validation rules as a space-separated string in
 * `settings.validation` (e.g. `"required"`, `"required email"`) — the same shape
 * legacy `BigTreeAutoModule::validate()` parses. We mirror the **required** rule
 * here so an empty required field is caught before the network call and shown
 * inline, instead of relying on the server 422. The server still re-validates
 * (and enforces the non-required rules), so this is defense-in-depth, never the
 * only gate.
 */

const readValidationString = (field: ModuleFormField): string => {
	const settings = field.settings;

	if (!settings || Array.isArray(settings)) {
		return "";
	}

	const raw = (settings as Record<string, unknown>).validation;

	return typeof raw === "string" ? raw : "";
};

/** Parsed validation rules for a field (e.g. ["required", "email"]). */
export const parseValidationRules = (field: ModuleFormField): string[] =>
	readValidationString(field).split(/\s+/).filter(Boolean);

/** True when the field carries the `required` validation rule. */
export const isFieldRequired = (field: ModuleFormField): boolean =>
	parseValidationRules(field).includes("required");

/**
 * Emptiness test matching legacy `validate()`'s required branch
 * (`$data === false || $data === ""`), specialized per field type:
 *   - checkbox: must be checked — any falsy/"0" value counts as empty
 *   - array-valued fields (many-to-many, callouts, matrix, media-gallery):
 *     an empty array counts as empty
 *   - everything else: null/undefined or a blank/whitespace string
 * Numbers (including 0) and `true` are always considered present.
 */
export const isFieldValueEmpty = (field: ModuleFormField, value: unknown): boolean => {
	if (field.type === "checkbox") {
		return (
			value === false ||
			value === undefined ||
			value === null ||
			value === "" ||
			value === 0 ||
			value === "0"
		);
	}

	if (Array.isArray(value)) {
		return value.length === 0;
	}

	if (value === null || value === undefined) {
		return true;
	}

	if (typeof value === "string") {
		return value.trim() === "";
	}

	return false;
};

/**
 * Returns a `fieldErrors`-shaped map (`column → message`) with one entry per
 * empty required field. An empty object means every required field is filled.
 */
export const validateRequiredFields = (
	fields: ModuleFormField[],
	values: Record<string, unknown>
): Record<string, string> => {
	const errors: Record<string, string> = {};

	for (const field of fields) {
		if (isFieldRequired(field) && isFieldValueEmpty(field, values[field.column])) {
			errors[field.column] = `${field.title || field.column} is required.`;
		}
	}

	return errors;
};
