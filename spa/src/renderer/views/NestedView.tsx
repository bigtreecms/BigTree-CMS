import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronDown, ChevronRight } from "lucide-react";
import { useNavigate } from "react-router-dom";

import { DragHandle } from "@/components/ui/DragHandle";
import { Loading } from "@/components/ui/Loading";
import { SearchInput } from "@/components/ui/SearchInput";
import { Toolbar } from "@/components/ui/Toolbar";

import { autoModulesApi, type ModuleEntryRow } from "@/api/endpoints/auto-modules";
import { queryKeys } from "@/lib/queryKeys";
import type { ModuleView } from "@/api/endpoints/modules";
import { toast } from "@/lib/toast";

import {
	formatCellValue,
	isPersistedEntryId,
	parseViewActions,
	statusDimClass,
	statusFromRow,
	statusRowClass,
	type BuiltinViewActionFlags,
	type CustomViewAction,
} from "./viewHelpers";
import { ViewStatusBadge } from "./ViewStatusBadge";
import { RowActions } from "./RowActions";
import { useEntryDelete } from "./useEntryDelete";
import { useModuleEntryLinks } from "@/pages/ModuleLayout";

/**
 * Runtime for the `nested` view type.
 *
 * The legacy PHP admin renders this as a tree with per-parent AJAX loads.
 * Our `/modules/{id}/entries` endpoint returns a flat list of view-cache rows
 * — and for nested views, `BigTreeAutoModule::cacheViewData` copies the value
 * of `view.settings.nesting_column` into the cache's `group_field` column.
 * That's what we use to assemble the tree: a row's parent ID is `group_field`.
 *
 * Drag reorders siblings within the same parent. Cross-parent drops are
 * rejected to match the legacy admin (which uses jQuery UI sortable per-`<ul>`).
 *
 * Search reverts to a flat list to match legacy behavior — when a query is
 * active the tree collapses, matches show inline, and drag is disabled.
 */

interface NestedViewProps {
	moduleId: string;
	view: ModuleView;
}

interface TreeNode {
	row: ModuleEntryRow;
	children: TreeNode[];
}

const isEmptyParent = (value: unknown): boolean =>
	value === undefined || value === null || value === "" || value === 0 || value === "0";

const parentKeyOf = (row: ModuleEntryRow): string =>
	isEmptyParent(row.group_field) ? "" : String(row.group_field);

const buildTree = (rows: ModuleEntryRow[]): TreeNode[] => {
	const byId = new Map<string, TreeNode>();
	const roots: TreeNode[] = [];

	for (const row of rows) {
		byId.set(String(row.id), { row, children: [] });
	}

	for (const row of rows) {
		const node = byId.get(String(row.id));

		if (!node) {
			continue;
		}

		const parentKey = parentKeyOf(row);

		if (parentKey && byId.has(parentKey)) {
			byId.get(parentKey)?.children.push(node);

			continue;
		}

		roots.push(node);
	}

	return roots;
};

interface DragApi {
	canDrag: boolean;
	dragId: string | null;
	overId: string | null;
	onDragStart: (e: React.DragEvent, row: ModuleEntryRow) => void;
	onDragOver: (e: React.DragEvent, row: ModuleEntryRow) => void;
	onDrop: (e: React.DragEvent, row: ModuleEntryRow) => void;
	onDragEnd: () => void;
}

