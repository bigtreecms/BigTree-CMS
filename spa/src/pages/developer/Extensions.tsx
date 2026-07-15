import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Hammer, Package, RefreshCw, Trash2, Upload } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { DescriptionList } from "@/components/ui/DescriptionList";
import { MonoText } from "@/components/ui/MonoText";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { IconButton } from "@/components/ui/IconButton";
import { SlideOver } from "@/components/ui/SlideOver";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { SectionLabel } from "@/components/ui/SectionLabel";

import { extensionsApi, type Extension } from "@/api/endpoints/extensions";
import { useIgnoredExtensionUpdates } from "@/hooks/useIgnoredExtensionUpdates";
import { relativeTime } from "@/lib/time";
import { toast } from "@/lib/toast";
import { queryKeys } from "@/lib/queryKeys";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useToastMutation } from "@/hooks/useToastMutation";

export const Extensions = () => {
	const listQ = useQuery({
		queryKey: queryKeys.extensions.root(),
		queryFn: () => extensionsApi.list(),
	});

	// Update detection is a best-effort, read-only call to the official registry;
	// keep it out of the critical path so the list still renders if it fails.
	const updatesQ = useQuery({
		queryKey: queryKeys.extensions.updates(),
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
	const deleteDialog = useConfirmDialog<Extension>();

	const deleteMutation = useToastMutation({
		mutationFn: (id: string) => extensionsApi.remove(id),
		invalidate: [["extensions"]],
		successMessage: "Extension uninstalled",
		errorMessage: "Could not uninstall extension",
	});

	const recacheMutation = useToastMutation({
		mutationFn: () => extensionsApi.recacheHooks(),
		successMessage: "Refreshed hooks cache",
		errorMessage: "Could not refresh hooks",
	});

	const upgradeMutation = useToastMutation({
		mutationFn: (id: string) => extensionsApi.upgrade(id),
		invalidate: [["extensions"]],
		errorMessage: "Upgrade failed",
		onSuccess: (r) => {
			toast.success(`Upgraded to ${r.version || "the latest version"}`);
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
						<Package className="shrink-0 text-text-3" size={14} />
						<div className="min-w-0">
							<div className="flex items-center gap-2">
								<span className="truncate font-medium text-text">{row.name}</span>
								{updateById.get(row.id) && (
									<Badge
										className="shrink-0"
										title={
											info?.version
												? `Version ${info.version} available${
														info.compatibility
															? ` (BigTree ${info.compatibility})`
															: ""
													}`
												: "An update is available"
										}
										tone="accent"
									>
										Update available
									</Badge>
								)}
							</div>
							<MonoText as="div">{row.id}</MonoText>
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
							<Button
								disabled={upgradeMutation.isPending}
								size="sm"
								variant="primary"
								onClick={(e) => {
									e.stopPropagation();
									upgradeMutation.mutate(row.id);
								}}
							>
								{upgradeMutation.isPending && upgradeMutation.variables === row.id
									? "Upgrading…"
									: "Upgrade"}
							</Button>
							<Button
								size="sm"
								variant="secondary"
								onClick={(e) => {
									e.stopPropagation();
									ignore(row.id);
								}}
							>
								Ignore
							</Button>
						</>
					)}
					<IconButton
						label="Uninstall extension"
						title="Uninstall extension"
						tone="danger"
						onClick={(e) => {
							e.stopPropagation();
							deleteDialog.open(row);
						}}
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
				items={[{ label: "Developer", to: "/developer" }, { label: "Extensions" }]}
			/>

			<PageHead
				actions={
					<>
						<Button
							icon={<RefreshCw size={13} />}
							loading={recacheMutation.isPending}
							loadingLabel="Refreshing…"
							onClick={() => recacheMutation.mutate()}
						>
							Refresh hooks cache
						</Button>
						<Button icon={<Hammer size={13} />} to="/developer/extensions/build">
							Build extension
						</Button>
						<Button
							icon={<Upload size={13} />}
							to="/developer/extensions/install"
							variant="primary"
						>
							Install extension
						</Button>
					</>
				}
				sub="Installed extensions and their manifests. Uninstalling removes everything the extension declares."
				title="Extensions"
			/>

			<DeveloperSectionNav />

			{listQ.error ? (
				<ErrorPanel error={listQ.error} />
			) : (
				<DataTable
					columns={columns}
					emptyLabel="No extensions installed."
					getRowKey={(row) => row.id}
					isLoading={listQ.isLoading}
					loadingLabel="Loading extensions…"
					rows={listQ.data ?? []}
					onRowClick={(row) => setDetail(row)}
				/>
			)}

			<SlideOver
				description={detail?.id}
				open={detail !== null}
				title={detail?.name ?? "Extension"}
				width="lg"
				onOpenChange={(open) => {
					if (!open) {
						setDetail(null);
					}
				}}
			>
				{detail && (
					<div className="space-y-4">
						<DescriptionList
							items={[
								{ label: "Version", value: detail.version || "—" },
								{
									label: "Installed",
									value: detail.installed_at
										? relativeTime(detail.installed_at)
										: "—",
								},
							]}
						/>

						<div>
							<SectionLabel as="h3" className="mb-1.5" size="sm">
								Manifest
							</SectionLabel>
							<pre className="overflow-x-auto rounded-lg border border-border bg-surface-2 p-3 font-mono text-[11.5px] leading-relaxed text-text-2">
								{JSON.stringify(detail.manifest, null, 2)}
							</pre>
						</div>
					</div>
				)}
			</SlideOver>

			<ConfirmDialog
				{...deleteDialog.dialogProps}
				confirmLabel="Uninstall"
				description="This removes the extension and every resource its manifest declares (modules, templates, callouts, etc.). This can't be undone."
				title="Uninstall extension?"
				variant="danger"
				onConfirm={() => {
					if (deleteDialog.item) {
						deleteMutation.mutate(deleteDialog.item.id);
						deleteDialog.close();
					}
				}}
			/>
		</PageContainer>
	);
};
