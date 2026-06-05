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
 * The baseline is captured once and never moves on its own — after a successful
 * save the form typically navigates away, so there's no need to re-baseline.
 */
export const useDirtyTracker = (value: unknown, ready: boolean = true): boolean => {
	const baselineRef = useRef<string | null>(null);
	const serialized = stableStringify(value);

	if (ready && baselineRef.current === null) {
		baselineRef.current = serialized;
	}

	if (baselineRef.current === null) {
		return false;
	}

	return serialized !== baselineRef.current;
};
