import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ChevronDown, ChevronRight, Search, X } from "lucide-react";
import { useNavigate } from "react-router-dom";

import { autoModulesApi, type ModuleEntryRow } from "@/api/endpoints/auto-modules";
import type { ModuleView } from "@/api/endpoints/modules";

import { ImagesGrid } from "./ImagesView";
import { decodeHTMLEntities, isPersistedEntryId, parseViewActions } from "./viewHelpers";
import { useModuleEntryLinks } from "@/pages/ModuleLayout";

/**
 * Runtime for `images-grouped` — combines GroupedView's section-per-bucket
 * layout with ImagesView's thumbnail grid inside each section.
 */

interface ImagesGroupedViewProps {
	moduleId: string;
	view: ModuleView;
}

export const ImagesGroupedView = ({ moduleId, view }: ImagesGroupedViewProps) => {
	const navigate = useNavigate();
	const { editPath } = useModuleEntryLinks();
	const [query, setQuery] = useState("");
	const [debouncedQuery, setDebouncedQuery] = useState("");
	const [collapsed, setCollapsed] = useState<Set<string>>(() => new Set());

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
	const prefix = (settings?.prefix as string) || "";

	const { builtins, custom } = useMemo(() => parseViewActions(view.actions), [view.actions]);

	const rows = listQuery.data?.items ?? [];

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
									<span className="text-[11px] tabular-nums text-text-3">
										{items.length}
									</span>
								</button>

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
							</section>
						);
					})}
				</div>
			)}
		</>
	);
};