export const NestedView = ({ moduleId, view }: NestedViewProps) => {
	const navigate = useNavigate();
	const { editPath } = useModuleEntryLinks();
	const queryClient = useQueryClient();
	const [query, setQuery] = useState("");
	const [debouncedQuery, setDebouncedQuery] = useState("");
	const [expanded, setExpanded] = useState<Set<string>>(() => new Set());
	const [localRows, setLocalRows] = useState<ModuleEntryRow[] | null>(null);
	const [dragId, setDragId] = useState<string | null>(null);
	const [overId, setOverId] = useState<string | null>(null);
	const { requestDelete, dialog: deleteDialog } = useEntryDelete(moduleId, view.id);

	useEffect(() => {
		const handle = window.setTimeout(() => setDebouncedQuery(query), 200);

		return () => window.clearTimeout(handle);
	}, [query]);

	// Match the legacy nested view's sort: position DESC keeps drag-assigned
	// ordering, id ASC is the stable tiebreak. getSearchResults special-cases
	// this exact string (see auto-modules.php:1482).
	const listQuery = useQuery({
		queryKey: queryKeys.moduleEntries.viewQuery(moduleId, view.id, { q: debouncedQuery || undefined, view: view.id }),
		queryFn: () =>
			autoModulesApi.list(moduleId, {
				view: view.id,
				q: debouncedQuery || undefined,
				sort: "position DESC, id ASC",
			}),
	});

	useEffect(() => {
		if (listQuery.data) {
			setLocalRows(listQuery.data.items);
		}
	}, [listQuery.data]);

	const reorderMutation = useMutation({
		mutationFn: (ids: Array<string | number>) => autoModulesApi.reorder(moduleId, ids, view.id),
		onError: () => {
			toast.error("Couldn't save the new order");
			queryClient.invalidateQueries({
				queryKey: queryKeys.moduleEntries.view(moduleId, view.id),
			});
		},
	});

	const { builtins, custom } = useMemo(() => parseViewActions(view.actions), [view.actions]);
	const fieldColumns = useMemo(() => Object.entries(view.fields ?? {}), [view.fields]);

	const rows = useMemo(
		() => localRows ?? listQuery.data?.items ?? [],
		[localRows, listQuery.data?.items]
	);
	const tree = useMemo(() => (debouncedQuery ? null : buildTree(rows)), [rows, debouncedQuery]);

	const toggle = (id: string) => {
		setExpanded((prev) => {
			const next = new Set(prev);

			if (next.has(id)) {
				next.delete(id);
			} else {
				next.add(id);
			}

			return next;
		});
	};

	const openEdit = (row: ModuleEntryRow) => {
		if (!builtins.edit || !isPersistedEntryId(row.id)) {
			return;
		}

		navigate(editPath(row.id));
	};

	const canDrag = !debouncedQuery && !listQuery.isLoading;

	const drag: DragApi = {
		canDrag,
		dragId,
		overId,
		onDragStart: (e, row) => {
			if (!canDrag) {
				return;
			}

			setDragId(String(row.id));
			e.dataTransfer.effectAllowed = "move";

			try {
				e.dataTransfer.setData("text/plain", String(row.id));
			} catch {
				// Some test environments block setData; safe to ignore
			}
		},
		onDragOver: (e, row) => {
			if (!dragId) {
				return;
			}

			const src = rows.find((r) => String(r.id) === dragId);

			if (!src || parentKeyOf(src) !== parentKeyOf(row)) {
				return;
			}

			e.preventDefault();
			e.dataTransfer.dropEffect = "move";
			const targetId = String(row.id);

			if (targetId !== overId) {
				setOverId(targetId);
			}
		},
		onDrop: (e, row) => {
			e.preventDefault();
			const sourceId = dragId;
			setDragId(null);
			setOverId(null);

			if (!sourceId || sourceId === String(row.id)) {
				return;
			}

			const src = rows.find((r) => String(r.id) === sourceId);

			if (!src || parentKeyOf(src) !== parentKeyOf(row)) {
				return;
			}

			const next = [...rows];
			const fromIdx = next.findIndex((r) => String(r.id) === sourceId);
			const toIdx = next.findIndex((r) => String(r.id) === String(row.id));

			if (fromIdx < 0 || toIdx < 0) {
				return;
			}

			const [moved] = next.splice(fromIdx, 1);

			if (!moved) {
				return;
			}

			next.splice(toIdx, 0, moved);
			setLocalRows(next);

			const parentKey = parentKeyOf(src);
			const siblingIds = next.filter((r) => parentKeyOf(r) === parentKey).map((r) => r.id);
			reorderMutation.mutate(siblingIds);
		},
		onDragEnd: () => {
			setDragId(null);
			setOverId(null);
		},
	};

	return (
		<>
			<Toolbar
				search={
					<SearchInput
						value={query}
						onChange={setQuery}
						placeholder={`Search ${view.title.toLowerCase()}…`}
						aria-label={`Search ${view.title.toLowerCase()}`}
					/>
				}
			/>

			{debouncedQuery && (
				<div className="mb-2 text-[11.5px] text-text-3">
					Reordering disabled while searching.
				</div>
			)}

			<div className="overflow-hidden rounded-xl border border-border bg-surface">
				{listQuery.isLoading && !listQuery.data ? (
					<Loading variant="block" label="Loading entries…" />
				) : rows.length === 0 ? (
					<div className="p-9 text-center text-[13px] text-text-3">
						{debouncedQuery
							? `No entries match “${debouncedQuery}”.`
							: "No entries yet."}
					</div>
				) : tree ? (
					<ul className="divide-y divide-border">
						{tree.map((node) => (
							<NestedRow
								key={String(node.row.id)}
								node={node}
								depth={0}
								expanded={expanded}
								onToggle={toggle}
								onEdit={openEdit}
								onDelete={requestDelete}
								fieldColumns={fieldColumns}
								builtins={builtins}
								custom={custom}
								moduleId={moduleId}
								viewId={view.id}
								drag={drag}
							/>
						))}
					</ul>
				) : (
					<ul className="divide-y divide-border">
						{rows.map((row) => (
							<NestedRow
								key={String(row.id)}
								node={{ row, children: [] }}
								depth={0}
								expanded={expanded}
								onToggle={toggle}
								onEdit={openEdit}
								onDelete={requestDelete}
								fieldColumns={fieldColumns}
								builtins={builtins}
								custom={custom}
								moduleId={moduleId}
								viewId={view.id}
								drag={drag}
							/>
						))}
					</ul>
				)}
			</div>

			{deleteDialog}
		</>
	);
};

