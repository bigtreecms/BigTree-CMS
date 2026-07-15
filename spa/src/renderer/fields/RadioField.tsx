import { Radio } from "@/components/ui/Radio";

import { normalizeStaticOptions, toStringValue } from "./fieldHelpers";
import { settingsOf, type FieldComponentProps } from "./types";

/**
 * Mutually-exclusive choice. Same shape as SelectField's static list — the
 * difference is purely visual. Renders inline radio inputs.
 */
export const RadioField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field);
	const items = normalizeStaticOptions(settings.list);
	const selected = toStringValue(value);

	return (
		<div className="flex flex-wrap gap-x-5 gap-y-2">
			{items.map((item, index) => (
				<Radio
					checked={selected === item.value}
					disabled={disabled}
					key={`${field.column}-${index}`}
					label={item.label}
					name={field.column}
					value={item.value}
					onChange={() => onChange(item.value)}
				/>
			))}
		</div>
	);
};
