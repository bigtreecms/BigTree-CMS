import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ChevronDown, ChevronRight, Edit, Trash } from "lucide-react";
import { useNavigate } from "react-router-dom";

import { IconButton } from "@/components/ui/IconButton";
import { SearchInput } from "@/components/ui/SearchInput";
import { autoModulesApi, type ModuleEntryRow } from "@/api/endpoints/auto-modules";
import type { ModuleView } from "@/api/endpoints/modules";

import {
	decodeHTMLEntities,
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
	const { editPath, actionPath } = useModuleEntryLinks();
	const [query, setQuery] = useState("");
	const [debouncedQuery, setDebouncedQuery] = useState("");
	const [collapsed, setCollapsed] = useState<Set<string>>(() => new Set());
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
	});

	const settings = view.settings as Record<string, unknown> | undefined;
	const groupField = (settings?.group_field as string) || "";
	const titleOverrides = specialGroupTitles[groupField];

	const { builtins, custom } = useMemo(() => parseViewActions(view.actions), [view.actions]);
	const fieldColumns = useMemo(() => Object.entries(view.fields ?? {}), [view.fields]);

	const rows = useMemo(() => listQuery.data?.items ?? [], [listQuery.data?.items]);
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
		if (!builtins.edit || !isPersistedEntryId(row.id)) {
			return;
		}

		navigate(editPath(row.id));
	};

	return (
		<>
			<div className="mb-3 flex flex-wrap items-center gap-3">
				<SearchInput
					className="w-full sm:w-auto sm:max-w-md sm:flex-1"
					value={query}
					onChange={setQuery}
					placeholder={`Search ${view.title.toLowerCase()}…`}
					aria-label={`Search ${view.title.toLowerCase()}`}
				/>
			</div>

			{!groupField && (
				<div className="mb-3 rounded-md border border-border bg-surface-2 px-3 py-2 text-[12.5px] text-text-2">
					This grouped view doesn't have a group column configured — showing as a flat
					list.
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
									<span className="text-[11px] tabular-nums text-text-3">
										{items.length}
									</span>
								</button>

								{!isCollapsed && (
									<ul className="divide-y divide-border">
										{items.map((row) => {
											const status = statusFromRow(row);
											const dim = statusDimClass(status.key);

											return (
												<li
													key={String(row.id)}
													className={`flex items-center gap-4 px-3 py-2 text-[13px] hover:bg-surface-2 ${statusRowClass(
														status.key
													)} ${builtins.edit ? "cursor-pointer" : ""}`}
													onClick={() => openEdit(row)}
												>
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
																		isFirst
																			? "font-medium text-text"
																			: "flex-1"
																	}`}
																>
																	{formatCellValue(row[valueKey])}
																</span>
															);
														})}
													</div>

													<ViewStatusBadge
														row={row}
														className="shrink-0"
													/>

													<div
														className={`flex items-center gap-1 ${dim}`}
													>
														{custom.map((action) => {
															const Icon = iconForCustomAction(
																action.className
															);

															return (
																<IconButton
																	key={action.key}
																	to={actionPath(
																		action.route,
																		row.id
																	)}
																	label={action.name}
																	title={action.name}
																	onClick={(e) =>
																		e.stopPropagation()
																	}
																>
																	<Icon size={15} />
																</IconButton>
															);
														})}
														{builtins.edit && (
															<IconButton
																to={editPath(row.id)}
																label="Edit"
																title="Edit"
																onClick={(e) => e.stopPropagation()}
															>
																<Edit size={15} />
															</IconButton>
														)}
														<BuiltinToggleButtons
															moduleId={moduleId}
															viewId={view.id}
															row={row}
															builtins={builtins}
														/>
														{builtins.delete && (
															<IconButton
																label="Delete"
																title="Delete"
																tone="danger"
																onClick={(e) => {
																	e.stopPropagation();
																	requestDelete(row);
																}}
															>
																<Trash size={15} />
															</IconButton>
														)}
													</div>
												</li>
											);
										})}
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
