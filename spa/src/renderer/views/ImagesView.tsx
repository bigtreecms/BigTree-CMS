import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Edit, Image as ImageIcon, Search, Trash, X } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";

import { ConfirmDialog } from "@/components/ui/ConfirmDialog";

import { autoModulesApi, type ModuleEntryRow } from "@/api/endpoints/auto-modules";
import type { ModuleView } from "@/api/endpoints/modules";

import { expandImageUrl } from "@/lib/imageUrl";
import { toast } from "@/lib/toast";

import { type CustomViewAction, parseViewActions } from "./viewHelpers";

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
	const navigate = useNavigate();
	const [query, setQuery] = useState("");
	const [debouncedQuery, setDebouncedQuery] = useState("");

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
					moduleId={moduleId}
					viewId={view.id}
					prefix={prefix}
					onClick={openEdit}
					canEdit={builtins.edit}
					canDelete={builtins.delete}
					customActions={custom}
				/>
			)}
		</>
	);
};

interface ImagesGridProps {
	rows: ModuleEntryRow[];
	moduleId: string;
	viewId: string;
	prefix: string;
	onClick: (row: ModuleEntryRow) => void;
	canEdit: boolean;
	canDelete: boolean;
	customActions: CustomViewAction[];
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
	const queryClient = useQueryClient();
	const [confirmDelete, setConfirmDelete] = useState<ModuleEntryRow | null>(null);

	const hasActions = canEdit || canDelete || customActions.length > 0;

	const deleteMutation = useMutation({
		mutationFn: (entryId: number) => autoModulesApi.delete(moduleId, entryId, viewId),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["module-entries", moduleId, viewId] });
			toast.success("Entry deleted");
		},
		onError: () => {
			toast.error("Could not delete entry");
		},
		onSettled: () => {
			setConfirmDelete(null);
		},
	});

	return (
		<div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
			{rows.map((row) => {
				const src = expandImageUrl(row.column1, prefix);
				const entryId = Number(row.id);
				const canMutate = Number.isFinite(entryId) && entryId > 0;

				return (
					<div
						key={String(row.id)}
						className="group flex flex-col overflow-hidden rounded-lg border border-border bg-surface transition-colors hover:border-border-strong"
					>
						<button
							type="button"
							className="block text-left focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-accent-ring disabled:cursor-default"
							onClick={() => onClick(row)}
							disabled={!canEdit}
							aria-label="Edit"
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
						</button>

						{hasActions && (
							<div className="flex items-center justify-end gap-1 border-t border-border bg-surface-2 px-2 py-1.5">
								{customActions.map((action) => (
									<Link
										key={action.key}
										to={`/modules/${moduleId}/view/${viewId}/${action.route}/${row.id}`}
										className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
										title={action.name}
										aria-label={action.name}
									>
										<span className="inline-block text-[11px] font-medium">
											{action.name.slice(0, 2)}
										</span>
									</Link>
								))}

								{canEdit && (
									<Link
										to={`/modules/${moduleId}/view/${viewId}/edit/${row.id}`}
										className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
										title="Edit"
										aria-label="Edit"
									>
										<Edit size={14} />
									</Link>
								)}

								{canDelete && (
									<button
										type="button"
										className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger disabled:opacity-40"
										title="Delete"
										aria-label="Delete"
										disabled={!canMutate}
										onClick={() => setConfirmDelete(row)}
									>
										<Trash size={14} />
									</button>
								)}
							</div>
						)}
					</div>
				);
			})}

			{confirmDelete && (
				<ConfirmDialog
					open={true}
					onOpenChange={(open) => {
						if (!open) {
							setConfirmDelete(null);
						}
					}}
					title="Delete entry?"
					description="This action cannot be undone."
					confirmLabel="Delete"
					variant="danger"
					onConfirm={() => {
						const entryId = Number(confirmDelete.id);

						if (Number.isFinite(entryId) && entryId > 0) {
							deleteMutation.mutate(entryId);
						}
					}}
				/>
			)}
		</div>
	);
};