interface NestedRowProps {
	node: TreeNode;
	depth: number;
	expanded: Set<string>;
	onToggle: (id: string) => void;
	onEdit: (row: ModuleEntryRow) => void;
	onDelete: (row: ModuleEntryRow) => void;
	fieldColumns: [string, { title: string }][];
	builtins: BuiltinViewActionFlags;
	custom: CustomViewAction[];
	moduleId: string;
	viewId: string;
	drag: DragApi;
}

const NestedRow = ({
	node,
	depth,
	expanded,
	onToggle,
	onEdit,
	onDelete,
	fieldColumns,
	builtins,
	custom,
	moduleId,
	viewId,
	drag,
}: NestedRowProps) => {
	const { editPath, actionPath } = useModuleEntryLinks();
	const id = String(node.row.id);
	const isOpen = expanded.has(id);
	const hasChildren = node.children.length > 0;
	const indentPx = depth * 20;
	const status = statusFromRow(node.row);
	const dim = statusDimClass(status.key);
	const isDragging = drag.dragId === id;
	const isOver = drag.overId === id && drag.dragId !== id;

	return (
		<>
			<li
				className={`flex items-center gap-2 px-3 py-2 text-[13px] hover:bg-surface-2 ${statusRowClass(
					status.key
				)} ${builtins.edit ? "cursor-pointer" : ""} ${
					isDragging ? "bg-accent-soft opacity-60" : ""
				} ${isOver ? "shadow-[inset_0_2px_0_0_var(--color-accent)]" : ""}`}
				draggable={drag.canDrag}
				onDragStart={(e) => drag.onDragStart(e, node.row)}
				onDragOver={(e) => drag.onDragOver(e, node.row)}
				onDrop={(e) => drag.onDrop(e, node.row)}
				onDragEnd={drag.onDragEnd}
				onClick={() => onEdit(node.row)}
			>
				<span
					className="flex shrink-0 items-center"
					style={{ paddingLeft: `${indentPx}px` }}
					onClick={(e) => e.stopPropagation()}
				>
					<DragHandle
						enabled={drag.canDrag}
						size={13}
						title={
							drag.canDrag ? "Drag to reorder within siblings" : "Reordering disabled"
						}
					/>

					{hasChildren ? (
						<button
							type="button"
							className="grid size-5 place-items-center rounded text-text-3 hover:bg-hover hover:text-text"
							onClick={(e) => {
								e.stopPropagation();
								onToggle(id);
							}}
							aria-label={isOpen ? "Collapse" : "Expand"}
						>
							{isOpen ? <ChevronDown size={13} /> : <ChevronRight size={13} />}
						</button>
					) : (
						<span className="inline-block size-5" />
					)}
				</span>

				<div className={`flex min-w-0 flex-1 items-center gap-4 ${dim}`}>
					{fieldColumns.map(([key], index) => {
						const valueKey = `column${index + 1}`;
						const isFirst = index === 0;

						return (
							<span
								key={key}
								className={`truncate text-text-2 ${isFirst ? "font-medium text-text" : "flex-1"}`}
							>
								{formatCellValue(node.row[valueKey])}
							</span>
						);
					})}
				</div>

				<ViewStatusBadge row={node.row} className="shrink-0" />

				<RowActions
					moduleId={moduleId}
					viewId={viewId}
					row={node.row}
					builtins={builtins}
					custom={custom}
					editPath={editPath}
					actionPath={actionPath}
					onDelete={onDelete}
					className={dim}
				/>
			</li>

			{hasChildren && isOpen && (
				<>
					{node.children.map((child) => (
						<NestedRow
							key={String(child.row.id)}
							node={child}
							depth={depth + 1}
							expanded={expanded}
							onToggle={onToggle}
							onEdit={onEdit}
							onDelete={onDelete}
							fieldColumns={fieldColumns}
							builtins={builtins}
							custom={custom}
							moduleId={moduleId}
							viewId={viewId}
							drag={drag}
						/>
					))}
				</>
			)}
		</>
	);
};
