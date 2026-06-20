import { Field } from "./Field";

/**
 * Labeled single-line text input — the standard control for admin forms.
 * Composes {@link Field} for the label/hint/error chrome so every form field
 * reads and renders consistently. For a bare input without the label wrapper,
 * use a plain `<input>` with `inputClass`.
 */

export const inputClass =
	"w-full rounded-md border border-border bg-surface px-3 py-2 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring disabled:cursor-not-allowed disabled:opacity-60";

interface TextFieldProps {
	label: string;
	value: string;
	onChange: (next: string) => void;
	hint?: string;
	error?: string;
	disabled?: boolean;
	required?: boolean;
	type?: string;
	placeholder?: string;
}

export const TextField = ({
	label,
	value,
	onChange,
	hint,
	error,
	disabled,
	required,
	type = "text",
	placeholder,
}: TextFieldProps) => (
	<Field label={label} hint={hint} error={error} required={required}>
		<input
			type={type}
			value={value}
			onChange={(e) => onChange(e.target.value)}
			disabled={disabled}
			placeholder={placeholder}
			className={inputClass}
		/>
	</Field>
);
