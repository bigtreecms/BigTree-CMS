import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronDown, ChevronRight, Edit, GripVertical, Search, Trash, X } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";

import { autoModulesApi, type ModuleEntryRow } from "@/api/endpoints/auto-modules";
import type { ModuleView } from "@/api/endpoints/modules";
import { toast } from "@/lib/toast";

import {
	formatCellValue,
	iconForCustomAction,
	parseViewActions,
	type BuiltinViewActionFlags,
	type CustomViewAction,
} from "./viewHelpers";
import { BuiltinToggleButtons } from "./BuiltinToggleButtons";
import { useEntryDelete } from "./useEntryDelete";

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

type StatusKey = "published" | "pending" | "changed" | "inactive";

const statusFromRow = (row: ModuleEntryRow): { label: string; key: StatusKey } => {
	const raw = typeof row.status === "string" ? row.status : "";

	if (raw === "p") {
		return { label: "Pending", key: "pending" };
	}

	if (raw === "c") {
		return { label: "Changed", key: "changed" };
	}

	if (raw === "i") {
		return { label: "Inactive", key: "inactive" };
	}

	return { label: "Published", key: "published" };
};

const statusClassName: Record<StatusKey, string> = {
	published: "text-success",
	pending: "text-warning",
	changed: "text-warning",
	inactive: "text-text-3",
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
		queryKey: ["module-entries", moduleId, view.id, { q: debouncedQuery || undefined, view: view.id }] as const,
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
				queryKey: ["module-entries", moduleId, view.id],
			});
		},
	});

	const { builtins, custom } = useMemo(() => parseViewActions(view.actions), [view.actions]);
	const fieldColumns = useMemo(() => Object.entries(view.fields ?? {}), [view.fields]);

	const rows = localRows ?? listQuery.data?.items ?? [];
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
		if (!builtins.edit) {
			return;
		}

		const entryId = Number(row.id);

		if (Number.isFinite(entryId) && entryId > 0) {
			navigate(`/modules/${moduleId}/view/${view.id}/edit/${entryId}`);
		}
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
			<div className="mb-3 flex flex-wrap items-center gap-3">
				<div className="relative max-w-md flex-1">
					<Search
						size={14}
						className="absolute left-3 top-1/2 -translate-y-1/2 text-text-3"
					/>
					<input
						className="w-full rounded-md border border-border bg-surface py-1.5 pl-9 pr-9 text-[13.5px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring"
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
				<div className="mb-2 text-[11.5px] text-text-3">Reordering disabled while searching.</div>
			)}

			<div className="overflow-hidden rounded-xl border border-border bg-surface">
				{listQuery.isLoading && !listQuery.data ? (
					<div className="p-9 text-center text-[13px] text-text-3">Loading entries…</div>
				) : rows.length === 0 ? (
					<div className="p-9 text-center text-[13px] text-text-3">
						{debouncedQuery ? `No entries match “${debouncedQuery}”.` : "No entries yet."}
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
	const id = String(node.row.id);
	const isOpen = expanded.has(id);
	const hasChildren = node.children.length > 0;
	const indentPx = depth * 20;
	const status = statusFromRow(node.row);
	const isDragging = drag.dragId === id;
	const isOver = drag.overId === id && drag.dragId !== id;

	return (
		<>
			<li
				className={`flex items-center gap-2 px-3 py-2 text-[13px] hover:bg-surface-2 ${
					builtins.edit ? "cursor-pointer" : ""
				} ${isDragging ? "bg-accent-soft opacity-60" : ""} ${
					isOver ? "shadow-[inset_0_2px_0_0_var(--color-accent)]" : ""
				}`}
				draggable={drag.canDrag}
				onDragStart={(e) => drag.onDragStart(e, node.row)}
				onDragOver={(e) => drag.onDragOver(e, node.row)}
				onDrop={(e) => drag.onDrop(e, node.row)}
				onDragEnd={drag.onDragEnd}
				onClick={() => onEdit(node.row)}
			>
				<span
					className="flex flex-shrink-0 items-center"
					style={{ paddingLeft: `${indentPx}px` }}
					onClick={(e) => e.stopPropagation()}
				>
					<span
						className={`grid h-5 w-5 place-items-center rounded text-text-4 ${
							drag.canDrag
								? "cursor-grab hover:bg-hover hover:text-text-2 active:cursor-grabbing"
								: "cursor-default opacity-25"
						}`}
						title={drag.canDrag ? "Drag to reorder within siblings" : "Reordering disabled"}
						aria-hidden="true"
					>
						<GripVertical size={13} />
					</span>

					{hasChildren ? (
						<button
							type="button"
							className="grid h-5 w-5 place-items-center rounded text-text-3 hover:bg-hover hover:text-text"
							onClick={(e) => {
								e.stopPropagation();
								onToggle(id);
							}}
							aria-label={isOpen ? "Collapse" : "Expand"}
						>
							{isOpen ? <ChevronDown size={13} /> : <ChevronRight size={13} />}
						</button>
					) : (
						<span className="inline-block h-5 w-5" />
					)}
				</span>

				<div className="flex min-w-0 flex-1 items-center gap-4">
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

				<span
					className={`flex-shrink-0 text-[11px] font-semibold uppercase tracking-[0.06em] ${statusClassName[status.key]}`}
				>
					{status.label}
				</span>

				<div className="flex items-center gap-1">
					{custom.map((action) => {
						const Icon = iconForCustomAction(action.className);

						return (
							<Link
								key={action.key}
								to={`/modules/${moduleId}/view/${viewId}/${action.route}/${node.row.id}`}
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
							to={`/modules/${moduleId}/view/${viewId}/edit/${node.row.id}`}
							className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
							title="Edit"
							onClick={(e) => e.stopPropagation()}
						>
							<Edit size={15} />
						</Link>
					)}
					<BuiltinToggleButtons
						moduleId={moduleId}
						viewId={viewId}
						row={node.row}
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
								onDelete(node.row);
							}}
						>
							<Trash size={15} />
						</button>
					)}
				</div>
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
