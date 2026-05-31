import { useMemo, useState } from "react";

interface JsonFallbackControlProps {
	value: Record<string, unknown> | unknown[] | undefined;
	onChange: (next: Record<string, unknown>) => void;
}

const safeStringify = (value: unknown): string => {
	if (value === undefined || value === null) {
		return "{}";
	}

	try {
		return JSON.stringify(value, null, 2);
	} catch {
		return "{}";
	}
};

/**
 * Raw-JSON settings editor. Used for custom / extension field types that don't
 * ship a settings schema, and as the always-available "Edit as JSON" escape
 * hatch for schema-driven types.
 */
export const JsonFallbackControl = ({ value, onChange }: JsonFallbackControlProps) => {
	const initial = useMemo(() => safeStringify(value), [value]);
	const [draft, setDraft] = useState(initial);
	const [error, setError] = useState<string | null>(null);

	// Reset draft when the parent value identity changes (e.g. row reorder).
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
				setError("Field settings must be a JSON object.");
			}
		} catch (err) {
			setError(err instanceof Error ? err.message : "Invalid JSON");
		}
	};

	return (
		<div>
			<textarea
				rows={6}
				className="w-full rounded-md border border-border bg-surface px-3 py-2 font-mono text-[11.5px] leading-relaxed focus:outline-none focus:ring-1 focus:ring-accent-ring"
				value={draft}
				onChange={(e) => setDraft(e.target.value)}
				onBlur={commit}
				spellCheck={false}
			/>
			{error && <div className="mt-1 text-[11.5px] text-danger">{error}</div>}
		</div>
	);
};
