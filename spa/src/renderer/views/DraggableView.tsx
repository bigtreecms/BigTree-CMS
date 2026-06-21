import { useEffect, useMemo, useState } from "react";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Edit, GripVertical, Search, Trash, X } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";

import { autoModulesApi, type ModuleEntryRow } from "@/api/endpoints/auto-modules";
import type { ModuleView } from "@/api/endpoints/modules";
import { useDragReorder } from "@/hooks/useDragReorder";
import { toast } from "@/lib/toast";

import {
	formatCellValue,
	iconForCustomAction,
	isPersistedEntryId,
	parseViewActions,
	statusDimClass,
	statusFromRow,
	statusRowClass,
} from "./viewHelpers";
import { ViewStatusBadge } from "./ViewStatusBadge";
import { BuiltinToggleButtons } from "./BuiltinToggleButtons";
import { useEntryDelete } from "./useEntryDelete";
import { useModuleEntryLinks } from "@/pages/ModuleLayout";

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
	const navigate = useNavigate();
	const { editPath, actionPath } = useModuleEntryLinks();
	const queryClient = useQueryClient();
	const [query, setQuery] = useState("");
	const [debouncedQuery, setDebouncedQuery] = useState("");
	const [localRows, setLocalRows] = useState<DraggableRow[] | null>(null);
	const { requestDelete, dialog: deleteDialog } = useEntryDelete(moduleId, view.id);

	useEffect(() => {
		const handle = window.setTimeout(() => setDebouncedQuery(query), 200);

		return () => window.clearTimeout(handle);
	}, [query]);

	const listQuery = useQuery({
		queryKey: [
			"module-entries",
			moduleId,
			view.id,
			{ q: debouncedQuery || undefined, view: view.id },
		] as const,
		queryFn: () =>
			autoModulesApi.list(moduleId, { view: view.id, q: debouncedQuery || undefined }),
		placeholderData: keepPreviousData,
	});

	useEffect(() => {
		if (listQuery.data) {
			setLocalRows(
				listQuery.data.items.map((row) => ({ id: row.id as string | number, row }))
			);
		}
	}, [listQuery.data]);

	const rows = localRows ?? [];

	const reorderMutation = useMutation({
		mutationFn: (ids: Array<string | number>) => autoModulesApi.reorder(moduleId, ids, view.id),
		onError: () => {
			toast.error("Couldn't save the new order");
			// Refetch to restore the server's truth.
			queryClient.invalidateQueries({
				queryKey: ["module-entries", moduleId, view.id],
			});
		},
	});

	const drag = useDragReorder<DraggableRow, string | number>(
		rows,
		(next) => setLocalRows(next),
		(orderedIds) => {
			reorderMutation.mutate(orderedIds);
		}
	);

	const canDrag = !debouncedQuery && !listQuery.isLoading;

	const { builtins, custom } = useMemo(() => parseViewActions(view.actions), [view.actions]);
	const fieldColumns = useMemo(() => Object.entries(view.fields ?? {}), [view.fields]);

	const openEdit = (row: ModuleEntryRow) => {
		if (!builtins.edit || !isPersistedEntryId(row.id)) {
			return;
		}

		navigate(editPath(row.id));
	};

	return (
		<>
			<div className="mb-3 flex flex-wrap items-center gap-3">
				<div className="relative w-full sm:w-auto sm:max-w-md sm:flex-1">
					<Search
						size={14}
						className="absolute left-3 top-1/2 -translate-y-1/2 text-text-3"
					/>
					<input
						className="w-full rounded-md border border-border bg-surface py-1.5 px-9 text-[13.5px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring"
						placeholder={`Search ${view.title.toLowerCase()}…`}
						value={query}
						onChange={(e) => setQuery(e.target.value)}
					/>
					{query && (
						<button
							type="button"
							className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-text-3 hover:bg-hover hover:text-text"
							onClick={() => setQuery("")}
							aria-label="Clear search"
						>
							<X size={14} />
						</button>
					)}
				</div>
			</div>

			{debouncedQuery && (
				<div className="mb-2 text-[11.5px] text-text-3">
					Reordering disabled while searching.
				</div>
			)}

			<div className="overflow-hidden rounded-xl border border-border bg-surface">
				{listQuery.isLoading && !listQuery.data ? (
					<div className="p-9 text-center text-[13px] text-text-3">Loading entries…</div>
				) : rows.length === 0 ? (
					<div className="p-9 text-center text-[13px] text-text-3">
						{debouncedQuery
							? `No entries match “${debouncedQuery}”.`
							: "No entries yet."}
					</div>
				) : (
					<ul>
						{rows.map((r) => {
							const isDragging = drag.dragId === r.id;
							const isOver = drag.overId === r.id && drag.dragId !== r.id;
							const status = statusFromRow(r.row);
							const dim = statusDimClass(status.key);

							return (
								<li
									key={String(r.id)}
									className={`flex items-center gap-2 border-b border-border px-3 py-2 text-[13px] last:border-b-0 hover:bg-surface-2 ${statusRowClass(
										status.key
									)} ${isDragging ? "bg-accent-soft shadow-md" : ""} ${
										isOver ? "shadow-[inset_0_2px_0_0_var(--color-accent)]" : ""
									}`}
									draggable={canDrag}
									onDragStart={(e) => drag.onDragStart(e, r.id)}
									onDragOver={(e) => drag.onDragOver(e, r.id)}
									onDrop={drag.onDrop}
									onDragEnd={drag.onDragEnd}
									onClick={() => openEdit(r.row)}
								>
									<span
										className={`grid size-6  place-items-center rounded text-text-4 ${
											canDrag
												? "cursor-grab hover:bg-hover hover:text-text-2 active:cursor-grabbing"
												: "cursor-default opacity-25"
										}`}
										title={canDrag ? "Drag to reorder" : "Reordering disabled"}
										aria-hidden="true"
										onClick={(e) => e.stopPropagation()}
									>
										<GripVertical size={14} />
									</span>

									<div
										className={`flex min-w-0 flex-1 items-center gap-4 ${dim}`}
									>
										{fieldColumns.map(([key], index) => {
											const valueKey = `column${index + 1}`;
											const isFirst = index === 0;

											return (
												<span
													key={key}
													className={`truncate text-text-2 ${
														isFirst ? "font-medium text-text" : "flex-1"
													}`}
												>
													{formatCellValue(r.row[valueKey])}
												</span>
											);
										})}
									</div>

									<ViewStatusBadge row={r.row} className="shrink-0" />

									<div className={`flex items-center gap-1 ${dim}`}>
										{custom.map((action) => {
											const Icon = iconForCustomAction(action.className);

											return (
												<Link
													key={action.key}
													to={actionPath(action.route, r.row.id)}
													className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
													title={action.name}
													aria-label={action.name}
													onClick={(e) => e.stopPropagation()}
												>
													<Icon size={15} />
												</Link>
											);
										})}
										{builtins.edit && (
											<Link
												to={editPath(r.row.id)}
												className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
												title="Edit"
												onClick={(e) => e.stopPropagation()}
											>
												<Edit size={15} />
											</Link>
										)}
										<BuiltinToggleButtons
											moduleId={moduleId}
											viewId={view.id}
											row={r.row}
											builtins={builtins}
										/>
										{builtins.delete && (
											<button
												type="button"
												className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
												title="Delete"
												aria-label="Delete"
												onClick={(e) => {
													e.stopPropagation();
													requestDelete(r.row);
												}}
											>
												<Trash size={15} />
											</button>
										)}
									</div>
								</li>
							);
						})}
					</ul>
				)}
			</div>

			{deleteDialog}
		</>
	);
};
