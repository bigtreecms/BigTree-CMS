import { Field } from "./Field";
import { TextInput } from "./TextInput";

/**
 * Labeled single-line text input — the standard control for admin forms.
 * Composes {@link Field} for the label/hint/error chrome around the bare
 * {@link TextInput} primitive so every form field reads and renders
 * consistently. For a bare input without the label wrapper, use {@link TextInput}
 * (or a plain `<input>` with the canonical `inputClass` from ./TextInput).
 */

interface TextFieldProps {
	/** Layout-only classes forwarded to the wrapping {@link Field} (e.g. grid spans). */
	className?: string;
	/** Compact vertical padding for space-constrained sections. */
	dense?: boolean;
	disabled?: boolean;
	error?: string;
	hint?: string;
	label: string;
	/** Monospace + slightly smaller text for code/identifier entry. */
	mono?: boolean;
	onChange: (next: string) => void;
	placeholder?: string;
	required?: boolean;
	type?: string;
	value: string;
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
	dense,
	mono,
	className,
}: TextFieldProps) => (
	<Field className={className} error={error} hint={hint} label={label} required={required}>
		<TextInput
			dense={dense}
			disabled={disabled}
			mono={mono}
			placeholder={placeholder}
			type={type}
			value={value}
			onChange={(e) => onChange(e.target.value)}
		/>
	</Field>
);
