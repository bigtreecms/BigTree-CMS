import { useEffect, useMemo, useState } from "react";

import { useToggleSet } from "@/hooks/useToggleSet";
import { ChevronDown, ChevronRight } from "lucide-react";

import { Card } from "@/components/ui/Card";
import { DragHandle } from "@/components/ui/DragHandle";
import { Loading } from "@/components/ui/Loading";
import { QueryRenderer } from "@/components/ui/QueryRenderer";
import { SearchInput } from "@/components/ui/SearchInput";
import { Toolbar } from "@/components/ui/Toolbar";

import type { ModuleEntryRow } from "@/api/endpoints/auto-modules";
import type { ModuleView } from "@/api/endpoints/modules";

import {
	statusDimClass,
	statusFromRow,
	statusRowClass,
	viewEmptyLabel,
	type BuiltinViewActionFlags,
	type CustomViewAction,
} from "./viewHelpers";
import { ViewStatusBadge } from "./ViewStatusBadge";
import { ViewRowCells } from "./ViewRowCells";
import { RowActions } from "./RowActions";
import { useEntryDelete } from "./useEntryDelete";
import { useModuleEntryReorder } from "./useModuleEntryReorder";
import { useModuleEntryLinks } from "@/pages/ModuleLayout";
import { useModuleEntries } from "./useModuleEntries";

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
	children: TreeNode[];
	row: ModuleEntryRow;
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
	onDragEnd: () => void;
	onDragOver: (e: React.DragEvent, row: ModuleEntryRow) => void;
	onDragStart: (e: React.DragEvent, row: ModuleEntryRow) => void;
	onDrop: (e: React.DragEvent, row: ModuleEntryRow) => void;
	overId: string | null;
}

export const NestedView = ({ moduleId, view }: NestedViewProps) => {
	const { set: expanded, toggle } = useToggleSet<string>();
	const [localRows, setLocalRows] = useState<ModuleEntryRow[] | null>(null);
	const [dragId, setDragId] = useState<string | null>(null);
	const [overId, setOverId] = useState<string | null>(null);
	const { requestDelete, dialog: deleteDialog } = useEntryDelete(moduleId, view.id);

	// Match the legacy nested view's sort: position DESC keeps drag-assigned
	// ordering, id ASC is the stable tiebreak. getSearchResults special-cases
	// this exact string (see auto-modules.php:1482).
	const { query, setQuery, debouncedQuery, builtins, custom, fieldColumns, listQuery, openEdit } =
		useModuleEntries({ moduleId, view, listParams: { sort: "position DESC, id ASC" } });

	useEffect(() => {
		if (listQuery.data) {
			setLocalRows(listQuery.data.items);
		}
	}, [listQuery.data]);

	const reorderMutation = useModuleEntryReorder(moduleId, view.id);

	const rows = useMemo(
		() => localRows ?? listQuery.data?.items ?? [],
		[localRows, listQuery.data?.items]
	);
	const tree = useMemo(() => (debouncedQuery ? null : buildTree(rows)), [rows, debouncedQuery]);

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
					{tree ? (
						<ul className="divide-y divide-border">
							{tree.map((node) => (
								<NestedRow
									builtins={builtins}
									custom={custom}
									depth={0}
									drag={drag}
									expanded={expanded}
									fieldColumns={fieldColumns}
									key={String(node.row.id)}
									moduleId={moduleId}
									node={node}
									viewId={view.id}
									onDelete={requestDelete}
									onEdit={openEdit}
									onToggle={toggle}
								/>
							))}
						</ul>
					) : (
						<ul className="divide-y divide-border">
							{rows.map((row) => (
								<NestedRow
									builtins={builtins}
									custom={custom}
									depth={0}
									drag={drag}
									expanded={expanded}
									fieldColumns={fieldColumns}
									key={String(row.id)}
									moduleId={moduleId}
									node={{ row, children: [] }}
									viewId={view.id}
									onDelete={requestDelete}
									onEdit={openEdit}
									onToggle={toggle}
								/>
							))}
						</ul>
					)}
				</QueryRenderer>
			</Card>

			{deleteDialog}
		</>
	);
};

interface NestedRowProps {
	builtins: BuiltinViewActionFlags;
	custom: CustomViewAction[];
	depth: number;
	drag: DragApi;
	expanded: Set<string>;
	fieldColumns: [string, { title: string }][];
	moduleId: string;
	node: TreeNode;
	onDelete: (row: ModuleEntryRow) => void;
	onEdit: (row: ModuleEntryRow) => void;
	onToggle: (id: string) => void;
	viewId: string;
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
				onClick={() => onEdit(node.row)}
				onDragEnd={drag.onDragEnd}
				onDragOver={(e) => drag.onDragOver(e, node.row)}
				onDragStart={(e) => drag.onDragStart(e, node.row)}
				onDrop={(e) => drag.onDrop(e, node.row)}
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
							aria-label={isOpen ? "Collapse" : "Expand"}
							className="grid size-5 place-items-center rounded text-text-3 hover:bg-hover hover:text-text"
							type="button"
							onClick={(e) => {
								e.stopPropagation();
								onToggle(id);
							}}
						>
							{isOpen ? <ChevronDown size={13} /> : <ChevronRight size={13} />}
						</button>
					) : (
						<span className="inline-block size-5" />
					)}
				</span>

				<ViewRowCells dim={dim} fieldColumns={fieldColumns} row={node.row} />

				<ViewStatusBadge className="shrink-0" row={node.row} />

				<RowActions
					actionPath={actionPath}
					builtins={builtins}
					className={dim}
					custom={custom}
					editPath={editPath}
					moduleId={moduleId}
					row={node.row}
					viewId={viewId}
					onDelete={onDelete}
				/>
			</li>

			{hasChildren && isOpen && (
				<>
					{node.children.map((child) => (
						<NestedRow
							builtins={builtins}
							custom={custom}
							depth={depth + 1}
							drag={drag}
							expanded={expanded}
							fieldColumns={fieldColumns}
							key={String(child.row.id)}
							moduleId={moduleId}
							node={child}
							viewId={viewId}
							onDelete={onDelete}
							onEdit={onEdit}
							onToggle={onToggle}
						/>
					))}
				</>
			)}
		</>
	);
};
