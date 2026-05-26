import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Image as ImageIcon, Search, X } from "lucide-react";
import { useNavigate } from "react-router-dom";

import { autoModulesApi, type ModuleEntryRow } from "@/api/endpoints/auto-modules";
import type { ModuleView } from "@/api/endpoints/modules";

import { formatCellValue, parseViewActions } from "./viewHelpers";

/**
 * Runtime for the `images` view type — a grid of thumbnail cards.
 *
 *   - `view.settings.image` names the column holding the image URL.
 *   - `view.settings.prefix` is a filename prefix to prepend for previews
 *     (legacy "sml_" etc.); when set we splice it before the basename.
 *   - The other configured fields (`view.fields`) are shown as a caption
 *     underneath the thumbnail.
 *   - Clicking a card navigates to edit (when the edit action is enabled).
 *
 * Drag-reorder + the per-row action gutter are deferred — image views in
 * the legacy admin show them inline on hover, but the grid layout makes the
 * action affordance less obvious in the SPA; clicking through to edit is the
 * primary affordance for now.
 */

interface ImagesViewProps {
	moduleId: string;
	view: ModuleView;
}

const PLACEHOLDER_TOKEN_RE = /\{(www|static)root\}/g;

/**
 * The legacy admin stores image URLs with `{wwwroot}` / `{staticroot}`
 * placeholders so they can be deployed across hosts. We swap them out for
 * the page-relative root since the SPA is served from the same origin as
 * the assets.
 */
const expandImageUrl = (raw: unknown, prefix: string): string => {
	if (typeof raw !== "string" || !raw) {
		return "";
	}

	const expanded = raw.replace(PLACEHOLDER_TOKEN_RE, "/");

	if (!prefix) {
		return expanded;
	}

	const slash = expanded.lastIndexOf("/");

	if (slash < 0) {
		return `${prefix}${expanded}`;
	}

	return `${expanded.slice(0, slash + 1)}${prefix}${expanded.slice(slash + 1)}`;
};

export const ImagesView = ({ moduleId, view }: ImagesViewProps) => {
	const navigate = useNavigate();
	const [query, setQuery] = useState("");
	const [debouncedQuery, setDebouncedQuery] = useState("");

	useEffect(() => {
		const handle = window.setTimeout(() => setDebouncedQuery(query), 200);

		return () => window.clearTimeout(handle);
	}, [query]);

	const listQuery = useQuery({
		queryKey: ["module-entries", moduleId, view.id, { q: debouncedQuery || undefined, view: view.id }] as const,
		queryFn: () => autoModulesApi.list(moduleId, { view: view.id, q: debouncedQuery || undefined }),
	});

	const settings = view.settings as Record<string, unknown> | undefined;
	const imageField = (settings?.image as string) || "image";
	const prefix = (settings?.prefix as string) || "";

	const { builtins } = useMemo(() => parseViewActions(view.actions), [view.actions]);
	const captionColumns = useMemo(
		() => Object.entries(view.fields ?? {}).filter(([key]) => key !== imageField),
		[view.fields, imageField]
	);

	const rows = listQuery.data?.items ?? [];

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

			{listQuery.isLoading && !listQuery.data ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading entries…
				</div>
			) : rows.length === 0 ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					{debouncedQuery ? `No entries match “${debouncedQuery}”.` : "No entries yet."}
				</div>
			) : (
				<ImagesGrid
					rows={rows}
					imageField={imageField}
					prefix={prefix}
					captionColumns={captionColumns}
					onClick={openEdit}
					canEdit={builtins.edit}
				/>
			)}
		</>
	);
};

interface ImagesGridProps {
	rows: ModuleEntryRow[];
	imageField: string;
	prefix: string;
	captionColumns: [string, { title: string }][];
	onClick: (row: ModuleEntryRow) => void;
	canEdit: boolean;
}

export const ImagesGrid = ({
	rows,
	imageField,
	prefix,
	captionColumns,
	onClick,
	canEdit,
}: ImagesGridProps) => {
	return (
		<div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
			{rows.map((row) => {
				const src = expandImageUrl(row[imageField], prefix);

				return (
					<button
						key={String(row.id)}
						type="button"
						className="group flex flex-col overflow-hidden rounded-lg border border-border bg-surface text-left transition-colors hover:border-border-strong hover:bg-surface-2 focus-visible:border-accent focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-accent-ring disabled:cursor-default"
						onClick={() => onClick(row)}
						disabled={!canEdit}
					>
						<div className="relative aspect-[4/3] w-full bg-surface-2">
							{src ? (
								<img
									src={src}
									alt=""
									loading="lazy"
									className="absolute inset-0 h-full w-full object-cover"
								/>
							) : (
								<div className="absolute inset-0 grid place-items-center text-text-4">
									<ImageIcon size={28} />
								</div>
							)}
						</div>

						{captionColumns.length > 0 && (
							<div className="flex flex-col gap-0.5 px-2.5 py-2 text-[12px]">
								{captionColumns.map(([key], i) => (
									<span
										key={key}
										className={i === 0 ? "font-medium text-text" : "text-text-3"}
									>
										{formatCellValue(row[key])}
									</span>
								))}
							</div>
						)}
					</button>
				);
			})}
		</div>
	);
};
