import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Plus, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { MonoText } from "@/components/ui/MonoText";
import { Button } from "@/components/ui/Button";
import { IconButton } from "@/components/ui/IconButton";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import { modulesApi, type ModuleGroup } from "@/api/endpoints/modules";

import { useToastMutation } from "@/hooks/useToastMutation";

export const ModuleGroups = () => {
	const navigate = useNavigate();
	const [confirmDelete, setConfirmDelete] = useState<ModuleGroup | null>(null);

	const query = useQuery({
		queryKey: ["module-groups", "list"],
		queryFn: () => modulesApi.listGroups(),
	});

	const deleteMutation = useToastMutation({
		mutationFn: (id: string) => modulesApi.deleteGroup(id),
		invalidate: [["module-groups"]],
		successMessage: "Module group deleted",
		errorMessage: "Delete failed",
		onSuccess: () => {
			setConfirmDelete(null);
		},
	});

	const rows = query.data ?? [];

	const columns: DataTableColumn<ModuleGroup>[] = [
		{
			key: "name",
			header: "Name",
			width: "minmax(0,2fr)",
			cell: (row) => (
				<div className="min-w-0">
					<div className="truncate font-medium text-text">{row.name}</div>
					<MonoText as="div">{row.id}</MonoText>
				</div>
			),
		},
		{
			key: "route",
			header: "Route",
			width: "minmax(0,1fr)",
			hideOnMobile: true,
			cell: (row) =>
				row.route ? (
					<span className="font-mono text-[11.5px] text-text-3">{row.route}</span>
				) : (
					<span className="text-text-3">—</span>
				),
		},
		{
			key: "position",
			header: "Pos",
			width: "60px",
			hideOnMobile: true,
			align: "right",
			cell: (row) => (
				<span className="tabular-nums text-[12px] text-text-3">{row.position ?? 0}</span>
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
						setConfirmDelete(row);
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
				items={[{ label: "Developer", to: "/developer" }, { label: "Module groups" }]}
			/>

			<PageHead
				title="Module groups"
				sub={rows.length === 1 ? "1 group" : `${rows.length} groups`}
				actions={
					<Button
						variant="primary"
						icon={<Plus size={13} />}
						to="/developer/module-groups/add"
					>
						Add group
					</Button>
				}
			/>

			<DeveloperSectionNav />

			<DataTable<ModuleGroup>
				columns={columns}
				rows={rows}
				getRowKey={(row) => row.id}
				isLoading={query.isLoading}
				loadingLabel="Loading groups…"
				emptyLabel="No module groups yet."
				onRowClick={(row) =>
					navigate(`/developer/module-groups/${encodeURIComponent(row.id)}/edit`)
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
					description="Modules in this group keep their definitions but lose their grouping on the Modules tab."
					confirmLabel="Delete group"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(confirmDelete.id)}
				/>
			)}
		</PageContainer>
	);
};
