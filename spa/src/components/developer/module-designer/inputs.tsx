import { useMemo, useState } from "react";

import { Checkbox } from "../../ui/Checkbox";
import { Field } from "../../ui/Field";
import { Select } from "../../ui/Select";
import { TextArea } from "../../ui/TextArea";
import { TextInput as BaseTextInput } from "../../ui/TextInput";

/**
 * Thin, designer-flavored wrappers over the shared `ui/*` form primitives. The
 * module designer packs many fields per tab, so these default to the `dense`
 * density; otherwise they delegate label/error chrome to {@link Field} and the
 * control rendering to the canonical primitives (single source of truth — no
 * fork). The bespoke {@link JsonInput} below is the one control with no shared
 * equivalent.
 */

interface TextInputProps {
	label: string;
	value: string;
	onChange: (next: string) => void;
	hint?: string;
	error?: string;
	disabled?: boolean;
	required?: boolean;
	placeholder?: string;
	mono?: boolean;
}

export const TextInput = ({
	label,
	value,
	onChange,
	hint,
	error,
	disabled,
	required,
	placeholder,
	mono,
}: TextInputProps) => (
	<Field label={label} hint={hint} error={error} required={required}>
		<BaseTextInput
			dense
			mono={mono}
			value={value}
			onChange={(e) => onChange(e.target.value)}
			disabled={disabled}
			placeholder={placeholder}
		/>
	</Field>
);

interface SelectInputProps {
	label: string;
	value: string;
	onChange: (next: string) => void;
	options: Array<{ value: string; label: string }>;
	hint?: string;
	error?: string;
	disabled?: boolean;
}

export const SelectInput = ({
	label,
	value,
	onChange,
	options,
	hint,
	error,
	disabled,
}: SelectInputProps) => (
	<Field label={label} hint={hint} error={error}>
		<Select dense value={value} onChange={(e) => onChange(e.target.value)} disabled={disabled}>
			{options.map((o) => (
				<option key={o.value} value={o.value}>
					{o.label}
				</option>
			))}
		</Select>
	</Field>
);

interface CheckboxInputProps {
	label: string;
	checked: boolean;
	onChange: (next: boolean) => void;
	disabled?: boolean;
}

export const CheckboxInput = ({ label, checked, onChange, disabled }: CheckboxInputProps) => (
	<Checkbox label={label} checked={checked} onChange={onChange} disabled={disabled} />
);

interface TextareaInputProps {
	label: string;
	value: string;
	onChange: (next: string) => void;
	rows?: number;
	hint?: string;
	mono?: boolean;
}

export const TextareaInput = ({
	label,
	value,
	onChange,
	rows = 4,
	hint,
	mono,
}: TextareaInputProps) => (
	<Field label={label} hint={hint}>
		<TextArea
			mono={mono}
			rows={rows}
			value={value}
			onChange={(e) => onChange(e.target.value)}
			spellCheck={false}
			className="leading-relaxed"
		/>
	</Field>
);

interface JsonInputProps {
	label: string;
	value: unknown;
	onChange: (next: Record<string, unknown>) => void;
	hint?: string;
	rows?: number;
}

/**
 * JSON object editor for config blobs we don't have a bespoke UI for yet
 * (view actions, report filters, form hooks). Commits on blur and surfaces
 * a parse error inline; honest about what's stored in the legacy JSONDB.
 */
export const JsonInput = ({ label, value, onChange, hint, rows = 5 }: JsonInputProps) => {
	const initial = useMemo(() => safeStringify(value), [value]);
	const [draft, setDraft] = useState(initial);
	const [error, setError] = useState<string | null>(null);

	useMemo(() => {
		setDraft(initial);
		setError(null);
	}, [initial]);

	const commit = () => {
		try {
			const parsed = draft.trim() === "" ? {} : JSON.parse(draft);

			if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) {
				setError(null);
				onChange(parsed as Record<string, unknown>);
			} else {
				setError("Must be a JSON object.");
			}
		} catch (err) {
			setError(err instanceof Error ? err.message : "Invalid JSON");
		}
	};

	return (
		<Field
			label={
				<>
					{label} <span className="text-text-3">(JSON)</span>
				</>
			}
			hint={hint}
			error={error ?? undefined}
		>
			<TextArea
				mono
				rows={rows}
				value={draft}
				onChange={(e) => setDraft(e.target.value)}
				onBlur={commit}
				spellCheck={false}
				className="leading-relaxed"
			/>
		</Field>
	);
};

export const safeStringify = (value: unknown): string => {
	if (value === undefined || value === null) {
		return "{}";
	}

	if (Array.isArray(value) && value.length === 0) {
		return "{}";
	}

	try {
		return JSON.stringify(value, null, 2);
	} catch {
		return "{}";
	}
};
