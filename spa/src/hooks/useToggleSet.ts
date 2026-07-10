import { useCallback, useState } from "react";

/**
 * A `Set` of selected/expanded/collapsed keys with the `new Set(prev)` toggle
 * reducer baked in — the copy-pasted idiom every list view (grouped/nested
 * collapse, row selection, expander state) hand-rolled. `toggle` flips
 * membership; `has` reads it; `clear` empties it; `setSet` is the raw setter for
 * the rare site that needs a bulk replace (select-all).
 */
export const useToggleSet = <T>(initial?: Iterable<T>) => {
	const [set, setSet] = useState<Set<T>>(() => new Set(initial));

	const toggle = useCallback((item: T) => {
		setSet((prev) => {
			const next = new Set(prev);

			if (next.has(item)) {
				next.delete(item);
			} else {
				next.add(item);
			}

			return next;
		});
	}, []);

	const clear = useCallback(() => setSet(new Set()), []);

	const has = useCallback((item: T) => set.has(item), [set]);

	return { set, toggle, has, clear, setSet };
};
