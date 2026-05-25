import { useCallback, useState } from "react";

/**
 * Generic HTML5 drag-reorder hook ported from the prototype's `useDragReorder`.
 *
 * Usage:
 *   const drag = useDragReorder<MyRow>(rows, setRows, (a, b) => myCommit(b));
 *   ...
 *   <tr draggable
 *       onDragStart={(e) => drag.onDragStart(e, row.id)}
 *       onDragOver={(e) => drag.onDragOver(e, row.id)}
 *       onDrop={drag.onDrop}
 *       onDragEnd={drag.onDragEnd}>
 *
 * The hook handles the move locally (optimistic), then calls `onCommit` with
 * the new ordered array so the caller can persist to the server. If the
 * server call fails, revert by calling setItems back with the original order.
 */
export interface DragReorderApi<Id extends string | number> {
	dragId: Id | null;
	overId: Id | null;
	onDragStart: (e: React.DragEvent, id: Id) => void;
	onDragOver: (e: React.DragEvent, id: Id) => void;
	onDrop: (e: React.DragEvent) => void;
	onDragEnd: () => void;
}

/**
 * Minimal constraint: T just needs an `id` field of the chosen type. We
 * deliberately don't add an index signature — that would force every other
 * property on T to be `unknown`, which doesn't match real DTOs.
 */
export function useDragReorder<
	T extends { id: Id },
	Id extends string | number = number,
>(
	items: T[],
	setItems: (next: T[]) => void,
	onCommit?: (orderedIds: Id[]) => void,
): DragReorderApi<Id> {
	const [dragId, setDragId] = useState<Id | null>(null);
	const [overId, setOverId] = useState<Id | null>(null);

	const onDragStart = useCallback((e: React.DragEvent, id: Id) => {
		setDragId(id);
		e.dataTransfer.effectAllowed = "move";
		// Firefox requires setData to actually start a drag
		try {
			e.dataTransfer.setData("text/plain", String(id));
		} catch {
			// Some test environments block setData; safe to ignore
		}
	}, []);

	const onDragOver = useCallback(
		(e: React.DragEvent, id: Id) => {
			e.preventDefault();
			if (id !== overId) setOverId(id);
		},
		[overId],
	);

	const onDrop = useCallback(
		(e: React.DragEvent) => {
			e.preventDefault();
			if (dragId === null || overId === null || dragId === overId) {
				setDragId(null);
				setOverId(null);
				return;
			}
			const fromIdx = items.findIndex((x) => x.id === dragId);
			const toIdx = items.findIndex((x) => x.id === overId);
			if (fromIdx < 0 || toIdx < 0) {
				setDragId(null);
				setOverId(null);
				return;
			}
			const next = [...items];
			const removed = next.splice(fromIdx, 1)[0];
			if (removed !== undefined) {
				next.splice(toIdx, 0, removed);
			}
			setItems(next);
			if (onCommit) onCommit(next.map((r) => r.id));
			setDragId(null);
			setOverId(null);
		},
		[dragId, overId, items, setItems, onCommit],
	);

	const onDragEnd = useCallback(() => {
		setDragId(null);
		setOverId(null);
	}, []);

	return { dragId, overId, onDragStart, onDragOver, onDrop, onDragEnd };
}
