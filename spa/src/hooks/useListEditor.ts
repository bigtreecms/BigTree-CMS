/**
 * The add / remove / update-at-index trio every `(value, onChange)` list editor
 * hand-rolled — media presets, resource/schema builders, view & report column
 * editors, and the field-settings controls all shipped the same three closures:
 *
 *   const update = (i, patch) => onChange(rows.map((r, j) => (j === i ? { ...r, ...patch } : r)));
 *   const remove = (i)        => onChange(rows.filter((_, j) => j !== i));
 *   const add    = (row)      => onChange([...rows, row]);
 *
 * `update` shallow-merges a patch into the row at `index`; `replace` swaps the
 * whole item (for primitive lists that can't be spread); `move` reorders one
 * item from → to (absorbing the adjacent move-up / move-down swap the schema
 * builders wrote as `move(index, dir)`), guarding out-of-range targets.
 */
export interface ListEditor<T> {
	add: (item: T) => void;
	/** Reorder the item at `from` to `to`; no-op if either index is out of range. */
	move: (from: number, to: number) => void;
	remove: (index: number) => void;
	/** Replace the whole item at `index` (for primitive/opaque rows). */
	replace: (index: number, item: T) => void;
	/** Shallow-merge `patch` into the object row at `index`. */
	update: (index: number, patch: Partial<T>) => void;
}

export const useListEditor = <T>(value: T[], onChange: (next: T[]) => void): ListEditor<T> => {
	const update = (index: number, patch: Partial<T>) =>
		onChange(value.map((item, i) => (i === index ? Object.assign({}, item, patch) : item)));

	const replace = (index: number, item: T) =>
		onChange(value.map((current, i) => (i === index ? item : current)));

	const remove = (index: number) => onChange(value.filter((_, i) => i !== index));

	const add = (item: T) => onChange([...value, item]);

	const move = (from: number, to: number) => {
		if (from === to || from < 0 || from >= value.length || to < 0 || to >= value.length) {
			return;
		}

		const next = [...value];
		const [moved] = next.splice(from, 1);
		next.splice(to, 0, moved as T);
		onChange(next);
	};

	return { update, replace, remove, add, move };
};
