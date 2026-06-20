import { Field } from "./Field";

/**
 * Labeled `<select>` — the dropdown counterpart to {@link TextField}, composed
 * on {@link Field} for shared label/hint/error chrome.
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
}: SelectFieldProps) => (
	<Field label={label} hint={hint} error={error} required={required}>
		<select
			value={value}
			onChange={(e) => onChange(e.target.value)}
			disabled={disabled}
			className="w-full rounded-md border border-border bg-surface px-2.5 py-2 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring disabled:cursor-not-allowed disabled:opacity-60"
		>
			{options.map((o) => (
				<option key={o.value} value={o.value}>
					{o.label}
				</option>
			))}
		</select>
	</Field>
);
