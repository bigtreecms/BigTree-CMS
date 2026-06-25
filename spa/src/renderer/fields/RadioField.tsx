import { Radio } from "@/components/ui/Radio";

import { settingsOf, type FieldComponentProps } from "./types";

interface RadioItem {
	key?: string;
	description?: string;
	value?: string;
	label?: string;
}

/**
 * Mutually-exclusive choice. Same shape as SelectField's static list — the
 * difference is purely visual. Renders inline radio inputs.
 */
export const RadioField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field);
	const items = Array.isArray(settings.list) ? (settings.list as RadioItem[]) : [];
	const selected = value == null ? "" : String(value);

	return (
		<div className="flex flex-wrap gap-x-5 gap-y-2">
			{items.map((item, index) => {
				const optValue = item.key ?? item.value ?? "";
				const optLabel = item.description ?? item.label ?? optValue;

				return (
					<Radio
						key={`${field.column}-${index}`}
						name={field.column}
						value={optValue}
						label={optLabel}
						checked={selected === optValue}
						disabled={disabled}
						onChange={() => onChange(optValue)}
					/>
				);
			})}
		</div>
	);
};
