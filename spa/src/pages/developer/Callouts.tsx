import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { HeaderBtn } from "@/components/ui/HeaderBtn";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import { calloutsApi, type CalloutSummary } from "@/api/endpoints/callouts";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

const LEVEL_LABEL = ["Editor", "Admin", "Developer"];

export const Callouts = () => {
	const navigate = useNavigate();
	const queryClient = useQueryClient();
	const [confirmDelete, setConfirmDelete] = useState<CalloutSummary | null>(null);

	const query = useQuery({
		queryKey: ["callouts", "list"],
		queryFn: () => calloutsApi.list(),
	});

	const deleteMutation = useMutation({
		mutationFn: (id: string) => calloutsApi.delete(id),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["callouts"] });
			setConfirmDelete(null);
			toast.success("Callout deleted");
		},
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Delete failed");
		},
	});

	const rows = query.data ?? [];

	const columns: DataTableColumn<CalloutSummary>[] = [
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
			key: "description",
			header: "Description",
			width: "minmax(0,1.5fr)",
			hideOnMobile: true,
			cell: (row) => (
				<span className="truncate text-[12px] text-text-3">{row.description || "—"}</span>
			),
		},
		{
			key: "fields",
			header: "Fields",
			width: "80px",
			hideOnMobile: true,
			align: "right",
			cell: (row) => (
				<span className="tabular-nums text-[12px] text-text-3">{row.resources.length}</span>
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
					title="Delete callout"
					aria-label="Delete callout"
				>
					<Trash size={13} />
				</button>
			),
		},
	];

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb items={[{ label: "Developer", to: "/developer" }, { label: "Callouts" }]} />

			<PageHead
				title="Callouts"
				sub={rows.length === 1 ? "1 callout" : `${rows.length} callouts`}
				actions={
					<HeaderBtn primary icon={<Plus size={13} />} to="/developer/callouts/add">
						Add callout
					</HeaderBtn>
				}
			/>

			<DeveloperSectionNav />

			<DataTable<CalloutSummary>
				columns={columns}
				rows={rows}
				getRowKey={(row) => row.id}
				isLoading={query.isLoading}
				loadingLabel="Loading callouts…"
				emptyLabel="No callouts yet."
				onRowClick={(row) =>
					navigate(`/developer/callouts/${encodeURIComponent(row.id)}/edit`)
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
					description="Removing a callout type can break existing Callouts-field entries that reference it."
					confirmLabel="Delete callout"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(confirmDelete.id)}
				/>
			)}
		</div>
	);
};
