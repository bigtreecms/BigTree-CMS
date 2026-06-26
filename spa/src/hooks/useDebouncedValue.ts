import { useEffect, useState } from "react";

/**
 * Returns a debounced copy of `value` that only updates after `delay` ms of
 * inactivity. Useful for deferring search API calls until the user pauses typing.
 */
export const useDebouncedValue = <T>(value: T, delay = 200): T => {
	const [debounced, setDebounced] = useState<T>(value);

	useEffect(() => {
		const handle = setTimeout(() => setDebounced(value), delay);

		return () => clearTimeout(handle);
	}, [value, delay]);

	return debounced;
};
