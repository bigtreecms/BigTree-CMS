import { toStringValue } from "./fieldHelpers";
import { INPUT_CLASS, settingsOf, type FieldComponentProps } from "./types";

/**
 * Multi-line text. Defaults to 4 rows but the `rows` setting (legacy) can
 * override. Honors the `max_length` setting — the descriptor id the field type
 * declares and the key textarea/settings.php writes — with `maxlength` read as a
 * fallback for a hand-edited legacy blob.
 */
export const TextareaField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field);
	const rows = Number(settings.rows) || 4;
	const maxLength = Number(settings.max_length) || Number(settings.maxlength) || undefined;

	return (
		<textarea
			aria-label={field.title}
			className={`${INPUT_CLASS} font-mono text-[12.5px] leading-relaxed`}
			disabled={disabled}
			maxLength={maxLength}
			rows={rows}
			value={toStringValue(value)}
			onChange={(event) => onChange(event.target.value)}
		/>
	);
};
