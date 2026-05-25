import { useMemo } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Edit, Eye, EyeOff, FileText, Move, Plus } from "lucide-react";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageTable } from "@/components/pages/PageTable";
import { HeaderBtn } from "@/components/ui/HeaderBtn";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { pagesApi, type PageListRow } from "@/api/endpoints/pages";
import { relativeTime } from "@/lib/time";

/**
 * Pages screen — initial table-view port of the prototype's `pages-screen.jsx`.
 *
 * Scope of this first slice:
 *   - List children of the home page (parent=0). Drill-down to /pages/:id
 *     comes next.
 *   - Split into "Visible" (in_nav) and "Hidden" sections, matching the
 *     prototype.
 *   - Drag-to-reorder rows, optimistic with server commit.
 *   - Inline rename, optimistic with server commit.
 *   - Archive / restore toggle per row.
 *
 * Out of scope (next slices):
 *   - PageProperties drawer (read-only metadata panel)
 *   - Add subpage wizard (separate route /pages/new)
 *   - Page-level Edit, Revisions, Move actions in the header
 *   - Drill-in to a subpage to manage its children
 */
export const Pages = () => {
	const queryClient = useQueryClient();
	const parent = 0; // root for now

	const {
		data: rows = [],
		isLoading,
		error,
	} = useQuery({
		queryKey: ["pages", "list", parent],
		queryFn: () => pagesApi.list(parent, false),
	});

	const visible = useMemo(() => rows.filter((r) => r.in_nav && !r.archived), [rows]);
	const hidden = useMemo(() => rows.filter((r) => !r.in_nav && !r.archived), [rows]);

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

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb items={[{ label: "Pages" }, { label: "Home" }]} />
			<PageHead
				title="Home"
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
						<HeaderBtn icon={<FileText size={13} />}>Revisions</HeaderBtn>
						<HeaderBtn icon={<Move size={13} />}>Move</HeaderBtn>
						<HeaderBtn icon={<Edit size={13} />}>Edit page</HeaderBtn>
						<HeaderBtn icon={<Plus size={13} />} primary>
							Add subpage
						</HeaderBtn>
					</>
				}
			/>

			{error ? (
				<ErrorPanel error={error} />
			) : isLoading ? (
				<div className="mt-6 text-[13px] text-text-3">Loading…</div>
			) : (
				<>
					<div className="mt-1 flex items-center gap-2.5">
						<span className="text-[12px] font-semibold uppercase tracking-[0.08em] text-text-3">
							Subpages
						</span>
					</div>

					<PageTable
						title="Visible"
						icon={<FileText size={13} className="text-accent" />}
						rows={visible}
						onReorder={(ids) => handleReorder("visible", ids)}
						onRename={(id, next) => renameMutation.mutate({ id, nav_title: next })}
						onToggleArchive={(id) => {
							const r = visible.find((x) => x.id === id);
							if (r)
								archiveMutation.mutate({
									id,
									archived: r.archived,
								});
						}}
					/>

					<PageTable
						title="Hidden"
						icon={<EyeOff size={13} className="text-text-3" />}
						rows={hidden}
						onReorder={(ids) => handleReorder("hidden", ids)}
						onRename={(id, next) => renameMutation.mutate({ id, nav_title: next })}
						onToggleArchive={(id) => {
							const r = hidden.find((x) => x.id === id);
							if (r)
								archiveMutation.mutate({
									id,
									archived: r.archived,
								});
						}}
						emptyLabel="No hidden pages."
					/>
				</>
			)}
		</div>
	);
};
