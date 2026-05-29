import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Package, Trash2 } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { SlideOver } from "@/components/ui/SlideOver";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import { extensionsApi, type Extension } from "@/api/endpoints/extensions";
import { relativeTime } from "@/lib/time";
import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

export const Extensions = () => {
	const queryClient = useQueryClient();

	const listQ = useQuery({
		queryKey: ["extensions"],
		queryFn: () => extensionsApi.list(),
	});

	const [detail, setDetail] = useState<Extension | null>(null);
	const [pendingDelete, setPendingDelete] = useState<Extension | null>(null);

	const deleteMutation = useMutation({
		mutationFn: (id: string) => extensionsApi.remove(id),
		onSuccess: () => {
			toast.success("Extension uninstalled");
			queryClient.invalidateQueries({ queryKey: ["extensions"] });
		},
		onError: (err) => {
			const msg =
				err instanceof ApiError && err.message
					? err.message
					: "Could not uninstall extension";
			toast.error(msg);
		},
	});

	const columns: DataTableColumn<Extension>[] = [
		{
			key: "name",
			header: "Extension",
			width: "minmax(0,1.6fr)",
			cell: (row) => (
				<div className="flex items-center gap-2">
					<Package size={14} className="shrink-0 text-text-3" />
					<div className="min-w-0">
						<div className="truncate font-medium text-text">{row.name}</div>
						<div className="truncate font-mono text-[11px] text-text-3">{row.id}</div>
					</div>
				</div>
			),
		},
		{
			key: "version",
			header: "Version",
			width: "120px",
			cell: (row) => <span className="tabular-nums text-text-2">{row.version || "—"}</span>,
		},
		{
			key: "installed_at",
			header: "Installed",
			width: "150px",
			hideOnMobile: true,
			cell: (row) => (
				<span className="text-text-3">
					{row.installed_at ? relativeTime(row.installed_at) : "—"}
				</span>
			),
		},
		{
			key: "actions",
			header: "",
			width: "100px",
			align: "right",
			headerAlign: "right",
			cell: (row) => (
				<button
					type="button"
					onClick={(e) => {
						e.stopPropagation();
						setPendingDelete(row);
					}}
					className="inline-flex items-center rounded-md border border-border bg-surface p-1.5 text-text-3 hover:border-danger/40 hover:text-danger"
					aria-label="Uninstall extension"
				>
					<Trash2 size={13} />
				</button>
			),
		},
	];

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Developer", to: "/developer" },
					{ label: "Debug", to: "/developer/debug" },
					{ label: "Extensions" },
				]}
			/>

			<PageHead
				title="Extensions"
				sub="Installed extensions and their manifests. Uninstalling removes everything the extension declares."
			/>

			<DeveloperSectionNav />

			{listQ.error ? (
				<ErrorPanel error={listQ.error} />
			) : (
				<DataTable
					columns={columns}
					rows={listQ.data ?? []}
					getRowKey={(row) => row.id}
					isLoading={listQ.isLoading}
					loadingLabel="Loading extensions…"
					emptyLabel="No extensions installed."
					onRowClick={(row) => setDetail(row)}
				/>
			)}

			<SlideOver
				open={detail !== null}
				onOpenChange={(open) => {
					if (!open) {
						setDetail(null);
					}
				}}
				title={detail?.name ?? "Extension"}
				description={detail?.id}
				width="lg"
			>
				{detail && (
					<div className="space-y-4">
						<dl className="grid grid-cols-[120px_1fr] gap-y-2 text-[12.5px]">
							<dt className="text-text-3">Version</dt>
							<dd className="text-text-2">{detail.version || "—"}</dd>
							<dt className="text-text-3">Installed</dt>
							<dd className="text-text-2">
								{detail.installed_at ? relativeTime(detail.installed_at) : "—"}
							</dd>
						</dl>

						<div>
							<h3 className="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.06em] text-text-3">
								Manifest
							</h3>
							<pre className="overflow-x-auto rounded-lg border border-border bg-surface-2 p-3 font-mono text-[11.5px] leading-relaxed text-text-2">
								{JSON.stringify(detail.manifest, null, 2)}
							</pre>
						</div>
					</div>
				)}
			</SlideOver>

			<ConfirmDialog
				open={pendingDelete !== null}
				onOpenChange={(open) => {
					if (!open) {
						setPendingDelete(null);
					}
				}}
				title="Uninstall extension?"
				description="This removes the extension and every resource its manifest declares (modules, templates, callouts, etc.). This can't be undone."
				confirmLabel="Uninstall"
				variant="danger"
				onConfirm={() => {
					if (pendingDelete) {
						deleteMutation.mutate(pendingDelete.id);
						setPendingDelete(null);
					}
				}}
			/>
		</div>
	);
};
