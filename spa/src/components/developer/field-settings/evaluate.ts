import type { FieldUseCase, SettingDescriptor } from "@/api/endpoints/field-types";

/** True when a value should be treated as "empty" for show_if purposes. */
const isEmpty = (value: unknown): boolean => {
	if (value === undefined || value === null || value === "") {
		return true;
	}

	if (Array.isArray(value)) {
		return value.length === 0;
	}

	return false;
};

/**
 * Decide whether a settings descriptor is currently visible given the other
 * settings values and the host use_case. Mirrors the legacy settings.php
 * context branches (`$_POST["template"]` …) and inline JS show/hide logic.
 */
export const isVisible = (
	descriptor: SettingDescriptor,
	settings: Record<string, unknown>,
	useCase: FieldUseCase
): boolean => {
	if (descriptor.contexts && !descriptor.contexts.includes(useCase)) {
		return false;
	}

	const condition = descriptor.show_if;

	if (!condition) {
		return true;
	}

	const value = settings[condition.field];

	if (condition.empty) {
		return isEmpty(value);
	}

	if (condition.not_empty) {
		return !isEmpty(value);
	}

	if (condition.in) {
		return condition.in.map(String).includes(value === undefined ? "" : String(value));
	}

	if (condition.equals !== undefined) {
		return String(value ?? "") === String(condition.equals);
	}

	return true;
};
