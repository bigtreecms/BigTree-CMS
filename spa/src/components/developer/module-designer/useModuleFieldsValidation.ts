import { useEffect, useMemo, useState } from "react";

import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import type { ModuleFormField } from "@/api/endpoints/modules";
import type { ResourceEntry } from "@/components/developer/ResourceDesigner";
import { useResourceSettingsValidation } from "@/components/developer/field-settings/useResourceSettingsValidation";

/**
 * Nested field-settings validation shared by the module form tabs
 * (`ModuleFormsTab`, `ModuleEmbedFormsTab`), which each build a form out of a
 * `ResourceDesigner` field list and validate every field's own settings.
 *
 * Owns the per-index settings-error map, flattens it for scroll-to-first-error,
 * resolves the settings schema for the field list, and clears stale errors
 * whenever a different row opens/closes. `validate()` runs the check, stores the
 * result, and returns it so the caller can gate its save on `hasSettingsErrors`.
 */
export const useModuleFieldsValidation = (fields: ModuleFormField[], editingId: string | null) => {
	const [settingsErrors, setSettingsErrors] = useState<Record<number, Record<string, string>>>(
		{}
	);

	// Nested settings errors are keyed by resource index; flatten so the
	// scroll-to-first-error hook can see whether any exist this submit.
	const flatSettingsErrors = useMemo(
		() => Object.assign({}, ...Object.values(settingsErrors)) as Record<string, string>,
		[settingsErrors]
	);

	useScrollToFirstError(flatSettingsErrors);

	const settingsValidation = useResourceSettingsValidation(
		fields as unknown as ResourceEntry[],
		"modules"
	);

	// Drop stale per-field settings errors whenever a different row opens/closes.
	useEffect(() => {
		setSettingsErrors({});
	}, [editingId]);

	const validate = (): Record<number, Record<string, string>> => {
		const sErrors = settingsValidation.validate();
		setSettingsErrors(sErrors);

		return sErrors;
	};

	return { settingsErrors, flatSettingsErrors, validate };
};
