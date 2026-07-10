import { useState } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Trash2 } from "lucide-react";

import { DebugLayout } from "@/components/developer/DebugLayout";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";
import { SectionLabel } from "@/components/ui/SectionLabel";
import { LoadingText } from "@/components/ui/LoadingText";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";

import { systemApi } from "@/api/endpoints/system";
import type { StatusLevel } from "@/api/endpoints/system";
import { queryKeys } from "@/lib/queryKeys";
import { useToastMutation } from "@/hooks/useToastMutation";

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
			<span className={`size-2 rounded-full ${meta.dot}`} />
			<span className={`text-[11px] font-semibold uppercase tracking-[0.04em] ${meta.text}`}>
				{meta.label}
			</span>
		</span>
	);
};

export const DebugStatus = () => {
	const statusQ = useQuery({
		queryKey: queryKeys.system.status(),
		queryFn: () => systemApi.status(),
	});

	const [confirmClear, setConfirmClear] = useState(false);

	const clearCache = useToastMutation({
		mutationFn: () => systemApi.clearCache(),
		successMessage: "Cache cleared",
		errorMessage: "Could not clear cache",
	});

	const data = statusQ.data;

	return (
		<DebugLayout
			title="Site Status"
			sub="Directory permissions, content warnings, and PHP server parameters for this install."
			actions={
				<Button
					icon={<Trash2 size={13} />}
					onClick={() => setConfirmClear(true)}
					loading={clearCache.isPending}
					loadingLabel="Clearing…"
				>
					Clear cache
				</Button>
			}
		>
			<p className="mb-5 text-[12.5px] text-text-3">
				Critical errors appear in <span className="font-semibold text-danger">red</span>,
				warnings appear in <span className="font-semibold text-warn">yellow</span>, and
				successes appear in <span className="font-semibold text-success">green</span>.
			</p>

			{statusQ.isLoading && <LoadingText />}

			{statusQ.error && <ErrorPanel error={statusQ.error} />}

			{data && (
				<>
					{data.warnings.length > 0 && (
						<Card className="mb-5 overflow-hidden">
							<CardHeader>
								<SectionLabel size="sm">Warnings</SectionLabel>
							</CardHeader>

							<ul className="divide-y divide-border">
								{data.warnings.map((w) => (
									<li
										key={`${w.parameter}-${w.page_id ?? ""}`}
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
						</Card>
					)}

					<Card className="overflow-hidden">
						<CardHeader>
							<SectionLabel size="sm">Server Parameters</SectionLabel>
						</CardHeader>

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
					</Card>
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
