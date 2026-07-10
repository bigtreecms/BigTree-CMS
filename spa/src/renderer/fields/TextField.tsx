import { toStringValue } from "./fieldHelpers";
import { INPUT_CLASS, settingsOf, type FieldComponentProps } from "./types";

/**
 * Plain text input. Honors a `maxlength` setting (legacy spelling) and exposes
 * the value as a string. Empty value is normalized to "".
 */
export const TextField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field);
	const maxLength = Number(settings.maxlength) || undefined;
	const placeholder = (settings.placeholder as string) || undefined;

	return (
		<input
			type="text"
			aria-label={field.title}
			className={INPUT_CLASS}
			value={toStringValue(value)}
			maxLength={maxLength}
			placeholder={placeholder}
			disabled={disabled}
			onChange={(event) => onChange(event.target.value)}
		/>
	);
};
