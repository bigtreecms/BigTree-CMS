import { Field } from "./Field";
import { Select } from "./Select";

/**
 * Labeled `<select>` — the dropdown counterpart to {@link TextField}, composed
 * on {@link Field} for shared label/hint/error chrome around the bare
 * {@link Select} primitive.
 */

interface SelectOption {
	value: string;
	label: string;
}

interface SelectFieldProps {
	label: string;
	value: string;
	onChange: (next: string) => void;
	options: SelectOption[];
	hint?: string;
	error?: string;
	disabled?: boolean;
	required?: boolean;
	/** Compact vertical padding for space-constrained sections. */
	dense?: boolean;
	/** Layout-only classes forwarded to the wrapping {@link Field} (e.g. grid spans). */
	className?: string;
}

export const SelectField = ({
	label,
	value,
	onChange,
	options,
	hint,
	error,
	disabled,
	required,
	dense,
	className,
}: SelectFieldProps) => (
	<Field label={label} hint={hint} error={error} required={required} className={className}>
		<Select
			value={value}
			onChange={(e) => onChange(e.target.value)}
			disabled={disabled}
			dense={dense}
		>
			{options.map((o) => (
				<option key={o.value} value={o.value}>
					{o.label}
				</option>
			))}
		</Select>
	</Field>
);
