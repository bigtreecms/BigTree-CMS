import { useQuery } from "@tanstack/react-query";

import { Select } from "@/components/ui/Select";
import { normalizeStaticOptions, toStringValue, type StaticOption } from "./fieldHelpers";
import { settingsOf, type FieldComponentProps } from "./types";
import { fieldTypesApi } from "@/api/endpoints/field-types";
import { modulesApi } from "@/api/endpoints/modules";
import { useFormRenderContext } from "@/renderer/forms/FormContext";
import { queryKeys } from "@/lib/queryKeys";

type Option = StaticOption;

/**
 * Stable cache key for settings-driven list options — only the keys that affect
 * the server lookup, so unrelated setting noise doesn't thrash the query.
 */
const listOptionsSettingsKey = (settings: Record<string, unknown>): string =>
	JSON.stringify({
		list_type: settings.list_type ?? "static",
		"pop-table": settings["pop-table"] ?? "",
		"pop-description": settings["pop-description"] ?? "",
		"pop-sort": settings["pop-sort"] ?? "",
		list: settings.list ?? null,
	});

/**
 * BigTree "list" field.
 *
 *   - `static` lists come straight from the field settings.
 *   - `db` / `state` / `country` lists are resolved server-side:
 *       • Inside a module form: GET /modules/{id}/forms/{sid}/list-options
 *         (reads the field's stored definition).
 *       • Everywhere else (page templates, settings, callouts, declarative
 *         custom-type sub-fields): POST /field-types/list-options with the
 *         field's settings blob.
 *
 * The legacy server-side `parser` callback is not applied (it is arbitrary PHP).
 */
export const SelectField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field);
	const listType = (settings.list_type as string) || "static";
	const allowEmpty = settings["allow-empty"] !== "No";

	const context = useFormRenderContext();
	const isDynamic = listType !== "static";
	const useModuleLookup =
		isDynamic && !!context?.moduleId && !!context?.formId && !!field.column;
	const useSettingsLookup = isDynamic && !useModuleLookup;

	const moduleOptionsQ = useQuery({
		queryKey: queryKeys.modules.listOptions(context?.moduleId, context?.formId, field.column),
		queryFn: () => modulesApi.listOptions(context!.moduleId, context!.formId, field.column),
		enabled: useModuleLookup,
		staleTime: 5 * 60 * 1000,
	});

	const settingsOptionsQ = useQuery({
		queryKey: queryKeys.modules.listOptionsFromSettings(listOptionsSettingsKey(settings)),
		queryFn: () => fieldTypesApi.listOptions(settings, field.column),
		enabled: useSettingsLookup,
		staleTime: 5 * 60 * 1000,
	});

	const optionsQ = useModuleLookup ? moduleOptionsQ : settingsOptionsQ;

	const staticItems: Option[] = normalizeStaticOptions(settings.list);

	const items: Option[] = isDynamic ? (optionsQ.data?.options ?? []) : staticItems;

	if (isDynamic && optionsQ.isError) {
		return (
			<Select disabled value="">
				<option value="">— couldn't load options —</option>
			</Select>
		);
	}

	const isLoading = isDynamic && optionsQ.isLoading;

	return (
		<Select
			disabled={disabled || isLoading}
			value={toStringValue(value)}
			onChange={(event) => onChange(event.target.value)}
		>
			{isLoading ? (
				<option value="">Loading…</option>
			) : (
				<>
					{allowEmpty && <option value="">—</option>}
					{items.map((item, index) => (
						<option key={`${item.value}-${index}`} value={item.value}>
							{item.label}
						</option>
					))}
				</>
			)}
		</Select>
	);
};
