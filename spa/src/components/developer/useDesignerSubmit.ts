import { useState } from "react";

import { useFormSubmit } from "@/hooks/useFormSubmit";
import { validateRequired, type RequiredRule } from "@/lib/formValidation";

export interface DesignerSettingsValidation {
	/** Returns nested field-settings errors keyed by entry index. */
	validate: () => Record<number, Record<string, string>>;
}

export interface DesignerSubmitArgs {
	/** Required top-level fields (id/name/…) validated before save. */
	required: RequiredRule[];
	/** Nested field-settings validation from `useResourceSettingsValidation`. */
	settingsValidation: DesignerSettingsValidation;
	/** Fire the create-or-update mutation once validation passes. */
	save: () => void;
	/** When true the handler is a no-op (mutation already in flight). */
	saving?: boolean;
}

/**
 * Shared submit machinery for the ResourceDesigner-backed developer editors
 * (Callout / Feed / Template), which each hand-rolled the identical
 * required-field + nested-settings validation block before calling `save`.
 *
 * Composes `useFormSubmit` for the top-level error + field-error state and adds
 * the nested field-settings error map, then exposes `buildSubmit` — a factory
 * called in render (where `body`/`save` exist) that returns the form's
 * `onSubmit`. `onMutationError` is surfaced so the page can wire it into
 * `useResourceEditor`'s `onError` and keep field-error binding on 422s; because
 * the editor is declared after this hook, that ordering is what forces the
 * split between the up-front hook state and the deferred `buildSubmit`.
 */
export const useDesignerSubmit = () => {
	const form = useFormSubmit();
	const [settingsErrors, setSettingsErrors] = useState<Record<number, Record<string, string>>>(
		{}
	);

	const buildSubmit =
		({ required, settingsValidation, save, saving }: DesignerSubmitArgs) =>
		(event: React.FormEvent) => {
			event.preventDefault();

			if (saving) {
				return;
			}

			const errors = validateRequired(required);
			const sErrors = settingsValidation.validate();

			if (Object.keys(errors).length > 0 || Object.keys(sErrors).length > 0) {
				form.setFieldErrors(errors);
				setSettingsErrors(sErrors);
				form.setError("Please fill in the required fields.");

				return;
			}

			form.setFieldErrors({});
			setSettingsErrors({});
			form.setError(null);
			save();
		};

	return {
		error: form.error,
		setError: form.setError,
		fieldErrors: form.fieldErrors,
		setFieldErrors: form.setFieldErrors,
		onMutationError: form.onMutationError,
		settingsErrors,
		buildSubmit,
	};
};
