import { Field } from "@/components/ui/Field";
import { inputClassFor } from "@/components/ui/TextInput";

type DateKind = "date" | "datetime" | "time";

/**
 * Normalize stored values (MySQL `Y-m-d H:i:s`, ISO, etc.) into what the native
 * `<input type="date|datetime-local|time">` expects.
 */
export const toDateInputValue = (raw: unknown, kind: DateKind): string => {
	if (raw == null || raw === "") {
		return "";
	}

	const s = String(raw).trim();

	if (kind === "date") {
		return s.slice(0, 10);
	}

	if (kind === "time") {
		// "14:30:00" or "14:30"
		const t = s.includes("T") ? s.split("T")[1] ?? s : s.includes(" ") ? s.split(" ")[1] ?? s : s;

		return t.slice(0, 5);
	}

	// datetime-local: "YYYY-MM-DDTHH:mm"
	if (s.includes("T")) {
		return s.slice(0, 16);
	}

	return s.replace(" ", "T").slice(0, 16);
};

/**
 * Expand a native input value back toward a MySQL-friendly string.
 * - date → `YYYY-MM-DD`
 * - time → `HH:mm:ss`
 * - datetime → `YYYY-MM-DD HH:mm:ss`
 */
export const fromDateInputValue = (raw: string, kind: DateKind): string => {
	if (!raw) {
		return "";
	}

	if (kind === "date") {
		return raw.slice(0, 10);
	}

	if (kind === "time") {
		const t = raw.slice(0, 5);

		return t.length === 5 ? `${t}:00` : t;
	}

	// "2026-05-26T14:30" → "2026-05-26 14:30:00"
	const normalized = raw.replace("T", " ").slice(0, 16);

	return normalized.length === 16 ? `${normalized}:00` : normalized;
};

interface DateFieldBaseProps {
	className?: string;
	dense?: boolean;
	disabled?: boolean;
	error?: string;
	hint?: string;
	label: string;
	/** Emits a normalized string (MySQL-friendly for datetime/time). */
	onChange: (next: string) => void;
	required?: boolean;
	/** Stored / MySQL / ISO value. */
	value: string;
}

const DateLikeControl = ({
	kind,
	label,
	value,
	onChange,
	hint,
	error,
	required,
	disabled,
	dense,
	className,
}: DateFieldBaseProps & { kind: DateKind }) => {
	const inputType = kind === "datetime" ? "datetime-local" : kind;
	const base = inputClassFor({ dense });

	return (
		<Field className={className} error={error} hint={hint} label={label} required={required}>
			<input
				className={base}
				disabled={disabled}
				type={inputType}
				value={toDateInputValue(value, kind)}
				onChange={(e) => onChange(fromDateInputValue(e.target.value, kind))}
			/>
		</Field>
	);
};

/** Labeled `<input type="date">` for custom actions. */
export const DateField = (props: DateFieldBaseProps) => <DateLikeControl kind="date" {...props} />;

/** Labeled `<input type="datetime-local">` — open/close scheduling, publish at, etc. */
export const DateTimeField = (props: DateFieldBaseProps) => (
	<DateLikeControl kind="datetime" {...props} />
);

/** Labeled `<input type="time">`. */
export const TimeField = (props: DateFieldBaseProps) => <DateLikeControl kind="time" {...props} />;
