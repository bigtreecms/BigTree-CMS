/**
 * Scroll-to-first-error support shared across every form in the SPA.
 *
 * The house style surfaces field problems through a `fieldErrors` map
 * (`Record<column, message>`) that each field wrapper renders inline. On a long
 * form the offending field can be well below the fold, so a failed submit looks
 * like nothing happened. To fix that without coupling the scroll logic to any
 * one form, each field wrapper tags its rendered error element with the
 * `data-field-error` attribute and the helpers here find the first such element
 * (in DOM — i.e. visual — order) and bring it into view.
 *
 * Use the `useScrollToFirstError` hook (see hooks/useScrollToFirstError) to wire
 * this up declaratively against a `fieldErrors` state.
 */

/** Attribute every field wrapper sets on its rendered error element. */
export const FIELD_ERROR_ATTR = "data-field-error";

/**
 * Find the first error element (in document order) within `container` (defaults
 * to the whole document), scroll it into view, and move focus to the nearest
 * focusable control so keyboard users land on the offending field.
 *
 * Returns `true` when an error element was found and scrolled, `false` when the
 * form had no rendered errors (e.g. the error sits on a tab that isn't mounted).
 */
export const scrollToFirstError = (container?: HTMLElement | Document | null): boolean => {
	const root = container ?? document;
	const errorEl = root.querySelector<HTMLElement>(`[${FIELD_ERROR_ATTR}]`);

	if (!errorEl) {
		return false;
	}

	// Frame the whole field, not just the small error text below it.
	const target = errorEl.closest<HTMLElement>("label, .field, [data-field]") ?? errorEl;

	target.scrollIntoView({ behavior: "smooth", block: "center" });

	const focusable = target.querySelector<HTMLElement>(
		"input, select, textarea, [contenteditable='true'], [tabindex]"
	);

	if (focusable) {
		// Don't fight the smooth scroll — focus without re-scrolling.
		focusable.focus({ preventScroll: true });
	}

	return true;
};
