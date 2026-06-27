import { useState } from "react";

/**
 * Manages the open/closed state of an inline add or edit form within a list
 * or panel — avoids repeating the same `useState(false)` + handlers pattern.
 */
export const useInlineForm = (initial = false) => {
	const [open, setOpen] = useState(initial);

	return {
		open,
		show: () => setOpen(true),
		hide: () => setOpen(false),
		toggle: () => setOpen((v) => !v),
	};
};
