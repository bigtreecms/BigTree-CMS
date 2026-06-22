import { Field } from "./Field";
import { TextInput, inputClass } from "./TextInput";

/**
 * Labeled single-line text input — the standard control for admin forms.
 * Composes {@link Field} for the label/hint/error chrome around the bare
 * {@link TextInput} primitive so every form field reads and renders
 * consistently. For a bare input without the label wrapper, use {@link TextInput}
 * (or a plain `<input>` with `inputClass`).
 */

// Re-exported for the many call sites that style a one-off control with the
// canonical class; the source of truth lives in ./TextInput.
export { inputClass };

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
	/** Compact vertical padding for space-constrained sections. */
	dense?: boolean;
	/** Monospace + slightly smaller text for code/identifier entry. */
	mono?: boolean;
	/** Layout-only classes forwarded to the wrapping {@link Field} (e.g. grid spans). */
	className?: string;
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
	<Field label={label} hint={hint} error={error} required={required} className={className}>
		<TextInput
			type={type}
			value={value}
			onChange={(e) => onChange(e.target.value)}
			disabled={disabled}
			placeholder={placeholder}
			dense={dense}
			mono={mono}
		/>
	</Field>
);
