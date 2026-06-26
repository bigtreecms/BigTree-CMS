import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Archive, Download, Plus, Trash2 } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";

import { systemApi, type Backup } from "@/api/endpoints/system";
import { formatBytes } from "@/lib/bytes";
import { relativeTime } from "@/lib/time";
import { useToastMutation } from "@/hooks/useToastMutation";

export const Backups = () => {
	const listQ = useQuery({
		queryKey: ["system", "backups"],
		queryFn: () => systemApi.backups.list(),
	});

	const [pendingDelete, setPendingDelete] = useState<Backup | null>(null);

	const createMutation = useToastMutation({
		mutationFn: () => systemApi.backups.create(),
		invalidate: [["system", "backups"]],
		successMessage: "Backup created",
		errorMessage: "Backup failed",
	});

	const deleteMutation = useToastMutation({
		mutationFn: (id: string) => systemApi.backups.remove(id),
		invalidate: [["system", "backups"]],
		successMessage: "Backup deleted",
		errorMessage: "Could not delete backup",
	});

	const columns: DataTableColumn<Backup>[] = [
		{
			key: "backup_id",
			header: "Backup",
			width: "minmax(0,1.4fr)",
			cell: (row) => (
				<div className="flex items-center gap-2">
					<Archive size={14} className="shrink-0 text-text-3" />
					<span className="truncate font-mono text-[12px] text-text-2">
						{row.backup_id}
					</span>
				</div>
			),
		},
		{
			key: "size",
			header: "Size",
			width: "120px",
			align: "right",
			headerAlign: "right",
			cell: (row) => (
				<span className="tabular-nums text-text-3">{formatBytes(row.size_bytes)}</span>
			),
		},
		{
			key: "created_at",
			header: "Created",
			width: "150px",
			hideOnMobile: true,
			cell: (row) => <span className="text-text-3">{relativeTime(row.created_at)}</span>,
		},
		{
			key: "expires_at",
			header: "Expires",
			width: "150px",
			hideOnMobile: true,
			cell: (row) => <span className="text-text-3">{relativeTime(row.expires_at)}</span>,
		},
		{
			key: "actions",
			header: "",
			width: "150px",
			align: "right",
			headerAlign: "right",
			cell: (row) => (
				<div className="flex items-center justify-end gap-1">
					<a
						href={row.download_url}
						className="inline-flex items-center gap-1 rounded-md border border-border bg-surface px-2 py-1 text-[11.5px] font-medium text-text-2 hover:border-border-strong hover:bg-hover"
					>
						<Download size={12} />
						Download
					</a>
					<button
						type="button"
						onClick={() => setPendingDelete(row)}
						className="inline-flex items-center rounded-md border border-border bg-surface p-1.5 text-text-3 hover:border-danger/40 hover:text-danger"
						aria-label="Delete backup"
					>
						<Trash2 size={13} />
					</button>
				</div>
			),
		},
	];

	return (
		<PageContainer width="wide">
			<Breadcrumb
				items={[
					{ label: "Developer", to: "/developer" },
					{ label: "Debug", to: "/developer/debug" },
					{ label: "Backups" },
				]}
			/>

			<PageHead
				title="Backups"
				sub="On-demand SQL dumps of the full database. Download links are short-lived and expire."
				actions={
					<Button
						variant="primary"
						icon={<Plus size={13} />}
						onClick={() => createMutation.mutate()}
						loading={createMutation.isPending}
						loadingLabel="Backing up…"
					>
						New backup
					</Button>
				}
			/>

			<DeveloperSectionNav />

			{listQ.error ? (
				<ErrorPanel error={listQ.error} />
			) : (
				<DataTable
					columns={columns}
					rows={listQ.data ?? []}
					getRowKey={(row) => row.backup_id}
					isLoading={listQ.isLoading}
					loadingLabel="Loading backups…"
					emptyLabel="No backups yet. Create one to download a snapshot of the database."
				/>
			)}

			<p className="mt-3 text-[11.5px] text-text-3">
				Backups on a large database can take a while — the page stays responsive while one
				runs.
			</p>

			<ConfirmDialog
				open={pendingDelete !== null}
				onOpenChange={(open) => {
					if (!open) {
						setPendingDelete(null);
					}
				}}
				title="Delete backup?"
				description="This removes the backup file from the server. This can't be undone."
				confirmLabel="Delete"
				variant="danger"
				onConfirm={() => {
					if (pendingDelete) {
						deleteMutation.mutate(pendingDelete.backup_id);
						setPendingDelete(null);
					}
				}}
			/>
		</PageContainer>
	);
};
