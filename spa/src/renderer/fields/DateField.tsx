import { INPUT_CLASS, type FieldComponentProps } from "./types";

/**
 * `<input type="date">` / `<input type="datetime-local">` / `<input type="time">`.
 * The HTML element handles formatting + native picker; we only need to make
 * sure the value is in the canonical YYYY-MM-DD or YYYY-MM-DDTHH:mm format.
 *
 * Legacy BigTree stores datetimes as MySQL strings like "2026-05-26 14:30:00".
 * `datetime-local` wants "2026-05-26T14:30". We slice down to the format the
 * element expects and re-expand on submit (the API tolerates both).
 */

const toDateInputValue = (raw: unknown, kind: "date" | "datetime" | "time"): string => {
	if (raw == null || raw === "") {
		return "";
	}

	const s = String(raw);

	if (kind === "date") {
		return s.slice(0, 10);
	}

	if (kind === "time") {
		return s.slice(0, 5);
	}

	// datetime-local
	return s.includes("T") ? s.slice(0, 16) : s.replace(" ", "T").slice(0, 16);
};

interface DateLikeFieldProps extends FieldComponentProps {
	kind: "date" | "datetime" | "time";
}

export const DateLikeField = ({ field, value, onChange, disabled, kind }: DateLikeFieldProps) => {
	const inputType = kind === "datetime" ? "datetime-local" : kind;

	return (
		<input
			type={inputType}
			className={INPUT_CLASS}
			value={toDateInputValue(value, kind)}
			disabled={disabled}
			onChange={(event) => onChange(event.target.value)}
			aria-label={field.title}
		/>
	);
};
