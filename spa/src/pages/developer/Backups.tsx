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
import { IconButton } from "@/components/ui/IconButton";

import { systemApi, type Backup } from "@/api/endpoints/system";
import { formatBytes } from "@/lib/bytes";
import { relativeTime } from "@/lib/time";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useToastMutation } from "@/hooks/useToastMutation";
import { queryKeys } from "@/lib/queryKeys";

export const Backups = () => {
	const listQ = useQuery({
		queryKey: queryKeys.system.backups(),
		queryFn: () => systemApi.backups.list(),
	});

	const deleteDialog = useConfirmDialog<Backup>();

	const createMutation = useToastMutation({
		mutationFn: () => systemApi.backups.create(),
		invalidate: [queryKeys.system.backups()],
		successMessage: "Backup created",
		errorMessage: "Backup failed",
	});

	const deleteMutation = useToastMutation({
		mutationFn: (id: string) => systemApi.backups.remove(id),
		invalidate: [queryKeys.system.backups()],
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
					<Archive className="shrink-0 text-text-3" size={14} />
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
						className="inline-flex items-center gap-1 rounded-md border border-border bg-surface px-2 py-1 text-[11.5px] font-medium text-text-2 hover:border-border-strong hover:bg-hover"
						href={row.download_url}
					>
						<Download size={12} />
						Download
					</a>
					<IconButton
						label="Delete backup"
						title="Delete backup"
						tone="danger"
						onClick={() => deleteDialog.open(row)}
					>
						<Trash2 size={13} />
					</IconButton>
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
				actions={
					<Button
						icon={<Plus size={13} />}
						loading={createMutation.isPending}
						loadingLabel="Backing up…"
						variant="primary"
						onClick={() => createMutation.mutate()}
					>
						New backup
					</Button>
				}
				sub="On-demand SQL dumps of the full database. Download links are short-lived and expire."
				title="Backups"
			/>

			<DeveloperSectionNav />

			{listQ.error ? (
				<ErrorPanel error={listQ.error} />
			) : (
				<DataTable
					columns={columns}
					emptyLabel="No backups yet. Create one to download a snapshot of the database."
					getRowKey={(row) => row.backup_id}
					isLoading={listQ.isLoading}
					loadingLabel="Loading backups…"
					rows={listQ.data ?? []}
				/>
			)}

			<p className="mt-3 text-[11.5px] text-text-3">
				Backups on a large database can take a while — the page stays responsive while one
				runs.
			</p>

			<ConfirmDialog
				{...deleteDialog.dialogProps}
				confirmLabel="Delete"
				description="This removes the backup file from the server. This can't be undone."
				title="Delete backup?"
				variant="danger"
				onConfirm={() => {
					if (deleteDialog.item) {
						deleteMutation.mutate(deleteDialog.item.backup_id);
						deleteDialog.close();
					}
				}}
			/>
		</PageContainer>
	);
};
