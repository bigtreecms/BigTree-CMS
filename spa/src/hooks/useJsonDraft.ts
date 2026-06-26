import { useMemo, useState } from "react";

/** Safely serialise an unknown value to a pretty-printed JSON string, falling back to `"{}"`. */
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

/**
 * Shared state machine for JSON object editors. Stringifies the incoming value
 * into a draft string, resets on identity change, and commits on blur —
 * validating that the result is a non-array object.
 */
export const useJsonDraft = (
	value: unknown,
	onChange: (next: Record<string, unknown>) => void,
	invalidMessage = "Must be a JSON object."
) => {
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
				setError(invalidMessage);
			}
		} catch (err) {
			setError(err instanceof Error ? err.message : "Invalid JSON");
		}
	};

	return { draft, error, setDraft, commit };
};
