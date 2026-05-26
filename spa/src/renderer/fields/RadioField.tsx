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
				const id = `${field.column}-${index}`;

				return (
					<label
						key={id}
						htmlFor={id}
						className="inline-flex cursor-pointer items-center gap-2 text-[13px] text-text-2"
					>
						<input
							id={id}
							type="radio"
							name={field.column}
							value={optValue}
							checked={selected === optValue}
							disabled={disabled}
							onChange={() => onChange(optValue)}
							className="h-3.5 w-3.5 accent-accent disabled:cursor-not-allowed"
						/>
						<span>{optLabel}</span>
					</label>
				);
			})}
		</div>
	);
};
