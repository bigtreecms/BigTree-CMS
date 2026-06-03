import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Link, useParams } from "react-router-dom";
import { Archive, Edit, Eye, EyeOff, FileText, Plus } from "lucide-react";
import { CardEmpty } from "@/components/dashboard/CardEmpty";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageTable } from "@/components/pages/PageTable";
import { MovePageDialog } from "@/components/pages/MovePageDialog";
import { HeaderBtn } from "@/components/ui/HeaderBtn";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { toast } from "@/lib/toast";
import { pagesApi, type PageListRow } from "@/api/endpoints/pages";
import { pendingChangesApi } from "@/api/endpoints/dashboard";
import { relativeTime } from "@/lib/time";

/**
 * Pages screen — table view for a page's direct children (supports drilling
 * via /pages and /pages/:parentId).
 *
 *   - URL-driven parent (root at /pages or /pages/0, subfolders at /pages/123)
 *   - Split into "Visible" (in_nav), "Hidden" (!in_nav), and "Archived" sections
 *   - Drag-to-reorder (visible only), inline rename, archive/restore (optimistic + server)
 *   - Dynamic title + breadcrumb using page lineage when inside a subfolder
 */
export const Pages = () => {
	const queryClient = useQueryClient();
	const { parentId } = useParams<{ parentId?: string }>();

	// Derive a safe numeric parent from the route. Falls back to root (0).
	const parent = (() => {
		if (!parentId) return 0;
		const n = parseInt(parentId, 10);
		return Number.isFinite(n) && n >= 0 ? n : 0;
	})();
	const isRoot = parent === 0;

	// Current folder info (for dynamic title + breadcrumb lineage) when viewing children of a page
	const { data: currentPage } = useQuery({
		queryKey: ["pages", "detail", parent],
		queryFn: () => pagesApi.get(parent, { lineage: true }),
		enabled: !isRoot,
	});

	const {
		data: rows = [],
		isLoading,
		error,
	} = useQuery({
		queryKey: ["pages", "list", parent],
		queryFn: () => pagesApi.list(parent, true),
	});

	const visible = useMemo(() => rows.filter((r) => r.in_nav && !r.archived), [rows]);
	const hidden = useMemo(() => rows.filter((r) => !r.in_nav && !r.archived), [rows]);
	const archived = useMemo(() => rows.filter((r) => r.archived), [rows]);

	interface PendingConfirm {
		type: "archive" | "restore" | "delete";
		id: number;
		title: string;
		/**
		 * Set when deleting a draft (pending NEW page). These live only in
		 * bigtree_pending_changes, so they're removed by rejecting the change
		 * rather than deleting a real page row.
		 */
		pendingChangeId?: number;
	}

	const [pendingConfirm, setPendingConfirm] = useState<PendingConfirm | null>(null);
	const [movingPage, setMovingPage] = useState<PageListRow | null>(null);

	const renameMutation = useMutation({
		mutationFn: ({ id, nav_title }: { id: number; nav_title: string }) =>
			pagesApi.patch(id, { nav_title }),
		// Optimistic update — show the new title immediately, roll back on error.
		onMutate: async ({ id, nav_title }) => {
			await queryClient.cancelQueries({
				queryKey: ["pages", "list", parent],
			});
			const previous = queryClient.getQueryData<PageListRow[]>(["pages", "list", parent]);
			queryClient.setQueryData<PageListRow[]>(
				["pages", "list", parent],
				(old) => old?.map((r) => (r.id === id ? { ...r, nav_title } : r)) ?? []
			);
			return { previous };
		},
		onError: (_err, _vars, ctx) => {
			if (ctx?.previous) queryClient.setQueryData(["pages", "list", parent], ctx.previous);
		},
		onSettled: () => {
			queryClient.invalidateQueries({
				queryKey: ["pages", "list", parent],
			});
		},
	});

	const reorderMutation = useMutation({
		mutationFn: (orderedIds: number[]) => pagesApi.reorder(parent, orderedIds),
		// The drag hook already applied the new order to local state; we just
		// re-fetch on settled to make sure server position values are reflected.
		onError: () => {
			queryClient.invalidateQueries({
				queryKey: ["pages", "list", parent],
			});
		},
		onSettled: () => {
			queryClient.invalidateQueries({
				queryKey: ["pages", "list", parent],
			});
		},
	});

	const archiveMutation = useMutation({
		mutationFn: ({ id, archived }: { id: number; archived: boolean }) =>
			archived ? pagesApi.unarchive(id) : pagesApi.archive(id),
		onMutate: async ({ id, archived }) => {
			await queryClient.cancelQueries({
				queryKey: ["pages", "list", parent],
			});
			const previous = queryClient.getQueryData<PageListRow[]>(["pages", "list", parent]);
			queryClient.setQueryData<PageListRow[]>(
				["pages", "list", parent],
				(old) => old?.map((r) => (r.id === id ? { ...r, archived: !archived } : r)) ?? []
			);
			return { previous };
		},
		onError: (_err, _vars, ctx) => {
			if (ctx?.previous) queryClient.setQueryData(["pages", "list", parent], ctx.previous);
		},
		onSettled: () => {
			queryClient.invalidateQueries({
				queryKey: ["pages", "list", parent],
			});
		},
		onSuccess: (_data, variables) => {
			const isRestoring = variables.archived;
			toast.success(isRestoring ? "Page restored" : "Page archived");
		},
	});

	const deleteMutation = useMutation({
		mutationFn: ({ id, pendingChangeId }: { id: number; pendingChangeId?: number }) =>
			pendingChangeId ? pendingChangesApi.reject(pendingChangeId) : pagesApi.delete(id),
		onMutate: async ({ id }) => {
			await queryClient.cancelQueries({
				queryKey: ["pages", "list", parent],
			});
			const previous = queryClient.getQueryData<PageListRow[]>(["pages", "list", parent]);
			queryClient.setQueryData<PageListRow[]>(
				["pages", "list", parent],
				(old) => old?.filter((r) => r.id !== id) ?? []
			);
			return { previous };
		},
		onError: (_err, _vars, ctx) => {
			if (ctx?.previous) queryClient.setQueryData(["pages", "list", parent], ctx.previous);
		},
		onSettled: () => {
			queryClient.invalidateQueries({
				queryKey: ["pages", "list", parent],
			});
		},
		onSuccess: () => {
			toast.success("Page deleted");
		},
	});

	// Newest update across all rows — used in the subtitle ("Updated 12 minutes ago").
	const newestUpdate = useMemo(() => {
		if (rows.length === 0) return null;
		return rows
			.map((r) => r.updated_at)
			.filter(Boolean)
			.sort()
			.at(-1);
	}, [rows]);

	function handleReorder(scope: "visible" | "hidden", orderedIds: number[]) {
		// We get the in-scope ids back; merge with the unaffected section so
		// the full ordering can be persisted.
		const others = scope === "visible" ? hidden : visible;
		const merged = [...orderedIds, ...others.map((r) => r.id)];
		reorderMutation.mutate(merged);
	}

	// Dynamic title + breadcrumb based on current location in the page tree
	const folderTitle = currentPage?.nav_title ?? "Home";
	const lineage = currentPage?.lineage ?? [];

	const breadcrumbItems = isRoot
		? [{ label: "Pages" }, { label: "Home" }]
		: [
				{ label: "Pages", to: "/pages" },
				{ label: "Home", to: "/pages" },
				...lineage.map((anc, index) => {
					const isLast = index === lineage.length - 1;

					return {
						label: anc.nav_title,
						to: isLast ? undefined : `/pages/${anc.id}`,
					};
				}),
			];

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb items={breadcrumbItems} />
			<PageHead
				title={folderTitle}
				sub={
					<>
						<span className="font-mono">/</span> · {visible.length} visible ·{" "}
						{hidden.length} hidden
						{newestUpdate ? <> · Updated {relativeTime(newestUpdate)}</> : null}
					</>
				}
				actions={
					<>
						<HeaderBtn icon={<Eye size={13} />}>Preview</HeaderBtn>
						{!isRoot && (
							<Link
								to={`/pages/${parent}/edit/revisions`}
								className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-sm hover:bg-hover"
							>
								<FileText size={13} />
								Revisions
							</Link>
						)}
						{!isRoot && (
							<Link
								to={`/pages/${parent}/edit`}
								className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-sm hover:bg-hover"
							>
								<Edit size={13} />
								Edit page
							</Link>
						)}
						<Link
							to={`/pages/add/${parent}`}
							className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-sm font-medium text-accent-fg hover:bg-accent-hover"
						>
							<Plus size={13} />
							Add subpage
						</Link>
					</>
				}
			/>

			{error ? (
				<ErrorPanel error={error} />
			) : isLoading ? (
				<div className="mt-6 text-[13px] text-text-3">Loading…</div>
			) : (
				<>
					{/* Only show section headers + tables when there is actual content */}
					{(visible.length > 0 || hidden.length > 0 || archived.length > 0) && (
						<>
							<div className="mt-1 flex items-center gap-2.5">
								<span className="text-[12px] font-semibold uppercase tracking-[0.08em] text-text-3">
									Subpages
								</span>
							</div>

							{visible.length > 0 && (
								<PageTable
									title="Visible"
									icon={<FileText size={13} className="text-accent" />}
									rows={visible}
									onReorder={(ids) => handleReorder("visible", ids)}
									onRename={(id, next) =>
										renameMutation.mutate({ id, nav_title: next })
									}
									onToggleArchive={(id) => {
										const r = visible.find((x) => x.id === id);
										if (r) {
											setPendingConfirm({
												type: r.archived ? "restore" : "archive",
												id,
												title: r.nav_title,
											});
										}
									}}
									emptyLabel="No visible pages."
									onDelete={(id) => {
										const r = visible.find((x) => x.id === id);

										if (r) {
											setPendingConfirm({
												type: "delete",
												id,
												title: r.nav_title,
												pendingChangeId: r.pending_change_id,
											});
										}
									}}
									onMove={(id) => {
										const r = visible.find((x) => x.id === id);

										if (r) {
											setMovingPage(r);
										}
									}}
								/>
							)}

							{hidden.length > 0 && (
								<PageTable
									title="Hidden"
									icon={<EyeOff size={13} className="text-text-3" />}
									rows={hidden}
									onReorder={() => {}}
									onRename={(id, next) =>
										renameMutation.mutate({ id, nav_title: next })
									}
									onToggleArchive={(id) => {
										const r = hidden.find((x) => x.id === id);
										if (r) {
											setPendingConfirm({
												type: r.archived ? "restore" : "archive",
												id,
												title: r.nav_title,
											});
										}
									}}
									emptyLabel="No hidden pages."
									allowReorder={false}
									onDelete={(id) => {
										const r = hidden.find((x) => x.id === id);

										if (r) {
											setPendingConfirm({
												type: "delete",
												id,
												title: r.nav_title,
												pendingChangeId: r.pending_change_id,
											});
										}
									}}
									onMove={(id) => {
										const r = hidden.find((x) => x.id === id);

										if (r) {
											setMovingPage(r);
										}
									}}
								/>
							)}

							{archived.length > 0 && (
								<PageTable
									title="Archived"
									icon={<Archive size={13} className="text-text-3" />}
									rows={archived}
									onReorder={() => {}}
									onRename={(id, next) =>
										renameMutation.mutate({ id, nav_title: next })
									}
									onToggleArchive={(id) => {
										const r = archived.find((x) => x.id === id);
										if (r) {
											setPendingConfirm({
												type: "restore",
												id,
												title: r.nav_title,
											});
										}
									}}
									leftActionLabel="Restore"
									rightActionLabel="Delete"
									onDelete={(id) => {
										const r = archived.find((x) => x.id === id);
										if (r) {
											setPendingConfirm({
												type: "delete",
												id,
												title: r.nav_title,
											});
										}
									}}
									allowReorder={false}
									enableFilters={false}
								/>
							)}
						</>
					)}

					{/* Global empty state when the current page truly has no children */}
					{visible.length === 0 && hidden.length === 0 && archived.length === 0 && (
						<div className="mt-6">
							<CardEmpty icon={FileText} label="No subpages yet." />
						</div>
					)}
				</>
			)}

			<ConfirmDialog
				open={!!pendingConfirm}
				onOpenChange={(open) => {
					if (!open) {
						setPendingConfirm(null);
					}
				}}
				title={
					pendingConfirm
						? pendingConfirm.type === "delete"
							? "Delete page"
							: pendingConfirm.type === "restore"
								? "Restore page"
								: "Archive page"
						: ""
				}
				description={
					pendingConfirm
						? `Are you sure you want to ${pendingConfirm.type} "${pendingConfirm.title}"?`
						: ""
				}
				confirmLabel={
					pendingConfirm
						? pendingConfirm.type === "delete"
							? "Delete"
							: pendingConfirm.type === "restore"
								? "Restore"
								: "Archive"
						: ""
				}
				variant={pendingConfirm?.type === "delete" ? "danger" : "default"}
				onConfirm={() => {
					if (pendingConfirm) {
						if (pendingConfirm.type === "delete") {
							deleteMutation.mutate({
								id: pendingConfirm.id,
								pendingChangeId: pendingConfirm.pendingChangeId,
							});
						} else {
							const isRestoring = pendingConfirm.type === "restore";

							archiveMutation.mutate({
								id: pendingConfirm.id,
								archived: isRestoring,
							});
						}

						setPendingConfirm(null);
					}
				}}
			/>

			<MovePageDialog
				open={movingPage !== null}
				onOpenChange={(open) => {
					if (!open) {
						setMovingPage(null);
					}
				}}
				page={movingPage}
				invalidateKey={["pages", "list", parent]}
			/>
		</div>
	);
};
