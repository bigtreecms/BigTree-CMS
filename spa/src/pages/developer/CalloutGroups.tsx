import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import { calloutsApi, type CalloutGroup } from "@/api/endpoints/callouts";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

export const CalloutGroups = () => {
	const navigate = useNavigate();
	const queryClient = useQueryClient();
	const [confirmDelete, setConfirmDelete] = useState<CalloutGroup | null>(null);

	const query = useQuery({
		queryKey: ["callout-groups", "list"],
		queryFn: () => calloutsApi.listGroups(),
	});

	const deleteMutation = useMutation({
		mutationFn: (id: string) => calloutsApi.deleteGroup(id),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["callout-groups"] });
			setConfirmDelete(null);
			toast.success("Group deleted");
		},
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Delete failed");
		},
	});

	const rows = query.data ?? [];

	const columns: DataTableColumn<CalloutGroup>[] = [
		{
			key: "name",
			header: "Name",
			width: "minmax(0,1.5fr)",
			cell: (row) => (
				<div className="min-w-0">
					<div className="truncate font-medium text-text">{row.name}</div>
				</div>
			),
		},
		{
			key: "count",
			header: "Callouts",
			width: "120px",
			hideOnMobile: true,
			align: "left",
			cell: (row) => (
				<span className="tabular-nums text-[12px] text-text-3">
					{(row.callouts ?? []).length}
				</span>
			),
		},
		{
			key: "actions",
			header: "",
			width: "56px",
			align: "right",
			cell: (row) => (
				<button
					type="button"
					className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
					onClick={(e) => {
						e.stopPropagation();
						setConfirmDelete(row);
					}}
					title="Delete group"
					aria-label="Delete group"
				>
					<Trash size={13} />
				</button>
			),
		},
	];

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb
				items={[{ label: "Developer", to: "/developer" }, { label: "Callout groups" }]}
			/>

			<PageHead
				title="Callout groups"
				sub={rows.length === 1 ? "1 group" : `${rows.length} groups`}
				actions={
					<Link
						to="/developer/callout-groups/add"
						className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover"
					>
						<Plus size={13} />
						Add group
					</Link>
				}
			/>

			<DeveloperSectionNav />

			<DataTable<CalloutGroup>
				columns={columns}
				rows={rows}
				getRowKey={(row) => row.id}
				isLoading={query.isLoading}
				loadingLabel="Loading groups…"
				emptyLabel="No callout groups yet."
				onRowClick={(row) =>
					navigate(`/developer/callout-groups/${encodeURIComponent(row.id)}/edit`)
				}
			/>

			{confirmDelete && (
				<ConfirmDialog
					open
					onOpenChange={(open) => {
						if (!open) {
							setConfirmDelete(null);
						}
					}}
					title={`Delete "${confirmDelete.name}"?`}
					description="Callouts in this group will continue to exist; only the grouping is removed."
					confirmLabel="Delete group"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(confirmDelete.id)}
				/>
			)}
		</div>
	);
};
