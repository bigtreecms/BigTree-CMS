import { toStringValue } from "./fieldHelpers";
import { INPUT_CLASS, settingsOf, type FieldComponentProps } from "./types";

/**
 * Plain text input. Honors the `max_length` setting — the descriptor id the field
 * type declares and the key text/settings.php writes — and exposes the value as a
 * string. `maxlength` is read as a fallback for a hand-edited legacy blob. Empty
 * value is normalized to "".
 */
export const TextField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field);
	const maxLength = Number(settings.max_length) || Number(settings.maxlength) || undefined;
	const placeholder = (settings.placeholder as string) || undefined;

	return (
		<input
			aria-label={field.title}
			className={INPUT_CLASS}
			disabled={disabled}
			maxLength={maxLength}
			placeholder={placeholder}
			type="text"
			value={toStringValue(value)}
			onChange={(event) => onChange(event.target.value)}
		/>
	);
};
