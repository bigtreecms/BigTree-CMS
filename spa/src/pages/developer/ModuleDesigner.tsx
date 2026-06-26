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

import { modulesApi, type ModuleSummary } from "@/api/endpoints/modules";

import { queryKeys } from "@/lib/queryKeys";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useToastMutation } from "@/hooks/useToastMutation";

/**
 * /developer/modules — the module designer landing. Lists every installed
 * module with delete and a link into the per-module tabbed editor. Module
 * ordering lives on the module group editor (modules are ordered within their
 * group), so this list has no reorder affordance. Distinct from the consumer
 * /modules tab (Modules.tsx) which groups modules for navigation; this is the
 * developer-facing CRUD list.
 */
export const ModuleDesigner = () => {
	const navigate = useNavigate();
	const deleteDialog = useConfirmDialog<ModuleSummary>();

	const query = useQuery({
		queryKey: queryKeys.modules.list(),
		queryFn: () => modulesApi.list(),
	});

	const rows = [...(query.data ?? [])].sort((a, b) =>
		a.name.localeCompare(b.name, undefined, { sensitivity: "base" })
	);

	const deleteMutation = useToastMutation({
		mutationFn: (id: string) => modulesApi.delete(id),
		invalidate: [["modules"]],
		successMessage: "Module deleted",
		errorMessage: "Delete failed",
		onSuccess: () => {
			deleteDialog.close();
		},
	});

	const columns: DataTableColumn<ModuleSummary>[] = [
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
			key: "group",
			header: "Group",
			width: "minmax(0,1fr)",
			hideOnMobile: true,
			cell: (row) =>
				row.group_name ? (
					<span className="text-[12.5px] text-text-2">{row.group_name}</span>
				) : (
					<span className="text-text-3">—</span>
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
					title="Delete module"
					label="Delete module"
				>
					<Trash size={13} />
				</IconButton>
			),
		},
	];

	return (
		<PageContainer width="wide">
			<Breadcrumb items={[{ label: "Developer", to: "/developer" }, { label: "Modules" }]} />

			<PageHead
				title="Modules"
				sub={rows.length === 1 ? "1 module" : `${rows.length} modules`}
				actions={
					<Button variant="primary" icon={<Plus size={13} />} to="/developer/modules/add">
						New module
					</Button>
				}
			/>

			<DeveloperSectionNav />

			<DataTable<ModuleSummary>
				columns={columns}
				rows={rows}
				getRowKey={(row) => row.id}
				isLoading={query.isLoading}
				loadingLabel="Loading modules…"
				emptyLabel="No modules defined yet."
				onRowClick={(row) => navigate(`/developer/modules/${encodeURIComponent(row.id)}`)}
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
					description="This removes the module and all of its actions, forms, views, reports and embed forms. Content rows in the module's table are left intact."
					confirmLabel="Delete module"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(deleteDialog.item!.id)}
				/>
			)}
		</PageContainer>
	);
};
