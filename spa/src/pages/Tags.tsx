import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { GitMerge, Plus, Search, Trash, X } from "lucide-react";

import { useAuthStore } from "@/auth/store";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { HeaderBtn } from "@/components/ui/HeaderBtn";
import { Pager } from "@/components/ui/Pager";
import { SubNav } from "@/components/ui/SubNav";

import { tagsApi, type Tag } from "@/api/endpoints/tags";
import { isAdmin } from "@/lib/permissions";
import { toast } from "@/lib/toast";

/**
 * Tags list page. Mirrors the conventions used by Users.tsx — server-side
 * paginated DataTable, debounced search, inline add.
 *
 * Per-row actions (Merge + Delete) match the legacy admin's pattern: each
 * tag has its own Merge link that takes you to /tags/merge?from=ID where
 * you pick the target. The API still accepts multiple source ids — that
 * capability is exposed by hand-editing the URL — but the list UI deals in
 * one source at a time.
 */

const PER_PAGE = 25;

const TAGS_LIST_KEY = (page: number, q: string) => ["tags", "list", { page, q }] as const;

export const Tags = () => {
	const queryClient = useQueryClient();
	const navigate = useNavigate();
	const user = useAuthStore((s) => s.user);
	const canEdit = isAdmin(user);

	const [query, setQuery] = useState("");
	const [page, setPage] = useState(1);
	const [confirmDelete, setConfirmDelete] = useState<Tag | null>(null);

	useEffect(() => {
		setPage(1);
	}, [query]);

	const listQuery = useQuery({
		queryKey: TAGS_LIST_KEY(page, query),
		queryFn: () =>
			tagsApi.list({
				page,
				per_page: PER_PAGE,
				q: query || undefined,
			}),
		placeholderData: keepPreviousData,
	});

	const rows = listQuery.data?.items ?? [];
	const total = listQuery.data?.meta?.total ?? rows.length;
	const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
	const safePage = Math.min(page, totalPages);

	const deleteMutation = useMutation({
		mutationFn: (id: number) => tagsApi.delete(id),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["tags"] });
			toast.success("Tag deleted");
		},
		onError: () => {
			toast.error("Could not delete tag");
		},
	});

	const columns: DataTableColumn<Tag>[] = [
		{
			key: "tag",
			header: "Name",
			width: "minmax(0,1.5fr)",
			cell: (tag) => <span className="font-medium text-text">{tag.tag}</span>,
		},
		{
			key: "route",
			header: "Route",
			width: "minmax(0,1.5fr)",
			hideOnMobile: true,
			cell: (tag) => (
				<span className="truncate font-mono text-[11.5px] text-text-3">{tag.route}</span>
			),
		},
		{
			key: "usage_count",
			header: "Usage",
			width: "100px",
			hideOnMobile: true,
			align: "right",
			headerAlign: "right",
			cell: (tag) => <span className="tabular-nums text-text-3">{tag.usage_count}</span>,
		},
		{
			key: "actions",
			header: "Actions",
			width: "84px",
			align: "right",
			headerAlign: "right",
			cell: (tag) => (
				<div className="flex items-center justify-end gap-1">
					<button
						type="button"
						className="rounded p-1 text-text-3 hover:bg-hover hover:text-text disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-text-3"
						title="Merge into another tag"
						disabled={!canEdit}
						onClick={(e) => {
							e.stopPropagation();
							navigate(`/tags/merge?from=${tag.id}`);
						}}
					>
						<GitMerge size={15} />
					</button>
					<button
						type="button"
						className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-text-3"
						title="Delete tag"
						disabled={!canEdit}
						onClick={(e) => {
							e.stopPropagation();
							setConfirmDelete(tag);
						}}
					>
						<Trash size={15} />
					</button>
				</div>
			),
		},
	];

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb items={[{ label: "Tags" }, { label: "View Tags" }]} />

			<PageHead
				title="Tags"
				sub={`${total} tag${total === 1 ? "" : "s"}`}
				actions={
					canEdit ? (
						<HeaderBtn primary icon={<Plus size={13} />} to="/tags/add">
							Add tag
						</HeaderBtn>
					) : undefined
				}
			/>

			{canEdit && (
				<SubNav<"list" | "add">
					className="mb-4"
					value="list"
					onChange={(v) => navigate(v === "add" ? "/tags/add" : "/tags")}
					items={[
						{ value: "list", label: "View Tags" },
						{ value: "add", label: "Add Tag", icon: <Plus size={13} /> },
					]}
				/>
			)}

			<div className="mb-3 flex flex-wrap items-center gap-3">
				<div className="relative w-full sm:w-auto sm:max-w-md sm:flex-1">
					<Search
						size={14}
						className="absolute left-3 top-1/2 -translate-y-1/2 text-text-3"
					/>
					<input
						className="w-full rounded-md border border-border bg-surface py-1.5 pl-9 pr-9 text-[13.5px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring"
						placeholder="Search tags…"
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

				<div className="flex-1" />

				<Pager page={safePage} totalPages={totalPages} onChange={setPage} />
			</div>

			<DataTable<Tag>
				columns={columns}
				rows={rows}
				getRowKey={(tag) => tag.id}
				isLoading={listQuery.isLoading && !listQuery.data}
				loadingLabel="Loading tags…"
				emptyLabel={query ? `No tags match “${query}”.` : "No tags yet."}
			/>

			{confirmDelete && (
				<ConfirmDialog
					open={true}
					onOpenChange={(open) => {
						if (!open) {
							setConfirmDelete(null);
						}
					}}
					title={`Delete “${confirmDelete.tag}”?`}
					description={
						confirmDelete.usage_count > 0
							? `This tag is currently used by ${confirmDelete.usage_count} item${
									confirmDelete.usage_count === 1 ? "" : "s"
								}. Those associations will be removed.`
							: "This tag isn't currently used by any content."
					}
					confirmLabel="Delete tag"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(confirmDelete.id)}
				/>
			)}
		</div>
	);
};
