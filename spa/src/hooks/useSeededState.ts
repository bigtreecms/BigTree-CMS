import { useEffect, useRef, useState } from "react";

/**
 * Runs `seed(data)` once the first time `data` is defined. Subsequent changes
 * to `data` (e.g. background refetches) do not re-run seed — so in-progress
 * form edits survive query invalidation.
 *
 * Returns `seeded` (true after the first successful seed) for dirty trackers
 * that must not mark "pristine vs empty" as dirty before the form is filled.
 */
export function useSeededState<T>(data: T | undefined, seed: (data: T) => void): boolean {
	const [seeded, setSeeded] = useState(false);
	const seedRef = useRef(seed);
	seedRef.current = seed;

	useEffect(() => {
		if (data === undefined || seeded) {
			return;
		}

		seedRef.current(data);
		setSeeded(true);
	}, [data, seeded]);

	return seeded;
}
