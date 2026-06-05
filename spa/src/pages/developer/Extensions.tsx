import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Link } from "react-router-dom";
import { Hammer, Package, RefreshCw, Trash2, Upload } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { SlideOver } from "@/components/ui/SlideOver";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import { extensionsApi, type Extension } from "@/api/endpoints/extensions";
import { useIgnoredExtensionUpdates } from "@/hooks/useIgnoredExtensionUpdates";
import { relativeTime } from "@/lib/time";
import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

export const Extensions = () => {
	const queryClient = useQueryClient();

	const listQ = useQuery({
		queryKey: ["extensions"],
		queryFn: () => extensionsApi.list(),
	});

	// Update detection is a best-effort, read-only call to the official registry;
	// keep it out of the critical path so the list still renders if it fails.
	const updatesQ = useQuery({
		queryKey: ["extensions", "updates"],
		queryFn: () => extensionsApi.updates(),
		staleTime: 5 * 60_000,
		retry: false,
	});

	const { isIgnored, ignore } = useIgnoredExtensionUpdates();

	const updateById = useMemo(() => {
		const map = new Map<string, boolean>();

		for (const u of updatesQ.data ?? []) {
			map.set(u.id, u.update_available && !isIgnored(u.id));
		}

		return map;
	}, [updatesQ.data, isIgnored]);

	const updateInfoById = useMemo(() => {
		const map = new Map<string, { version: string | null; compatibility: string | null }>();

		for (const u of updatesQ.data ?? []) {
			map.set(u.id, { version: u.available_version, compatibility: u.compatibility });
		}

		return map;
	}, [updatesQ.data]);

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

	const recacheMutation = useMutation({
		mutationFn: () => extensionsApi.recacheHooks(),
		onSuccess: () => toast.success("Refreshed hooks cache"),
		onError: (err) => {
			toast.error(
				err instanceof ApiError && err.message ? err.message : "Could not refresh hooks"
			);
		},
	});

	const upgradeMutation = useMutation({
		mutationFn: (id: string) => extensionsApi.upgrade(id),
		onSuccess: (r) => {
			toast.success(`Upgraded to ${r.version || "the latest version"}`);
			queryClient.invalidateQueries({ queryKey: ["extensions"] });
		},
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Upgrade failed");
		},
	});

	const columns: DataTableColumn<Extension>[] = [
		{
			key: "name",
			header: "Extension",
			width: "minmax(0,1.6fr)",
			cell: (row) => {
				const info = updateInfoById.get(row.id);

				return (
					<div className="flex items-center gap-2">
						<Package size={14} className="shrink-0 text-text-3" />
						<div className="min-w-0">
							<div className="flex items-center gap-2">
								<span className="truncate font-medium text-text">{row.name}</span>
								{updateById.get(row.id) && (
									<span
										className="shrink-0 rounded-full bg-accent-soft px-2 py-0.5 text-[10.5px] font-medium text-accent"
										title={
											info?.version
												? `Version ${info.version} available${
														info.compatibility
															? ` (BigTree ${info.compatibility})`
															: ""
													}`
												: "An update is available"
										}
									>
										Update available
									</span>
								)}
							</div>
							<div className="truncate font-mono text-[11px] text-text-3">
								{row.id}
							</div>
						</div>
					</div>
				);
			},
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
			width: "240px",
			align: "right",
			headerAlign: "right",
			cell: (row) => (
				<div className="flex items-center justify-end gap-1.5">
					{updateById.get(row.id) && (
						<>
							<button
								type="button"
								disabled={upgradeMutation.isPending}
								onClick={(e) => {
									e.stopPropagation();
									upgradeMutation.mutate(row.id);
								}}
								className="rounded-md bg-accent px-2 py-1 text-[11.5px] font-medium text-accent-fg hover:bg-accent-hover disabled:opacity-50"
							>
								{upgradeMutation.isPending && upgradeMutation.variables === row.id
									? "Upgrading…"
									: "Upgrade"}
							</button>
							<button
								type="button"
								onClick={(e) => {
									e.stopPropagation();
									ignore(row.id);
								}}
								className="rounded-md border border-border bg-surface px-2 py-1 text-[11.5px] text-text-3 hover:bg-hover"
							>
								Ignore
							</button>
						</>
					)}
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
				</div>
			),
		},
	];

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb
				items={[{ label: "Developer", to: "/developer" }, { label: "Extensions" }]}
			/>

			<PageHead
				title="Extensions"
				sub="Installed extensions and their manifests. Uninstalling removes everything the extension declares."
				actions={
					<div className="flex items-center gap-2">
						<button
							type="button"
							onClick={() => recacheMutation.mutate()}
							disabled={recacheMutation.isPending}
							className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover disabled:opacity-50"
						>
							<RefreshCw size={13} />
							{recacheMutation.isPending ? "Refreshing…" : "Refresh hooks cache"}
						</button>
						<Link
							to="/developer/extensions/build"
							className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
						>
							<Hammer size={13} />
							Build extension
						</Link>
						<Link
							to="/developer/extensions/install"
							className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover"
						>
							<Upload size={13} />
							Install extension
						</Link>
					</div>
				}
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
