import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ChevronDown, ChevronRight, Edit, Search, Trash, X } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";

import { autoModulesApi, type ModuleEntryRow } from "@/api/endpoints/auto-modules";
import type { ModuleView } from "@/api/endpoints/modules";

import { decodeHTMLEntities, formatCellValue, parseViewActions } from "./viewHelpers";

/**
 * Runtime for the `grouped` view type. Entries are bucketed by a column
 * configured in `view.settings.group_field` (legacy admin reads it from
 * a few keys — `group_field`, `other_table_field`, `group_by`). Each
 * bucket is its own collapsible section.
 *
 * Default expansion state: all expanded. Search hides empty groups.
 */

interface GroupedViewProps {
	moduleId: string;
	view: ModuleView;
}

export const GroupedView = ({ moduleId, view }: GroupedViewProps) => {
	const navigate = useNavigate();
	const [query, setQuery] = useState("");
	const [debouncedQuery, setDebouncedQuery] = useState("");
	const [collapsed, setCollapsed] = useState<Set<string>>(() => new Set());

	useEffect(() => {
		const handle = window.setTimeout(() => setDebouncedQuery(query), 200);

		return () => window.clearTimeout(handle);
	}, [query]);

	const listQuery = useQuery({
		queryKey: ["module-entries", moduleId, view.id, { q: debouncedQuery || undefined, view: view.id }] as const,
		queryFn: () => autoModulesApi.list(moduleId, { view: view.id, q: debouncedQuery || undefined }),
	});

	const settings = view.settings as Record<string, unknown> | undefined;
	const groupField =
		(settings?.group_field as string) ||
		(settings?.other_table_field as string) ||
		(settings?.group_by as string) ||
		"";

	const { builtins, custom } = useMemo(() => parseViewActions(view.actions), [view.actions]);
	const fieldColumns = useMemo(() => Object.entries(view.fields ?? {}), [view.fields]);
	const firstColKey = fieldColumns[0]?.[0];

	const rows = listQuery.data?.items ?? [];

	const groups = useMemo(() => {
		const byGroup = new Map<string, ModuleEntryRow[]>();

		for (const row of rows) {
			const raw = groupField ? row[groupField] : "";
			const key =
				raw == null || raw === "" ? "—" : decodeHTMLEntities(String(raw));
			const bucket = byGroup.get(key) ?? [];
			bucket.push(row);
			byGroup.set(key, bucket);
		}

		return Array.from(byGroup.entries()).sort(([a], [b]) => a.localeCompare(b));
	}, [rows, groupField]);

	const toggle = (key: string) => {
		setCollapsed((prev) => {
			const next = new Set(prev);

			if (next.has(key)) {
				next.delete(key);
			} else {
				next.add(key);
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
					<Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-text-3" />
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

			{!groupField && (
				<div className="mb-3 rounded-md border border-border bg-surface-2 px-3 py-2 text-[12.5px] text-text-2">
					This grouped view doesn't have a group column configured — showing as a flat list.
				</div>
			)}

			{listQuery.isLoading && !listQuery.data ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading entries…
				</div>
			) : rows.length === 0 ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					{debouncedQuery ? `No entries match “${debouncedQuery}”.` : "No entries yet."}
				</div>
			) : (
				<div className="flex flex-col gap-4">
					{groups.map(([groupKey, items]) => {
						const isCollapsed = collapsed.has(groupKey);

						return (
							<section
								key={groupKey}
								className="overflow-hidden rounded-xl border border-border bg-surface"
							>
								<button
									type="button"
									className="flex w-full items-center gap-2 border-b border-border bg-surface-2 px-3.5 py-2 text-left"
									onClick={() => toggle(groupKey)}
								>
									{isCollapsed ? (
										<ChevronRight size={13} className="text-text-3" />
									) : (
										<ChevronDown size={13} className="text-text-3" />
									)}
									<h3 className="text-[11px] font-semibold uppercase tracking-[0.08em] text-text">
										{groupKey}
									</h3>
									<span className="text-[11px] tabular-nums text-text-3">{items.length}</span>
								</button>

								{!isCollapsed && (
									<ul className="divide-y divide-border">
										{items.map((row) => (
											<li
												key={String(row.id)}
												className={`flex items-center gap-4 px-3 py-2 text-[13px] hover:bg-surface-2 ${
													builtins.edit ? "cursor-pointer" : ""
												}`}
												onClick={() => openEdit(row)}
											>
												<div className="flex min-w-0 flex-1 items-center gap-4">
													{fieldColumns.map(([key]) => (
														<span
															key={key}
															className={`truncate text-text-2 ${
																key === firstColKey ? "font-medium text-text" : "flex-1"
															}`}
														>
															{formatCellValue(row[key])}
														</span>
													))}
												</div>

												<div className="flex items-center gap-1">
													{custom.map((action) => (
														<Link
															key={action.key}
															to={`/modules/${moduleId}/view/${view.id}/${action.route}/${row.id}`}
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
															to={`/modules/${moduleId}/view/${view.id}/edit/${row.id}`}
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
										))}
									</ul>
								)}
							</section>
						);
					})}
				</div>
			)}
		</>
	);
};
