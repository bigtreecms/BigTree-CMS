import { useQuery } from "@tanstack/react-query";

import { Select } from "@/components/ui/Select";
import { normalizeStaticOptions, toStringValue, type StaticOption } from "./fieldHelpers";
import { settingsOf, type FieldComponentProps } from "./types";
import { modulesApi } from "@/api/endpoints/modules";
import { useFormRenderContext } from "@/renderer/forms/FormContext";
import { queryKeys } from "@/lib/queryKeys";

type Option = StaticOption;

/**
 * BigTree "list" field.
 *
 *   - `static` lists come straight from the field settings.
 *   - `db` / `state` / `country` lists are resolved server-side via
 *     /modules/{id}/forms/{sid}/list-options, which reads the field's stored
 *     settings and returns `{ value, label }` pairs.
 *
 * Dynamic lists need the surrounding FormRenderContext (module + form id) to
 * locate the field definition; outside a module form they can't be resolved and
 * the select renders disabled. The legacy server-side `parser` callback is not
 * applied (it is arbitrary PHP).
 */
export const SelectField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field);
	const listType = (settings.list_type as string) || "static";
	const allowEmpty = settings["allow-empty"] !== "No";

	const context = useFormRenderContext();
	const isDynamic = listType !== "static";
	const canFetch = isDynamic && !!context?.moduleId && !!context?.formId && !!field.column;

	const optionsQ = useQuery({
		queryKey: queryKeys.modules.listOptions(context?.moduleId, context?.formId, field.column),
		queryFn: () => modulesApi.listOptions(context!.moduleId, context!.formId, field.column),
		enabled: canFetch,
		staleTime: 5 * 60 * 1000,
	});

	const staticItems: Option[] = normalizeStaticOptions(settings.list);

	const items: Option[] = isDynamic ? (optionsQ.data?.options ?? []) : staticItems;

	// A dynamic list outside a module-form context can't be resolved.
	if (isDynamic && !canFetch) {
		return (
			<Select disabled value="">
				<option value="">— dynamic list unavailable here —</option>
			</Select>
		);
	}

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
