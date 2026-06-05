/**
 * Deterministic JSON serialization for structural equality checks. Object keys
 * are sorted recursively so two values that differ only in key insertion order
 * serialize identically — important for dirty-tracking, where form state is
 * rebuilt on every keystroke and key order is not guaranteed to be stable.
 *
 * Arrays keep their order (order is meaningful there). Non-plain values
 * (numbers, strings, null) pass through untouched.
 */
export const stableStringify = (value: unknown): string =>
	JSON.stringify(value, (_key, val) => {
		if (val && typeof val === "object" && !Array.isArray(val)) {
			const record = val as Record<string, unknown>;

			return Object.keys(record)
				.sort()
				.reduce<Record<string, unknown>>((acc, key) => {
					acc[key] = record[key];

					return acc;
				}, {});
		}

		return val;
	});
