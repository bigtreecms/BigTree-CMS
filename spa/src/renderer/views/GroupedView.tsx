import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ChevronDown, ChevronRight, Edit, Search, Trash, X } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";

import { autoModulesApi, type ModuleEntryRow } from "@/api/endpoints/auto-modules";
import type { ModuleView } from "@/api/endpoints/modules";

import {
	decodeHTMLEntities,
	formatCellValue,
	iconForCustomAction,
	parseViewActions,
} from "./viewHelpers";
import { BuiltinToggleButtons } from "./BuiltinToggleButtons";
import { useEntryDelete } from "./useEntryDelete";

/**
 * Runtime for the `grouped` view type. Rows are bucketed by their cached
 * `group_field` column (populated by BigTreeAutoModule::cacheRecord from
 * `view.settings.group_field`). The server returns a `groups` map that
 * resolves the raw key (often a foreign-key id) to the display title via
 * the view's `other_table` — without it, group headers would render as
 * the bare numeric id. Section order follows the server map's insertion
 * order so `ot_sort_field` is honored.
 *
 * Default expansion state: all expanded. Search hides empty groups.
 */

interface GroupedViewProps {
	moduleId: string;
	view: ModuleView;
}

// Legacy `_common-js.php` overrides the displayed title when grouping by one
// of the built-in BigTree status columns — the cached value is "on" / "" and
// not human-readable on its own.
const specialGroupTitles: Record<string, Record<string, string>> = {
	featured: { on: "Featured", "": "Normal" },
	archived: { on: "Archived", "": "Active" },
	approved: { on: "Approved", "": "Not Approved" },
};

export const GroupedView = ({ moduleId, view }: GroupedViewProps) => {
	const navigate = useNavigate();
	const [query, setQuery] = useState("");
	const [debouncedQuery, setDebouncedQuery] = useState("");
	const [collapsed, setCollapsed] = useState<Set<string>>(() => new Set());
	const { requestDelete, dialog: deleteDialog } = useEntryDelete(moduleId, view.id);

	useEffect(() => {
		const handle = window.setTimeout(() => setDebouncedQuery(query), 200);

		return () => window.clearTimeout(handle);
	}, [query]);

	const listQuery = useQuery({
		queryKey: ["module-entries", moduleId, view.id, { q: debouncedQuery || undefined, view: view.id }] as const,
		queryFn: () => autoModulesApi.list(moduleId, { view: view.id, q: debouncedQuery || undefined }),
	});

	const settings = view.settings as Record<string, unknown> | undefined;
	const groupField = (settings?.group_field as string) || "";
	const titleOverrides = specialGroupTitles[groupField];

	const { builtins, custom } = useMemo(() => parseViewActions(view.actions), [view.actions]);
	const fieldColumns = useMemo(() => Object.entries(view.fields ?? {}), [view.fields]);

	const rows = listQuery.data?.items ?? [];
	const groupTitles = listQuery.data?.groups;

	const groups = useMemo(() => {
		const byGroup = new Map<string, { title: string; items: ModuleEntryRow[] }>();
		const titleFor = (rawKey: string): string => {
			const override = titleOverrides?.[rawKey];

			if (override !== undefined) {
				return override;
			}

			const resolved = groupTitles?.[rawKey];

			if (resolved !== undefined && resolved !== "") {
				return decodeHTMLEntities(resolved);
			}

			return rawKey === "" ? "—" : decodeHTMLEntities(rawKey);
		};

		// Seed buckets in server-provided order so `ot_sort_field` is preserved.
		if (groupTitles) {
			for (const rawKey of Object.keys(groupTitles)) {
				byGroup.set(rawKey, { title: titleFor(rawKey), items: [] });
			}
		}

		for (const row of rows) {
			const raw = row.group_field;
			const rawKey = raw == null ? "" : String(raw);
			const bucket = byGroup.get(rawKey) ?? { title: titleFor(rawKey), items: [] };
			bucket.items.push(row);
			byGroup.set(rawKey, bucket);
		}

		return Array.from(byGroup.entries()).filter(([, { items }]) => items.length > 0);
	}, [rows, groupTitles, titleOverrides]);

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
					{groups.map(([groupKey, { title, items }]) => {
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
										{title}
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
																{formatCellValue(row[valueKey])}
															</span>
														);
													})}
												</div>

												<div className="flex items-center gap-1">
													{custom.map((action) => {
														const Icon = iconForCustomAction(action.className);

														return (
															<Link
																key={action.key}
																to={`/modules/${moduleId}/view/${view.id}/${action.route}/${row.id}`}
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
															to={`/modules/${moduleId}/view/${view.id}/edit/${row.id}`}
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
														row={row}
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
																requestDelete(row);
															}}
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

			{deleteDialog}
		</>
	);
};
