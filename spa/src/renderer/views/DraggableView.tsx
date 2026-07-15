import { useEffect, useState } from "react";

import { Card } from "@/components/ui/Card";
import { DragHandle } from "@/components/ui/DragHandle";
import { Loading } from "@/components/ui/Loading";
import { QueryRenderer } from "@/components/ui/QueryRenderer";
import { SearchInput } from "@/components/ui/SearchInput";
import { Toolbar } from "@/components/ui/Toolbar";

import type { ModuleEntryRow } from "@/api/endpoints/auto-modules";
import type { ModuleView } from "@/api/endpoints/modules";
import { useDragReorder } from "@/hooks/useDragReorder";

import { statusDimClass, statusFromRow, statusRowClass, viewEmptyLabel } from "./viewHelpers";
import { ViewStatusBadge } from "./ViewStatusBadge";
import { ViewRowCells } from "./ViewRowCells";
import { RowActions } from "./RowActions";
import { useEntryDelete } from "./useEntryDelete";
import { useModuleEntryReorder } from "./useModuleEntryReorder";
import { useModuleEntryLinks } from "@/pages/ModuleLayout";
import { useModuleEntries } from "./useModuleEntries";

/**
 * Runtime for the `draggable` view type. Flat list ordered by position; the
 * leading grip cell starts an HTML5 drag, and POST /modules/{id}/entries/reorder
 * persists the new ordering on drop.
 *
 *   - When a search query is active the legacy admin disables drag (it would
 *     reorder the filtered subset only); we mirror that behavior.
 *   - Reorder is optimistic: we splice the local array, fire the API call,
 *     and revert on failure with a toast.
 *   - Pagination is intentionally omitted — draggable views are meant to be
 *     wholly-orderable, so the legacy admin doesn't paginate them either.
 */

interface DraggableViewProps {
	moduleId: string;
	view: ModuleView;
}

interface DraggableRow {
	id: string | number;
	row: ModuleEntryRow;
}

export const DraggableView = ({ moduleId, view }: DraggableViewProps) => {
	const { editPath, actionPath } = useModuleEntryLinks();
	const [localRows, setLocalRows] = useState<DraggableRow[] | null>(null);
	const { requestDelete, dialog: deleteDialog } = useEntryDelete(moduleId, view.id);
	const { query, setQuery, debouncedQuery, builtins, custom, fieldColumns, listQuery, openEdit } =
		useModuleEntries({ moduleId, view, keepPrevious: true });

	useEffect(() => {
		if (listQuery.data) {
			setLocalRows(
				listQuery.data.items.map((row) => ({ id: row.id as string | number, row }))
			);
		}
	}, [listQuery.data]);

	const rows = localRows ?? [];

	const reorderMutation = useModuleEntryReorder(moduleId, view.id);

	const drag = useDragReorder<DraggableRow, string | number>(
		rows,
		(next) => setLocalRows(next),
		(orderedIds) => {
			reorderMutation.mutate(orderedIds);
		}
	);

	const canDrag = !debouncedQuery && !listQuery.isLoading;

	return (
		<>
			<Toolbar
				search={
					<SearchInput
						aria-label={`Search ${view.title.toLowerCase()}`}
						placeholder={`Search ${view.title.toLowerCase()}…`}
						value={query}
						onChange={setQuery}
					/>
				}
			/>

			{debouncedQuery && (
				<div className="mb-2 text-[11.5px] text-text-3">
					Reordering disabled while searching.
				</div>
			)}

			<Card className="overflow-hidden">
				<QueryRenderer
					empty={
						<div className="p-9 text-center text-[13px] text-text-3">
							{viewEmptyLabel(debouncedQuery)}
						</div>
					}
					error={listQuery.error}
					isEmpty={rows.length === 0}
					isLoading={listQuery.isLoading && !listQuery.data}
					loading={<Loading label="Loading entries…" variant="block" />}
				>
					<ul>
						{rows.map((r) => {
							const isDragging = drag.dragId === r.id;
							const isOver = drag.overId === r.id && drag.dragId !== r.id;
							const status = statusFromRow(r.row);
							const dim = statusDimClass(status.key);

							return (
								<li
									className={`flex items-center gap-2 border-b border-border px-3 py-2 text-[13px] last:border-b-0 hover:bg-surface-2 ${statusRowClass(
										status.key
									)} ${isDragging ? "bg-accent-soft shadow-md" : ""} ${
										isOver ? "shadow-[inset_0_2px_0_0_var(--color-accent)]" : ""
									}`}
									draggable={canDrag}
									key={String(r.id)}
									onClick={() => openEdit(r.row)}
									onDragEnd={drag.onDragEnd}
									onDragOver={(e) => drag.onDragOver(e, r.id)}
									onDragStart={(e) => drag.onDragStart(e, r.id)}
									onDrop={drag.onDrop}
								>
									<DragHandle
										enabled={canDrag}
										title={canDrag ? "Drag to reorder" : "Reordering disabled"}
										onClick={(e) => e.stopPropagation()}
									/>

									<ViewRowCells
										dim={dim}
										fieldColumns={fieldColumns}
										row={r.row}
									/>

									<ViewStatusBadge className="shrink-0" row={r.row} />

									<RowActions
										actionPath={actionPath}
										builtins={builtins}
										className={dim}
										custom={custom}
										editPath={editPath}
										moduleId={moduleId}
										row={r.row}
										viewId={view.id}
										onDelete={requestDelete}
									/>
								</li>
							);
						})}
					</ul>
				</QueryRenderer>
			</Card>

			{deleteDialog}
		</>
	);
};
