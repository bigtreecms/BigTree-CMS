import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ChevronDown, ChevronRight, Edit, Search, Trash, X } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";

import { autoModulesApi, type ModuleEntryRow } from "@/api/endpoints/auto-modules";
import type { ModuleView } from "@/api/endpoints/modules";

import { formatCellValue, parseViewActions } from "./viewHelpers";

/**
 * Runtime for the `nested` view type.
 *
 * The legacy PHP admin renders this as a tree with per-parent AJAX loads.
 * Our `/modules/{id}/entries` endpoint doesn't expose nesting — it returns a
 * flat list. We approximate the tree client-side by reading a `parent`
 * column (configurable via `view.settings.parent_field`, defaults to
 * "parent") off each row.
 *
 * Limitation: if the legacy module relies on a column name other than
 * `parent`, the tree collapses to a flat list. Surface this as a follow-up
 * if a real-world module breaks. A proper nested endpoint
 * (GET /modules/{id}/entries?nested=1) is the right server-side fix.
 *
 * Search reverts to a flat list to match the legacy behavior — when a query
 * is active the tree disappears and matches show inline.
 */

interface NestedViewProps {
	moduleId: number;
	view: ModuleView;
}

interface TreeNode {
	row: ModuleEntryRow;
	children: TreeNode[];
}

const buildTree = (rows: ModuleEntryRow[], parentField: string): TreeNode[] => {
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

		const parentRaw = row[parentField];
		const parentKey =
			parentRaw === undefined || parentRaw === null || parentRaw === "" || parentRaw === 0 || parentRaw === "0"
				? null
				: String(parentRaw);

		if (parentKey && byId.has(parentKey)) {
			byId.get(parentKey)?.children.push(node);

			continue;
		}

		roots.push(node);
	}

	return roots;
};

export const NestedView = ({ moduleId, view }: NestedViewProps) => {
	const navigate = useNavigate();
	const [query, setQuery] = useState("");
	const [debouncedQuery, setDebouncedQuery] = useState("");
	const [expanded, setExpanded] = useState<Set<string>>(() => new Set());

	useEffect(() => {
		const handle = window.setTimeout(() => setDebouncedQuery(query), 200);

		return () => window.clearTimeout(handle);
	}, [query]);

	const listQuery = useQuery({
		queryKey: ["module-entries", moduleId, view.id, { q: debouncedQuery || undefined, view: view.id }] as const,
		queryFn: () =>
			autoModulesApi.list(moduleId, {
				view: view.id,
				q: debouncedQuery || undefined,
			}),
	});

	const settings = view.settings as Record<string, unknown> | undefined;
	const parentField = (settings?.parent_field as string) || "parent";

	const { builtins, custom } = useMemo(() => parseViewActions(view.actions), [view.actions]);
	const fieldColumns = useMemo(() => Object.entries(view.fields ?? {}), [view.fields]);

	const rows = listQuery.data?.items ?? [];
	const tree = useMemo(
		() => (debouncedQuery ? null : buildTree(rows, parentField)),
		[rows, parentField, debouncedQuery]
	);

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
								fieldColumns={fieldColumns}
								builtins={builtins}
								custom={custom}
								moduleId={moduleId}
								viewId={view.id}
							/>
						))}
					</ul>
				) : (
					// Search mode — flat list
					<ul className="divide-y divide-border">
						{rows.map((row) => (
							<NestedRow
								key={String(row.id)}
								node={{ row, children: [] }}
								depth={0}
								expanded={expanded}
								onToggle={toggle}
								onEdit={openEdit}
								fieldColumns={fieldColumns}
								builtins={builtins}
								custom={custom}
								moduleId={moduleId}
								viewId={view.id}
							/>
						))}
					</ul>
				)}
			</div>
		</>
	);
};

interface NestedRowProps {
	node: TreeNode;
	depth: number;
	expanded: Set<string>;
	onToggle: (id: string) => void;
	onEdit: (row: ModuleEntryRow) => void;
	fieldColumns: [string, { title: string }][];
	builtins: { edit: boolean; delete: boolean };
	custom: { key: string; name: string; route: string }[];
	moduleId: number;
	viewId: number;
}

const NestedRow = ({
	node,
	depth,
	expanded,
	onToggle,
	onEdit,
	fieldColumns,
	builtins,
	custom,
	moduleId,
	viewId,
}: NestedRowProps) => {
	const id = String(node.row.id);
	const isOpen = expanded.has(id);
	const hasChildren = node.children.length > 0;
	const indentPx = depth * 20;
	const firstColKey = fieldColumns[0]?.[0];

	return (
		<>
			<li
				className={`flex items-center gap-2 px-3 py-2 text-[13px] hover:bg-surface-2 ${
					builtins.edit ? "cursor-pointer" : ""
				}`}
				onClick={() => onEdit(node.row)}
			>
				<span
					className="flex-shrink-0"
					style={{ paddingLeft: `${indentPx}px` }}
				>
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
					{fieldColumns.map(([key]) => (
						<span
							key={key}
							className={`truncate text-text-2 ${key === firstColKey ? "font-medium text-text" : "flex-1"}`}
						>
							{formatCellValue(node.row[key])}
						</span>
					))}
				</div>

				<div className="flex items-center gap-1">
					{custom.map((action) => (
						<Link
							key={action.key}
							to={`/modules/${moduleId}/view/${viewId}/${action.route}/${node.row.id}`}
							className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
							title={action.name}
							onClick={(e) => e.stopPropagation()}
						>
							<span className="inline-block text-[11px] font-medium">
								{action.name.slice(0, 2)}
							</span>
						</Link>
					))}
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
					{builtins.delete && (
						<button
							type="button"
							className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
							title="Delete"
							onClick={(e) => e.stopPropagation()}
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
							fieldColumns={fieldColumns}
							builtins={builtins}
							custom={custom}
							moduleId={moduleId}
							viewId={viewId}
						/>
					))}
				</>
			)}
		</>
	);
};
