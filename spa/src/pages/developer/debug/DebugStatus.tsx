import { useState } from "react";
import { Link } from "react-router-dom";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Trash2 } from "lucide-react";

import { DebugLayout } from "@/components/developer/DebugLayout";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";

import { systemApi } from "@/api/endpoints/system";
import type { StatusLevel } from "@/api/endpoints/system";
import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

/**
 * Developer → Debug → Site Status.
 *
 * Faithful port of the legacy developer status page: a warnings list
 * (directory writability, bad admin links, missing favicon) followed by the
 * PHP server-parameter checks. Mirrors the legacy red/yellow/green legend.
 */

const STATUS_META: Record<StatusLevel, { label: string; dot: string; text: string }> = {
	bad: { label: "Critical", dot: "bg-danger", text: "text-danger" },
	ok: { label: "Warning", dot: "bg-warn", text: "text-warn" },
	good: { label: "OK", dot: "bg-success", text: "text-success" },
};

const StatusBadge = ({ status, value }: { status: StatusLevel; value?: string }) => {
	const meta = STATUS_META[status];

	return (
		<span className="inline-flex items-center gap-1.5 whitespace-nowrap">
			{value && (
				<span className="font-mono text-[12.5px] tabular-nums text-text-2">{value}</span>
			)}
			<span className={`h-2 w-2 rounded-full ${meta.dot}`} />
			<span className={`text-[11px] font-semibold uppercase tracking-[0.04em] ${meta.text}`}>
				{meta.label}
			</span>
		</span>
	);
};

export const DebugStatus = () => {
	const statusQ = useQuery({
		queryKey: ["system", "status"],
		queryFn: () => systemApi.status(),
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

	const data = statusQ.data;

	return (
		<DebugLayout
			title="Site Status"
			sub="Directory permissions, content warnings, and PHP server parameters for this install."
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
			<p className="mb-5 text-[12.5px] text-text-3">
				Critical errors appear in <span className="font-semibold text-danger">red</span>,
				warnings appear in <span className="font-semibold text-warn">yellow</span>, and
				successes appear in <span className="font-semibold text-success">green</span>.
			</p>

			{statusQ.isLoading && <p className="text-[12.5px] text-text-3">Loading…</p>}

			{statusQ.error && <ErrorPanel error={statusQ.error} />}

			{data && (
				<>
					{data.warnings.length > 0 && (
						<section className="mb-5 overflow-hidden rounded-xl border border-border bg-surface">
							<header className="border-b border-border bg-surface-2 px-4 py-2.5 text-[11.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
								Warnings
							</header>

							<ul className="divide-y divide-border">
								{data.warnings.map((w, i) => (
									<li
										key={i}
										className="flex items-center justify-between gap-4 px-4 py-2.5"
									>
										<div className="min-w-0">
											<div className="text-[12.5px] font-medium text-text">
												{w.parameter}
											</div>
											<div className="mt-0.5 text-[12px] text-text-3">
												{w.page_id ? (
													<>
														Remove links to the admin on{" "}
														<Link
															to={`/pages/${w.page_id}/edit`}
															className="text-accent hover:underline"
														>
															{w.nav_title}
														</Link>
														.
													</>
												) : (
													w.rec
												)}
											</div>
										</div>
										<StatusBadge status={w.status} />
									</li>
								))}
							</ul>
						</section>
					)}

					<section className="overflow-hidden rounded-xl border border-border bg-surface">
						<header className="border-b border-border bg-surface-2 px-4 py-2.5 text-[11.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
							Server Parameters
						</header>

						<ul className="divide-y divide-border">
							{data.parameters.map((p) => (
								<li
									key={p.parameter}
									className="flex items-center justify-between gap-4 px-4 py-2.5"
								>
									<div className="min-w-0">
										<div className="text-[12.5px] font-medium text-text">
											{p.parameter}
										</div>
										<div className="mt-0.5 text-[12px] text-text-3">
											{p.rec}
										</div>
									</div>
									<StatusBadge status={p.status} value={p.value} />
								</li>
							))}
						</ul>
					</section>
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
