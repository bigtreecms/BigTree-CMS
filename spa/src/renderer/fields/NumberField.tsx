import { INPUT_CLASS, settingsOf, type FieldComponentProps } from "./types";

/**
 * Numeric input. Stored as a number when valid, "" otherwise so the field
 * round-trips empty cleanly.
 */
export const NumberField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field);
	const min = settings.min !== undefined ? Number(settings.min) : undefined;
	const max = settings.max !== undefined ? Number(settings.max) : undefined;
	const step = settings.step !== undefined ? Number(settings.step) : undefined;

	return (
		<input
			aria-label={field.title}
			className={INPUT_CLASS}
			disabled={disabled}
			max={max}
			min={min}
			step={step}
			type="number"
			value={value == null || value === "" ? "" : String(value)}
			onChange={(event) => {
				const raw = event.target.value;

				if (raw === "") {
					onChange("");

					return;
				}

				const n = Number(raw);
				onChange(Number.isFinite(n) ? n : raw);
			}}
		/>
	);
};
