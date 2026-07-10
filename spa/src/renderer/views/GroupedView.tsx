import { useMemo } from "react";

import { useToggleSet } from "@/hooks/useToggleSet";

import { Card } from "@/components/ui/Card";
import { DisclosureToggle } from "@/components/ui/DisclosureToggle";
import { EmptyState } from "@/components/ui/EmptyState";
import { Loading } from "@/components/ui/Loading";
import { QueryRenderer } from "@/components/ui/QueryRenderer";
import { SearchInput } from "@/components/ui/SearchInput";
import { Toolbar } from "@/components/ui/Toolbar";
import type { ModuleEntryRow } from "@/api/endpoints/auto-modules";
import type { ModuleView } from "@/api/endpoints/modules";

import {
	decodeHTMLEntities,
	statusDimClass,
	statusFromRow,
	statusRowClass,
	viewEmptyLabel,
} from "./viewHelpers";
import { ViewStatusBadge } from "./ViewStatusBadge";
import { ViewRowCells } from "./ViewRowCells";
import { RowActions } from "./RowActions";
import { useEntryDelete } from "./useEntryDelete";
import { useModuleEntryLinks } from "@/pages/ModuleLayout";
import { useModuleEntries } from "./useModuleEntries";

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
	const { editPath, actionPath } = useModuleEntryLinks();
	const { set: collapsed, toggle } = useToggleSet<string>();
	const { requestDelete, dialog: deleteDialog } = useEntryDelete(moduleId, view.id);
	const {
		query,
		setQuery,
		debouncedQuery,
		builtins,
		custom,
		fieldColumns,
		listQuery,
		rows,
		openEdit,
	} = useModuleEntries({ moduleId, view });

	const settings = view.settings as Record<string, unknown> | undefined;
	const groupField = (settings?.group_field as string) || "";
	const titleOverrides = specialGroupTitles[groupField];

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

			{!groupField && (
				<div className="mb-3 rounded-md border border-border bg-surface-2 px-3 py-2 text-[12.5px] text-text-2">
					This grouped view doesn't have a group column configured — showing as a flat
					list.
				</div>
			)}

			<QueryRenderer
				isLoading={listQuery.isLoading && !listQuery.data}
				error={listQuery.error}
				isEmpty={rows.length === 0}
				loading={<Loading variant="card" label="Loading entries…" />}
				empty={<EmptyState>{viewEmptyLabel(debouncedQuery)}</EmptyState>}
			>
				<div className="flex flex-col gap-4">
					{groups.map(([groupKey, { title, items }]) => {
						const isCollapsed = collapsed.has(groupKey);

						return (
							<Card as="section" key={groupKey} className="overflow-hidden">
								<DisclosureToggle
									open={!isCollapsed}
									onToggle={() => toggle(groupKey)}
									className="w-full gap-2 border-b border-border bg-surface-2 px-3.5 py-2"
									label={
										<h3 className="text-[11px] font-semibold uppercase tracking-[0.08em] text-text">
											{title}
										</h3>
									}
								>
									<span className="text-[11px] tabular-nums text-text-3">
										{items.length}
									</span>
								</DisclosureToggle>

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
													<ViewRowCells
														fieldColumns={fieldColumns}
														row={row}
														dim={dim}
													/>

													<ViewStatusBadge
														row={row}
														className="shrink-0"
													/>

													<RowActions
														moduleId={moduleId}
														viewId={view.id}
														row={row}
														builtins={builtins}
														custom={custom}
														editPath={editPath}
														actionPath={actionPath}
														onDelete={requestDelete}
														className={dim}
													/>
												</li>
											);
										})}
									</ul>
								)}
							</Card>
						);
					})}
				</div>
			</QueryRenderer>

			{deleteDialog}
		</>
	);
};
