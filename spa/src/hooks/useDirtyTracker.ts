import { useRef } from "react";

import { stableStringify } from "@/lib/stableStringify";

/**
 * Line endings folded to "\n" throughout. Text stored with "\r\n" (content
 * saved by the legacy admin, or pasted from Windows) comes back from any
 * textarea or rich text editor with "\n" — the browser normalizes it — which
 * isn't an edit.
 */
const normalizeLineEndings = (value: unknown): unknown => {
	if (typeof value === "string") {
		return value.includes("\r") ? value.replace(/\r\n?/g, "\n") : value;
	}

	if (Array.isArray(value)) {
		return value.map(normalizeLineEndings);
	}

	if (value && typeof value === "object") {
		return Object.fromEntries(
			Object.entries(value as Record<string, unknown>).map(([key, entry]) => [
				key,
				normalizeLineEndings(entry),
			])
		);
	}

	return value;
};

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
 * Line endings don't count: "\r\n" and "\n" compare equal.
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
	const serialized = stableStringify(normalizeLineEndings(value));

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
