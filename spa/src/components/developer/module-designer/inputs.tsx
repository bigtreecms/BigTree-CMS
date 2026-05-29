import { useMemo, useState } from "react";

/**
 * Small form primitives shared across the module designer tabs. Kept local to
 * the designer so the broader UI library isn't grown for a single section.
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
	<label className="block">
		<span className="mb-1 block text-[12px] font-medium text-text-2">
			{label}
			{required && <span className="text-danger"> *</span>}
		</span>
		<input
			type="text"
			value={value}
			onChange={(e) => onChange(e.target.value)}
			disabled={disabled}
			placeholder={placeholder}
			className={`w-full rounded-md border border-border bg-surface px-3 py-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring disabled:cursor-not-allowed disabled:opacity-60 ${
				mono ? "font-mono text-[12px]" : ""
			}`}
		/>
		{hint && <span className="mt-1 block text-[11px] text-text-3">{hint}</span>}
		{error && <span className="mt-1 block text-[11.5px] text-danger">{error}</span>}
	</label>
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
	<label className="block">
		<span className="mb-1 block text-[12px] font-medium text-text-2">{label}</span>
		<select
			value={value}
			onChange={(e) => onChange(e.target.value)}
			disabled={disabled}
			className="w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring disabled:opacity-50"
		>
			{options.map((o) => (
				<option key={o.value} value={o.value}>
					{o.label}
				</option>
			))}
		</select>
		{hint && <span className="mt-1 block text-[11px] text-text-3">{hint}</span>}
		{error && <span className="mt-1 block text-[11.5px] text-danger">{error}</span>}
	</label>
);

interface CheckboxInputProps {
	label: string;
	checked: boolean;
	onChange: (next: boolean) => void;
	disabled?: boolean;
}

export const CheckboxInput = ({ label, checked, onChange, disabled }: CheckboxInputProps) => (
	<label className="flex items-center gap-2 text-[12.5px] text-text-2">
		<input
			type="checkbox"
			className="h-4 w-4 accent-accent"
			checked={checked}
			disabled={disabled}
			onChange={(e) => onChange(e.target.checked)}
		/>
		{label}
	</label>
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
	<label className="block">
		<span className="mb-1 block text-[12px] font-medium text-text-2">{label}</span>
		<textarea
			rows={rows}
			value={value}
			onChange={(e) => onChange(e.target.value)}
			spellCheck={false}
			className={`w-full rounded-md border border-border bg-surface px-3 py-2 text-[13px] leading-relaxed focus:outline-none focus:ring-1 focus:ring-accent-ring ${
				mono ? "font-mono text-[11.5px]" : ""
			}`}
		/>
		{hint && <span className="mt-1 block text-[11px] text-text-3">{hint}</span>}
	</label>
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
		<label className="block">
			<span className="mb-1 block text-[12px] font-medium text-text-2">
				{label} <span className="text-text-3">(JSON)</span>
			</span>
			<textarea
				rows={rows}
				value={draft}
				onChange={(e) => setDraft(e.target.value)}
				onBlur={commit}
				spellCheck={false}
				className="w-full rounded-md border border-border bg-surface px-3 py-2 font-mono text-[11.5px] leading-relaxed focus:outline-none focus:ring-1 focus:ring-accent-ring"
			/>
			{hint && <span className="mt-1 block text-[11px] text-text-3">{hint}</span>}
			{error && <span className="mt-1 block text-[11.5px] text-danger">{error}</span>}
		</label>
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
