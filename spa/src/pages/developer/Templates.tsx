import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";

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

	const rows = query.data ?? [];

	const columns: DataTableColumn<TemplateSummary>[] = [
		{
			key: "name",
			header: "Name",
			width: "minmax(0,1.5fr)",
			cell: (row) => (
				<div className="min-w-0">
					<div className="truncate font-medium text-text">{row.name}</div>
					<div className="truncate font-mono text-[11px] text-text-3">{row.id}</div>
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
				<button
					type="button"
					className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
					onClick={(e) => {
						e.stopPropagation();
						setConfirmDelete(row);
					}}
					title="Delete template"
					aria-label="Delete template"
				>
					<Trash size={13} />
				</button>
			),
		},
	];

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb
				items={[{ label: "Developer", to: "/developer" }, { label: "Templates" }]}
			/>

			<PageHead
				title="Templates"
				sub={rows.length === 1 ? "1 template" : `${rows.length} templates`}
				actions={
					<Link
						to="/developer/templates/add"
						className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover"
					>
						<Plus size={13} />
						Add template
					</Link>
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
		</div>
	);
};
