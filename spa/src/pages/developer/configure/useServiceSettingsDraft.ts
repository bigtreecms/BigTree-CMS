import type { FormEvent } from "react";

import { useConfigDraft, type UseConfigDraftOptions } from "@/hooks/useConfigDraft";

interface ServiceDraft {
	service: string;
	settings: Record<string, unknown>;
}

/**
 * `useConfigDraft` plus the settings-map machinery the service-picker configure
 * pages (Email, PaymentGateway) share: a `service` + `settings` draft, an
 * `onChange(key, value)` that merges a single setting, and the `onSubmit` guard
 * that saves the draft once it exists. Pages that key their config off a flat
 * shape rather than a `settings` map (Geocoding) use `useConfigDraft` directly.
 */
export const useServiceSettingsDraft = <TData, TDraft extends ServiceDraft>(
	options: UseConfigDraftOptions<TData, TDraft>
) => {
	const config = useConfigDraft(options);
	const { draft, setDraft, saveMutation } = config;

	const onChange = (key: string, value: string) => {
		if (!draft) {
			return;
		}

		setDraft({ ...draft, settings: { ...draft.settings, [key]: value } } as TDraft);
	};

	const onSubmit = (event: FormEvent) => {
		event.preventDefault();

		if (!draft) {
			return;
		}

		saveMutation.mutate(draft);
	};

	return { ...config, onChange, onSubmit };
};
