import { Edit, Image as ImageIcon, Trash } from "lucide-react";

import { EmptyState } from "@/components/ui/EmptyState";
import { IconButton } from "@/components/ui/IconButton";
import { Loading } from "@/components/ui/Loading";
import { QueryRenderer } from "@/components/ui/QueryRenderer";
import { SearchInput } from "@/components/ui/SearchInput";
import { Toolbar } from "@/components/ui/Toolbar";

import type { ModuleEntryRow } from "@/api/endpoints/auto-modules";
import type { ModuleView } from "@/api/endpoints/modules";

import { expandImageUrl } from "@/lib/imageUrl";

import { type CustomViewAction, viewEmptyLabel } from "./viewHelpers";
import { useEntryDelete } from "./useEntryDelete";
import { useModuleEntryLinks } from "@/pages/ModuleLayout";
import { useModuleEntries } from "./useModuleEntries";

/**
 * Runtime for the `images` view type — a grid of thumbnail cards.
 *
 *   - The image URL is always stored in `column1` of the view cache (the
 *     legacy admin only caches the image column for `images` / `images-grouped`
 *     views; the other fields configured in `view.fields` are not present in
 *     the cache row, so no caption is shown).
 *   - `view.settings.prefix` is a filename prefix to prepend for previews
 *     (legacy "sml_" etc.); when set we splice it before the basename.
 *   - Action icons (edit / delete / custom) render beneath each thumbnail,
 *     mirroring the legacy admin.
 *
 * Drag-reorder is deferred.
 */

interface ImagesViewProps {
	moduleId: string;
	view: ModuleView;
}

export const ImagesView = ({ moduleId, view }: ImagesViewProps) => {
	const { query, setQuery, debouncedQuery, builtins, custom, listQuery, rows, openEdit } =
		useModuleEntries({ moduleId, view });

	const settings = view.settings as Record<string, unknown> | undefined;
	const prefix = (settings?.prefix as string) || "";

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

			<QueryRenderer
				empty={<EmptyState>{viewEmptyLabel(debouncedQuery)}</EmptyState>}
				error={listQuery.error}
				isEmpty={rows.length === 0}
				isLoading={listQuery.isLoading && !listQuery.data}
				loading={<Loading label="Loading entries…" variant="card" />}
			>
				<ImagesGrid
					canDelete={builtins.delete}
					canEdit={builtins.edit}
					customActions={custom}
					moduleId={moduleId}
					prefix={prefix}
					rows={rows}
					viewId={view.id}
					onClick={openEdit}
				/>
			</QueryRenderer>
		</>
	);
};

interface ImagesGridProps {
	canDelete: boolean;
	canEdit: boolean;
	customActions: CustomViewAction[];
	moduleId: string;
	onClick: (row: ModuleEntryRow) => void;
	prefix: string;
	rows: ModuleEntryRow[];
	viewId: string;
}

export const ImagesGrid = ({
	rows,
	moduleId,
	viewId,
	prefix,
	onClick,
	canEdit,
	canDelete,
	customActions,
}: ImagesGridProps) => {
	const { requestDelete, dialog: deleteDialog } = useEntryDelete(moduleId, viewId);
	const { editPath, actionPath } = useModuleEntryLinks();

	const hasActions = canEdit || canDelete || customActions.length > 0;

	return (
		<div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
			{rows.map((row) => {
				const src = expandImageUrl(row.column1, prefix);
				const entryId = Number(row.id);
				const canMutate = Number.isFinite(entryId) && entryId > 0;

				return (
					<div
						className="group flex flex-col overflow-hidden rounded-lg border border-border bg-surface transition-colors hover:border-border-strong"
						key={String(row.id)}
					>
						<button
							aria-label="Edit"
							className="block text-left focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-accent-ring disabled:cursor-default"
							disabled={!canEdit}
							type="button"
							onClick={() => onClick(row)}
						>
							<div className="relative aspect-4/3 w-full bg-surface-2">
								{src ? (
									<img
										alt=""
										className="absolute inset-0 size-full object-cover"
										loading="lazy"
										src={src}
									/>
								) : (
									<div className="absolute inset-0 grid place-items-center text-text-4">
										<ImageIcon size={28} />
									</div>
								)}
							</div>
						</button>

						{hasActions && (
							<div className="flex items-center justify-end gap-1 border-t border-border bg-surface-2 px-2 py-1.5">
								{customActions.map((action) => (
									<IconButton
										key={action.key}
										label={action.name}
										title={action.name}
										to={actionPath(action.route, row.id)}
									>
										<span className="inline-block text-[11px] font-medium">
											{action.name.slice(0, 2)}
										</span>
									</IconButton>
								))}

								{canEdit && (
									<IconButton label="Edit" title="Edit" to={editPath(row.id)}>
										<Edit size={14} />
									</IconButton>
								)}

								{canDelete && (
									<IconButton
										disabled={!canMutate}
										label="Delete"
										title="Delete"
										tone="danger"
										onClick={() => requestDelete(row)}
									>
										<Trash size={14} />
									</IconButton>
								)}
							</div>
						)}
					</div>
				);
			})}

			{deleteDialog}
		</div>
	);
};
