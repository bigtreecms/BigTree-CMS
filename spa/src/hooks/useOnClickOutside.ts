import { useEffect, type RefObject } from "react";

/**
 * Calls `handler` when a pointer-down lands outside `ref`. The de-duplicated copy
 * of the close-on-outside-click effect that every popover control (Combobox,
 * LinkField, RelationField, IconSelect, LinkFinder, TagInput) hand-rolled.
 *
 * Pass `enabled` (usually the popover's `open` state) so the listener is only
 * attached while the surface is visible — matches the existing `if (!open) return`
 * early-out in every call-site.
 */
export const useOnClickOutside = (
	ref: RefObject<HTMLElement | null>,
	handler: () => void,
	enabled = true
) => {
	useEffect(() => {
		if (!enabled) {
			return;
		}

		const listener = (event: MouseEvent) => {
			if (ref.current && !ref.current.contains(event.target as Node)) {
				handler();
			}
		};

		document.addEventListener("mousedown", listener);

		return () => document.removeEventListener("mousedown", listener);
	}, [ref, handler, enabled]);
};
