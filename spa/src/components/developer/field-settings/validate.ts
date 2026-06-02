import type { FieldUseCase, SettingDescriptor } from "@/api/endpoints/field-types";
import { isEmptyValue } from "@/lib/formValidation";

import { isVisible } from "./evaluate";

/**
 * Validate a field type's settings blob against its `settings_schema`. Returns a
 * map of `descriptor.id → message` for every **visible** required setting that
 * is empty — the same `fieldErrors` shape the rest of the app renders inline.
 *
 * Mirrors `FieldSettingsEditor`'s own visibility filtering (`isVisible`) so a
 * required setting that is hidden by a `show_if`/`contexts` condition never
 * blocks a save it isn't actually shown for.
 *
 * `directory` controls are special-cased: `DirectoryControl` seeds a
 * context-specific default into storage on mount, so a required directory with
 * a configured `context_defaults` fallback is treated as satisfied even before
 * that seed runs — otherwise a never-opened panel would flag a value the UI
 * fills in automatically.
 */
export const validateFieldSettings = (
	descriptors: SettingDescriptor[] | undefined,
	settings: Record<string, unknown>,
	useCase: FieldUseCase
): Record<string, string> => {
	const errors: Record<string, string> = {};

	if (!descriptors) {
		return errors;
	}

	for (const descriptor of descriptors) {
		if (!descriptor.required || !isVisible(descriptor, settings, useCase)) {
			continue;
		}

		if (!isEmptyValue(settings[descriptor.id])) {
			continue;
		}

		if (descriptor.control === "directory" && descriptor.context_defaults?.[useCase]) {
			continue;
		}

		errors[descriptor.id] = `${descriptor.label ?? descriptor.id} is required.`;
	}

	return errors;
};
