import { useEffect, useState } from "react";

import { isRecord } from "@/renderer/fields/fieldHelpers";

/**
 * Shared row engine for the repeating-group field renderers (`MatrixField`,
 * `CalloutsField`, `MediaGalleryField`). Each row pairs the caller's `data`
 * payload with an ephemeral UI `uid` used for React keys and reordering; the
 * uid is stripped before the value reaches `onChange`, so the wire payload
 * stays a plain `data[]` array — matching the legacy storage shape.
 *
 * The re-seed effect is echo-guarded: it only regenerates rows when the
 * incoming `value` genuinely differs (by data payload) from the rows already
 * held, so a record switch / reset replaces them but the echo of our own
 * `onChange` leaves row uids — and the expand state keyed on them — intact.
 */

/** A repeater row: an ephemeral UI `uid` paired with the row's data payload. */
export interface RepeaterRow<T> {
	data: T;
	uid: string;
}

// Monotonic across every repeater instance on the page — uids only need to be
// unique within a single field, and a shared counter guarantees that cheaply.
let uidCounter = 0;

const nextUid = (prefix: string): string => `${prefix}${++uidCounter}-${Date.now().toString(36)}`;

const seedRows = <T extends Record<string, unknown>>(
	raw: unknown,
	prefix: string
): RepeaterRow<T>[] => {
	if (!Array.isArray(raw)) {
		return [];
	}

	return raw.filter(isRecord).map((data) => ({ uid: nextUid(prefix), data: { ...data } as T }));
};

/**
 * Structural equality of two row sets by their data payloads (ignoring the
 * ephemeral uids), so we can tell our own edit's echo from an external change.
 */
const rowsDataEqual = <T>(a: RepeaterRow<T>[], b: RepeaterRow<T>[]): boolean => {
	if (a.length !== b.length) {
		return false;
	}

	for (let i = 0; i < a.length; i++) {
		if (JSON.stringify(a[i]?.data) !== JSON.stringify(b[i]?.data)) {
			return false;
		}
	}

	return true;
};

interface UseRepeaterRowsArgs {
	/** Upper bound on the row count (`0` = unlimited). */
	max?: number;
	/** Field `onChange` — receives the uid-stripped `data[]`. */
	onChange: (next: unknown) => void;
	/** Prefix letter for generated uids (purely cosmetic; e.g. `"m"`/`"c"`/`"g"`). */
	uidPrefix: string;
	/** The controlled field value (an array of row data; anything else → `[]`). */
	value: unknown;
}

export interface UseRepeaterRowsReturn<T> {
	/** Append a new row with `data`, auto-expanding it. No-op at the limit. */
	add: (data: T) => void;
	atLimit: boolean;
	isExpanded: (uid: string) => boolean;
	move: (index: number, direction: "up" | "down") => void;
	remove: (uid: string) => void;
	rows: RepeaterRow<T>[];
	toggleExpanded: (uid: string) => void;
	/** Shallow-merge `patch` into the row's data. */
	update: (uid: string, patch: Partial<T>) => void;
	/** Replace the row's data via an updater (for nested merges). */
	updateWith: (uid: string, updater: (data: T) => T) => void;
}

export const useRepeaterRows = <T extends Record<string, unknown>>({
	value,
	onChange,
	uidPrefix,
	max = 0,
}: UseRepeaterRowsArgs): UseRepeaterRowsReturn<T> => {
	const [rows, setRows] = useState<RepeaterRow<T>[]>(() => seedRows<T>(value, uidPrefix));
	const [expanded, setExpanded] = useState<Set<string>>(new Set());

	useEffect(() => {
		setRows((prev) => {
			const incoming = seedRows<T>(value, uidPrefix);

			return rowsDataEqual(prev, incoming) ? prev : incoming;
		});
	}, [value, uidPrefix]);

	const commit = (next: RepeaterRow<T>[]) => {
		setRows(next);
		onChange(next.map((r) => r.data));
	};

	const atLimit = max > 0 && rows.length >= max;

	const add = (data: T) => {
		if (atLimit) {
			return;
		}

		const fresh: RepeaterRow<T> = { uid: nextUid(uidPrefix), data };
		commit([...rows, fresh]);
		setExpanded((prev) => new Set(prev).add(fresh.uid));
	};

	const remove = (uid: string) => {
		commit(rows.filter((r) => r.uid !== uid));
		setExpanded((prev) => {
			const copy = new Set(prev);
			copy.delete(uid);

			return copy;
		});
	};

	const move = (index: number, direction: "up" | "down") => {
		const swapWith = direction === "up" ? index - 1 : index + 1;

		if (swapWith < 0 || swapWith >= rows.length) {
			return;
		}

		const next = [...rows];
		const removed = next.splice(index, 1)[0];

		if (removed) {
			next.splice(swapWith, 0, removed);
			commit(next);
		}
	};

	const updateWith = (uid: string, updater: (data: T) => T) => {
		commit(rows.map((r) => (r.uid === uid ? { ...r, data: updater(r.data) } : r)));
	};

	const update = (uid: string, patch: Partial<T>) => {
		updateWith(uid, (data) => ({ ...data, ...patch }) as T);
	};

	const toggleExpanded = (uid: string) => {
		setExpanded((prev) => {
			const copy = new Set(prev);

			if (copy.has(uid)) {
				copy.delete(uid);
			} else {
				copy.add(uid);
			}

			return copy;
		});
	};

	const isExpanded = (uid: string) => expanded.has(uid);

	return {
		rows,
		isExpanded,
		toggleExpanded,
		atLimit,
		add,
		remove,
		move,
		update,
		updateWith,
	};
};
