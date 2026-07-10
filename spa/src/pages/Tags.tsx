import { useNavigate } from "react-router-dom";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { GitMerge, Plus, Trash } from "lucide-react";

import { useAuthStore } from "@/auth/store";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Button } from "@/components/ui/Button";
import { Pager } from "@/components/ui/Pager";
import { SearchInput } from "@/components/ui/SearchInput";
import { Toolbar } from "@/components/ui/Toolbar";
import { SubNav } from "@/components/ui/SubNav";
import { IconButton } from "@/components/ui/IconButton";

import { tagsApi, type Tag } from "@/api/endpoints/tags";
import { pluralize } from "@/lib/number";
import { derivePagination } from "@/lib/pagination";
import { isAdmin } from "@/lib/permissions";
import { queryKeys } from "@/lib/queryKeys";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { usePaginatedSearch } from "@/hooks/usePaginatedSearch";
import { useToastMutation } from "@/hooks/useToastMutation";

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

export const Tags = () => {
	const navigate = useNavigate();
	const user = useAuthStore((s) => s.user);
	const canEdit = isAdmin(user);

	const { query, setQuery, page, setPage, debouncedQuery } = usePaginatedSearch();
	const deleteDialog = useConfirmDialog<Tag>();

	const listQuery = useQuery({
		queryKey: queryKeys.tags.list(page, debouncedQuery),
		queryFn: () =>
			tagsApi.list({
				page,
				per_page: PER_PAGE,
				q: debouncedQuery || undefined,
			}),
		placeholderData: keepPreviousData,
	});

	const { rows, total, totalPages, safePage } = derivePagination({
		rows: listQuery.data?.items,
		meta: listQuery.data?.meta,
		page,
		perPage: PER_PAGE,
	});

	const deleteMutation = useToastMutation({
		mutationFn: (id: number) => tagsApi.delete(id),
		invalidate: [["tags"]],
		successMessage: "Tag deleted",
		errorMessage: "Could not delete tag",
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
					<IconButton
						className="disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-text-3"
						title="Merge into another tag"
						label="Merge into another tag"
						disabled={!canEdit}
						onClick={(e) => {
							e.stopPropagation();
							navigate(`/tags/merge?from=${tag.id}`);
						}}
					>
						<GitMerge size={15} />
					</IconButton>
					<IconButton
						tone="danger"
						className="disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-text-3"
						title="Delete tag"
						label="Delete tag"
						disabled={!canEdit}
						onClick={(e) => {
							e.stopPropagation();
							deleteDialog.open(tag);
						}}
					>
						<Trash size={15} />
					</IconButton>
				</div>
			),
		},
	];

	return (
		<PageContainer width="wide">
			<Breadcrumb items={[{ label: "Tags" }, { label: "View Tags" }]} />

			<PageHead
				title="Tags"
				sub={pluralize(total, "tag")}
				actions={
					canEdit ? (
						<Button variant="primary" icon={<Plus size={13} />} to="/tags/add">
							Add tag
						</Button>
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

			<Toolbar
				search={
					<SearchInput value={query} onChange={setQuery} placeholder="Search tags…" />
				}
			>
				<Pager page={safePage} totalPages={totalPages} onChange={setPage} />
			</Toolbar>

			<DataTable<Tag>
				columns={columns}
				rows={rows}
				getRowKey={(tag) => tag.id}
				isLoading={listQuery.isLoading && !listQuery.data}
				loadingLabel="Loading tags…"
				emptyLabel={query ? `No tags match “${query}”.` : "No tags yet."}
			/>

			{deleteDialog.item && (
				<ConfirmDialog
					open={deleteDialog.isOpen}
					onOpenChange={(open) => {
						if (!open) {
							deleteDialog.close();
						}
					}}
					title={`Delete “${deleteDialog.item.tag}”?`}
					description={
						deleteDialog.item.usage_count > 0
							? `This tag is currently used by ${pluralize(deleteDialog.item.usage_count, "item")}. Those associations will be removed.`
							: "This tag isn't currently used by any content."
					}
					confirmLabel="Delete tag"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(deleteDialog.item!.id)}
				/>
			)}
		</PageContainer>
	);
};
