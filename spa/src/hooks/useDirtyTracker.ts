import { useRef } from "react";

import { stableStringify } from "@/lib/stableStringify";

/**
 * Report whether a form's editable state has diverged from its pristine
 * baseline. The baseline is captured the first render where `ready` is true,
 * then every subsequent value is compared against it structurally.
 *
 * `ready` exists for edit screens that seed their state asynchronously: pass
 * `false` until the loaded record has populated the form, then `true`. Capturing
 * the baseline on that transition (rather than on mount) avoids a false-positive
 * dirty state from the initial empty → loaded jump. For add screens, where the
 * initial state is already pristine, leave `ready` at its default of `true`.
 *
 * `resetKey` re-captures the baseline whenever it changes. Screens that stay
 * open after a save and reseed from the saved record (e.g. "Save & Preview")
 * change it in the same render as the reseed, so the saved state becomes the
 * new pristine one. Screens that navigate away after saving can omit it.
 */
export const useDirtyTracker = (
	value: unknown,
	ready: boolean = true,
	resetKey?: unknown
): boolean => {
	const baselineRef = useRef<string | null>(null);
	const resetKeyRef = useRef<unknown>(resetKey);
	const serialized = stableStringify(value);

	if (!Object.is(resetKeyRef.current, resetKey)) {
		resetKeyRef.current = resetKey;
		baselineRef.current = null;
	}

	if (ready && baselineRef.current === null) {
		baselineRef.current = serialized;
	}

	if (baselineRef.current === null) {
		return false;
	}

	return serialized !== baselineRef.current;
};
