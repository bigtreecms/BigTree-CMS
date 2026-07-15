import type { ReactNode } from "react";

import { Field, type FieldSize } from "./Field";
import { Select } from "./Select";

/**
 * Labeled `<select>` — the dropdown counterpart to {@link TextField}, composed
 * on {@link Field} for shared label/hint/error chrome around the bare
 * {@link Select} primitive.
 */

interface SelectOption {
	label: string;
	value: string;
}

interface SelectFieldProps {
	/** Layout-only classes forwarded to the wrapping {@link Field} (e.g. grid spans). */
	className?: string;
	/** Compact vertical padding for space-constrained sections. */
	dense?: boolean;
	disabled?: boolean;
	error?: string;
	hint?: ReactNode;
	label: string;
	onChange: (next: string) => void;
	options: SelectOption[];
	required?: boolean;
	/** Label text size forwarded to {@link Field}: `"md"` (default) or `"sm"`. */
	size?: FieldSize;
	value: string;
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
	size,
	dense,
	className,
}: SelectFieldProps) => (
	<Field
		className={className}
		error={error}
		hint={hint}
		label={label}
		required={required}
		size={size}
	>
		<Select
			dense={dense}
			disabled={disabled}
			value={value}
			onChange={(e) => onChange(e.target.value)}
		>
			{options.map((o) => (
				<option key={o.value} value={o.value}>
					{o.label}
				</option>
			))}
		</Select>
	</Field>
);
