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

import { ImagesGrid } from "./ImagesView";
import { decodeHTMLEntities, viewEmptyLabel } from "./viewHelpers";
import { useModuleEntries } from "./useModuleEntries";

/**
 * Runtime for `images-grouped` — combines GroupedView's section-per-bucket
 * layout with ImagesView's thumbnail grid inside each section.
 */

interface ImagesGroupedViewProps {
	moduleId: string;
	view: ModuleView;
}

export const ImagesGroupedView = ({ moduleId, view }: ImagesGroupedViewProps) => {
	const { set: collapsed, toggle } = useToggleSet<string>();
	const { query, setQuery, debouncedQuery, builtins, custom, listQuery, rows, openEdit } =
		useModuleEntries({ moduleId, view });

	const settings = view.settings as Record<string, unknown> | undefined;
	const prefix = (settings?.prefix as string) || "";

	// The view cache always stores the bucket value in a fixed `group_field`
	// column (see BigTreeAutoModule::cacheRecord), regardless of which source
	// column the view's `settings.group_field` points at — so we read from
	// that positional key, not the field name.
	const groups = useMemo(() => {
		const byGroup = new Map<string, ModuleEntryRow[]>();

		for (const row of rows) {
			const raw = row.group_field;
			const key = raw == null || raw === "" ? "—" : decodeHTMLEntities(String(raw));
			const bucket = byGroup.get(key) ?? [];
			bucket.push(row);
			byGroup.set(key, bucket);
		}

		return Array.from(byGroup.entries()).sort(([a], [b]) => a.localeCompare(b));
	}, [rows]);

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

			<QueryRenderer
				isLoading={listQuery.isLoading && !listQuery.data}
				error={listQuery.error}
				isEmpty={rows.length === 0}
				loading={<Loading variant="card" label="Loading entries…" />}
				empty={<EmptyState>{viewEmptyLabel(debouncedQuery)}</EmptyState>}
			>
				<div className="flex flex-col gap-4">
					{groups.map(([groupKey, items]) => {
						const isCollapsed = collapsed.has(groupKey);

						return (
							<Card as="section" key={groupKey} className="overflow-hidden">
								<DisclosureToggle
									open={!isCollapsed}
									onToggle={() => toggle(groupKey)}
									className="w-full gap-2 border-b border-border bg-surface-2 px-3.5 py-2"
									label={
										<h3 className="text-[11px] font-semibold uppercase tracking-[0.08em] text-text">
											{groupKey}
										</h3>
									}
								>
									<span className="text-[11px] tabular-nums text-text-3">
										{items.length}
									</span>
								</DisclosureToggle>

								{!isCollapsed && (
									<div className="p-3">
										<ImagesGrid
											rows={items}
											moduleId={moduleId}
											viewId={view.id}
											prefix={prefix}
											onClick={openEdit}
											canEdit={builtins.edit}
											canDelete={builtins.delete}
											customActions={custom}
										/>
									</div>
								)}
							</Card>
						);
					})}
				</div>
			</QueryRenderer>
		</>
	);
};
