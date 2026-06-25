import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
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

import { templatesApi, type TemplateSummary } from "@/api/endpoints/templates";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

const LEVEL_LABEL = ["Editor", "Admin", "Developer"];

export const Templates = () => {
	const navigate = useNavigate();
	const queryClient = useQueryClient();
	const [confirmDelete, setConfirmDelete] = useState<TemplateSummary | null>(null);

	const query = useQuery({
		queryKey: ["templates", "list"],
		queryFn: () => templatesApi.list(),
	});

	const deleteMutation = useMutation({
		mutationFn: (id: string) => templatesApi.delete(id),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["templates"] });
			setConfirmDelete(null);
			toast.success("Template deleted");
		},
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Delete failed");
		},
	});

	const reorderMutation = useMutation({
		mutationFn: (ids: string[]) => templatesApi.reorder(ids),
		onError: () => {
			toast.error("Could not save the new order");
			queryClient.invalidateQueries({ queryKey: ["templates"] });
		},
	});

	const handleReorder = (orderedKeys: Array<string | number>) => {
		const ids = orderedKeys.map(String);

		// Optimistic: reorder the cached list immediately, then persist.
		queryClient.setQueryData<TemplateSummary[]>(["templates", "list"], (prev) => {
			if (!prev) {
				return prev;
			}

			const byId = new Map(prev.map((t) => [t.id, t]));

			return ids.map((id) => byId.get(id)).filter((t): t is TemplateSummary => !!t);
		});

		reorderMutation.mutate(ids);
	};

	const rows = query.data ?? [];

	const columns: DataTableColumn<TemplateSummary>[] = [
		{
			key: "name",
			header: "Name",
			width: "minmax(0,1.5fr)",
			cell: (row) => (
				<div className="min-w-0">
					<div className="truncate font-medium text-text">{row.name}</div>
					<MonoText as="div">{row.id}</MonoText>
				</div>
			),
		},
		{
			key: "module",
			header: "Module",
			width: "minmax(0,1fr)",
			hideOnMobile: true,
			cell: (row) =>
				row.module ? (
					<span className="font-mono text-[11.5px] text-text-3">{row.module}</span>
				) : (
					<span className="text-text-3">—</span>
				),
		},
		{
			key: "level",
			header: "Level",
			width: "120px",
			hideOnMobile: true,
			cell: (row) => (
				<span className="text-[12px] text-text-3">
					{LEVEL_LABEL[row.level] ?? row.level}
				</span>
			),
		},
		{
			key: "routed",
			header: "Routed",
			width: "90px",
			hideOnMobile: true,
			cell: (row) =>
				row.routed ? (
					<span className="rounded bg-info-bg px-1.5 py-0.5 text-[11px] font-medium text-info">
						Routed
					</span>
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
						setConfirmDelete(row);
					}}
					title="Delete template"
					label="Delete template"
				>
					<Trash size={13} />
				</IconButton>
			),
		},
	];

	return (
		<PageContainer width="wide">
			<Breadcrumb
				items={[{ label: "Developer", to: "/developer" }, { label: "Templates" }]}
			/>

			<PageHead
				title="Templates"
				sub={rows.length === 1 ? "1 template" : `${rows.length} templates`}
				actions={
					<Button
						variant="primary"
						icon={<Plus size={13} />}
						to="/developer/templates/add"
					>
						Add template
					</Button>
				}
			/>

			<DeveloperSectionNav />

			<DataTable<TemplateSummary>
				columns={columns}
				rows={rows}
				getRowKey={(row) => row.id}
				isLoading={query.isLoading}
				loadingLabel="Loading templates…"
				emptyLabel="No templates yet."
				onRowClick={(row) =>
					navigate(`/developer/templates/${encodeURIComponent(row.id)}/edit`)
				}
				onReorder={handleReorder}
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
					description="Pages using this template will lose their content schema. This cannot be undone."
					confirmLabel="Delete template"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(confirmDelete.id)}
				/>
			)}
		</PageContainer>
	);
};
