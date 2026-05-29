import { useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import {
	AlertTriangle,
	CheckCircle2,
	FileText,
	Image as ImageIcon,
	Layers,
	Trash2,
} from "lucide-react";

import { DebugLayout } from "@/components/developer/DebugLayout";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";

import { systemApi } from "@/api/endpoints/system";
import { dashboardApi } from "@/api/endpoints/dashboard";
import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

export const DebugStatus = () => {
	const versionQ = useQuery({
		queryKey: ["system", "version"],
		queryFn: () => systemApi.version(),
	});

	const integrityQ = useQuery({
		queryKey: ["dashboard", "integrity"],
		queryFn: () => dashboardApi.integrity(),
	});

	const [confirmClear, setConfirmClear] = useState(false);

	const clearCache = useMutation({
		mutationFn: () => systemApi.clearCache(),
		onSuccess: () => toast.success("Cache cleared"),
		onError: (err) => {
			const msg =
				err instanceof ApiError && err.message ? err.message : "Could not clear cache";
			toast.error(msg);
		},
	});

	const version = versionQ.data;
	const stats = integrityQ.data;

	const versionRows: Array<{ label: string; value: string }> = version
		? [
				{ label: "BigTree version", value: version.version || "—" },
				{ label: "Build revision", value: String(version.revision) },
				{ label: "PHP version", value: version.php },
			]
		: [];

	const tiles = stats
		? [
				{
					label: "Pages",
					value: stats.pages_total,
					icon: <FileText size={16} className="text-text-3" />,
					warn: false,
				},
				{
					label: "Pages with missing template",
					value: stats.pages_with_missing_template,
					icon: <AlertTriangle size={16} className="text-warn" />,
					warn: stats.pages_with_missing_template > 0,
				},
				{
					label: "Resources",
					value: stats.resources_total,
					icon: <ImageIcon size={16} className="text-text-3" />,
					warn: false,
				},
				{
					label: "Orphan resource allocations",
					value: stats.orphan_resource_allocations,
					icon: <Layers size={16} className="text-warn" />,
					warn: stats.orphan_resource_allocations > 0,
				},
			]
		: [];

	return (
		<DebugLayout
			title="System status"
			sub="Version info and the structural-health snapshot the dashboard computes."
			actions={
				<button
					type="button"
					onClick={() => setConfirmClear(true)}
					disabled={clearCache.isPending}
					className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] font-medium text-text-2 hover:border-border-strong hover:bg-hover disabled:opacity-60"
				>
					<Trash2 size={13} />
					{clearCache.isPending ? "Clearing…" : "Clear cache"}
				</button>
			}
		>
			<section className="mb-5 overflow-hidden rounded-xl border border-border bg-surface">
				<header className="border-b border-border bg-surface-2 px-4 py-2.5 text-[11.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
					Build
				</header>

				{versionQ.isLoading && <p className="p-4 text-[12.5px] text-text-3">Loading…</p>}

				{versionQ.error && (
					<div className="p-4">
						<ErrorPanel error={versionQ.error} />
					</div>
				)}

				{version && (
					<dl className="divide-y divide-border">
						{versionRows.map((row) => (
							<div
								key={row.label}
								className="flex items-center justify-between px-4 py-2.5"
							>
								<dt className="text-[12.5px] text-text-2">{row.label}</dt>
								<dd className="font-mono text-[12.5px] tabular-nums text-text">
									{row.value}
								</dd>
							</div>
						))}
					</dl>
				)}
			</section>

			<h2 className="mb-2 text-[11px] font-semibold uppercase tracking-[0.06em] text-text-3">
				Site integrity
			</h2>

			{integrityQ.isLoading && (
				<p className="text-[12.5px] text-text-3">Loading integrity…</p>
			)}

			{integrityQ.error && <ErrorPanel error={integrityQ.error} />}

			{stats && (
				<>
					<div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
						{tiles.map((tile) => (
							<div
								key={tile.label}
								className={`rounded-lg border border-border bg-surface p-3 ${
									tile.warn ? "border-warn/40 bg-warn-bg/30" : ""
								}`}
							>
								<div className="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.06em] text-text-3">
									{tile.icon}
									{tile.label}
								</div>
								<div
									className={`text-[22px] font-semibold tabular-nums ${
										tile.warn ? "text-warn" : "text-text"
									}`}
								>
									{Number(tile.value).toLocaleString()}
								</div>
							</div>
						))}
					</div>

					{tiles.every((t) => !t.warn) && (
						<div className="mt-3 flex items-center gap-2 rounded-lg border border-border bg-surface p-4 text-[12.5px] text-text-3">
							<CheckCircle2 size={14} className="text-success" />
							No structural issues detected.
						</div>
					)}
				</>
			)}

			<ConfirmDialog
				open={confirmClear}
				onOpenChange={setConfirmClear}
				title="Clear cache?"
				description="Removes BigTree's cache files. They rebuild on next request — safe, but the first few page loads may be slower."
				confirmLabel="Clear cache"
				onConfirm={() => clearCache.mutate()}
			/>
		</DebugLayout>
	);
};
