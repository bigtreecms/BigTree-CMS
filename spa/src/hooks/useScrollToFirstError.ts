import { useEffect, useRef } from "react";

import { scrollToFirstError } from "@/lib/scrollToFirstError";

/**
 * Auto-scroll a form to its first field error whenever new errors appear.
 *
 * Pass the form's `fieldErrors` map. The hook scrolls on the render where the
 * error count grows (or holds steady at a non-zero count, i.e. a resubmit) —
 * covering both client-side required-field validation and a server 422 — but
 * stays put while the user clears errors by editing fields. The scroll is
 * deferred to the next animation frame so it runs after the error elements (and
 * any tab switch the submit handler triggered) have painted.
 *
 * Field wrappers opt in by tagging their rendered error element with the
 * `data-field-error` attribute; see lib/scrollToFirstError.
 */
export const useScrollToFirstError = (fieldErrors: Record<string, string>): void => {
	const prevCount = useRef(0);

	useEffect(() => {
		const count = Object.keys(fieldErrors).length;

		if (count > 0 && count >= prevCount.current) {
			const frame = requestAnimationFrame(() => scrollToFirstError());

			prevCount.current = count;

			return () => cancelAnimationFrame(frame);
		}

		prevCount.current = count;
	}, [fieldErrors]);
};
