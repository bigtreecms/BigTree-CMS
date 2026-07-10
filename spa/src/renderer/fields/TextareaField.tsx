import { toStringValue } from "./fieldHelpers";
import { INPUT_CLASS, settingsOf, type FieldComponentProps } from "./types";

/**
 * Multi-line text. Defaults to 4 rows but the `rows` setting (legacy) can
 * override.
 */
export const TextareaField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field);
	const rows = Number(settings.rows) || 4;
	const maxLength = Number(settings.maxlength) || undefined;

	return (
		<textarea
			aria-label={field.title}
			className={`${INPUT_CLASS} font-mono text-[12.5px] leading-relaxed`}
			rows={rows}
			maxLength={maxLength}
			value={toStringValue(value)}
			disabled={disabled}
			onChange={(event) => onChange(event.target.value)}
		/>
	);
};
