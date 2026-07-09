import { useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Plus, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Button } from "@/components/ui/Button";
import { IconButton } from "@/components/ui/IconButton";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import { calloutsApi, type CalloutGroup } from "@/api/endpoints/callouts";

import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useToastMutation } from "@/hooks/useToastMutation";
import { pluralize } from "@/lib/number";
import { queryKeys } from "@/lib/queryKeys";

export const CalloutGroups = () => {
	const navigate = useNavigate();
	const deleteDialog = useConfirmDialog<CalloutGroup>();

	const query = useQuery({
		queryKey: queryKeys.calloutGroups.list(),
		queryFn: () => calloutsApi.listGroups(),
	});

	const deleteMutation = useToastMutation({
		mutationFn: (id: string) => calloutsApi.deleteGroup(id),
		invalidate: [queryKeys.calloutGroups.root()],
		successMessage: "Group deleted",
		errorMessage: "Delete failed",
		onSuccess: () => {
			deleteDialog.close();
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
				<IconButton
					tone="danger"
					onClick={(e) => {
						e.stopPropagation();
						deleteDialog.open(row);
					}}
					title="Delete group"
					label="Delete group"
				>
					<Trash size={13} />
				</IconButton>
			),
		},
	];

	return (
		<PageContainer width="wide">
			<Breadcrumb
				items={[{ label: "Developer", to: "/developer" }, { label: "Callout groups" }]}
			/>

			<PageHead
				title="Callout groups"
				sub={pluralize(rows.length, "group")}
				actions={
					<Button
						variant="primary"
						icon={<Plus size={13} />}
						to="/developer/callout-groups/add"
					>
						Add group
					</Button>
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

			{deleteDialog.item && (
				<ConfirmDialog
					open={deleteDialog.isOpen}
					onOpenChange={(open) => {
						if (!open) {
							deleteDialog.close();
						}
					}}
					title={`Delete "${deleteDialog.item.name}"?`}
					description="Callouts in this group will continue to exist; only the grouping is removed."
					confirmLabel="Delete group"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(deleteDialog.item!.id)}
				/>
			)}
		</PageContainer>
	);
};
