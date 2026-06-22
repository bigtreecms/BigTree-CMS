import { useQuery } from "@tanstack/react-query";
import { Link } from "react-router-dom";
import {
	AlertTriangle,
	CheckCircle2,
	Download,
	Globe,
	Image as ImageIcon,
	Link as LinkIcon,
	RotateCcw,
	Server,
	ShieldCheck,
	Square,
} from "lucide-react";
import { useState } from "react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { Loading } from "@/components/ui/Loading";
import { Card } from "@/components/ui/Card";

import { integrityApi } from "@/api/endpoints/integrity";
import { useIntegrityScan, type ScanFinding } from "@/hooks/useIntegrityScan";
import { downloadCsv } from "@/lib/csv";
import { toast } from "@/lib/toast";

/**
 * /dashboard/integrity — Site Integrity (broken link/image checker).
 *
 * Replaces the dashboard "Site Integrity" tool from the legacy admin: an
 * incremental scan of every page and module entry for dead links, broken
 * internal-page/resource links, and missing images (external links optionally).
 * The scan streams progress and findings as it walks the work list, can resume
 * an interrupted session, and exports results to CSV.
 */
export const SiteIntegrity = () => {
	const scan = useIntegrityScan();
	const [confirmReset, setConfirmReset] = useState(false);
	const [exporting, setExporting] = useState(false);

	const stateQuery = useQuery({
		queryKey: ["dashboard", "integrity", "state"],
		queryFn: () => integrityApi.state(),
		// While a scan is running the server state is mid-flight; don't refetch.
		enabled: scan.phase === "idle",
	});

	const hasSession =
		!!stateQuery.data && (stateQuery.data.internal_session || stateQuery.data.external_session);
	const resumeExternal = stateQuery.data?.external_session ?? false;

	const handleReset = async () => {
		try {
			await integrityApi.reset();
			scan.clear();
			await stateQuery.refetch();
			toast.success("Integrity check session reset");
		} catch {
			toast.error("Could not reset the session");
		}
	};

	const handleExport = async () => {
		setExporting(true);

		try {
			const rows = await integrityApi.export(scan.external);

			if (rows.length === 0) {
				toast.info("No issues to export");

				return;
			}

			downloadCsv(
				`site-integrity-${new Date().toISOString().slice(0, 10)}.csv`,
				["Location", "Title", "Type", "Broken URL", "Field"],
				rows.map((r) => [
					r.location,
					r.title,
					r.type === "image" ? "Image" : "Link",
					r.url,
					r.field,
				])
			);
			toast.success(`Exported ${rows.length} ${rows.length === 1 ? "issue" : "issues"}`);
		} catch {
			toast.error("CSV export failed");
		} finally {
			setExporting(false);
		}
	};

	const percent = scan.total > 0 ? Math.round((scan.completed / scan.total) * 100) : 0;
	const isRunning = scan.phase === "scanning";
	const showResults = scan.phase !== "idle";

	return (
		<div className="mx-auto max-w-5xl px-6 py-4">
			<Breadcrumb
				items={[{ label: "Dashboard", to: "/dashboard" }, { label: "Site Integrity" }]}
			/>

			<PageHead
				title="Site Integrity"
				sub="Scan every page and module entry for broken links and missing images."
				actions={
					showResults ? (
						<>
							{isRunning ? (
								<Button icon={<Square size={13} />} onClick={scan.stop}>
									Stop
								</Button>
							) : null}

							<Button
								icon={<Download size={13} />}
								onClick={handleExport}
								disabled={exporting || scan.findings.length === 0}
							>
								{exporting ? "Exporting…" : "Export CSV"}
							</Button>

							<Button
								icon={<RotateCcw size={13} />}
								onClick={() => setConfirmReset(true)}
							>
								Reset
							</Button>
						</>
					) : undefined
				}
			/>

			{!showResults ? (
				<StartPanel
					loading={stateQuery.isLoading}
					error={stateQuery.error}
					hasSession={hasSession}
					onStart={(external) => scan.start(external)}
					onResume={() => scan.start(resumeExternal)}
					onReset={() => setConfirmReset(true)}
				/>
			) : (
				<ScanResults
					phase={scan.phase}
					percent={percent}
					completed={scan.completed}
					total={scan.total}
					currentLabel={scan.currentLabel}
					external={scan.external}
					findings={scan.findings}
				/>
			)}

			<ConfirmDialog
				open={confirmReset}
				onOpenChange={setConfirmReset}
				title="Reset integrity session?"
				description="Discards the current scan and any saved progress so the next run starts fresh."
				confirmLabel="Reset"
				variant="danger"
				onConfirm={handleReset}
			/>
		</div>
	);
};

interface StartPanelProps {
	loading: boolean;
	error: unknown;
	hasSession: boolean;
	onStart: (external: boolean) => void;
	onResume: () => void;
	onReset: () => void;
}

const StartPanel = ({
	loading,
	error,
	hasSession,
	onStart,
	onResume,
	onReset,
}: StartPanelProps) => {
	if (loading) {
		return <Loading variant="card" />;
	}

	if (error) {
		return <ErrorPanel error={error} />;
	}

	if (hasSession) {
		return (
			<Card className="p-6">
				<div className="mb-1 flex items-center gap-2 text-[14px] font-semibold text-text">
					<ShieldCheck size={16} className="text-accent" />
					An integrity check is already in progress
				</div>
				<p className="mb-4 text-[13px] leading-relaxed text-text-3">
					Resume to continue where the scanner left off, or reset to begin a new scan.
				</p>
				<div className="flex flex-wrap gap-2">
					<Button variant="primary" onClick={onResume}>
						Resume session
					</Button>
					<Button variant="secondary" icon={<RotateCcw size={14} />} onClick={onReset}>
						Reset
					</Button>
				</div>
			</Card>
		);
	}

	return (
		<Card className="p-6">
			<p className="mb-2 text-[13px] leading-relaxed text-text-2">
				The site integrity check searches your site for broken or dead links and missing
				images and alerts you to their presence.
			</p>
			<div className="mb-5 flex items-start gap-2 text-[12.5px] leading-relaxed text-text-3">
				<AlertTriangle size={14} className="mt-0.5 shrink-0 text-warn" />
				<p>
					Including external links takes <strong>significantly longer</strong> and may
					report <strong>false positives</strong>.
				</p>
			</div>
			<div className="flex flex-wrap gap-2">
				<Button
					variant="primary"
					icon={<Server size={14} />}
					onClick={() => onStart(false)}
				>
					Only internal links
				</Button>
				<Button
					variant="secondary"
					icon={<Globe size={14} />}
					onClick={() => onStart(true)}
				>
					Include external links
				</Button>
			</div>
		</Card>
	);
};

interface ScanResultsProps {
	phase: string;
	percent: number;
	completed: number;
	total: number;
	currentLabel: string;
	external: boolean;
	findings: ScanFinding[];
}

const ScanResults = ({
	phase,
	percent,
	completed,
	total,
	currentLabel,
	external,
	findings,
}: ScanResultsProps) => {
	const done = phase === "done";
	const paused = phase === "paused";

	return (
		<div className="space-y-4">
			<div className="rounded-lg border border-border bg-surface p-4">
				<div className="mb-2 flex items-center justify-between gap-3 text-[12.5px]">
					<span className="font-medium text-text-2">
						{done
							? "Scan complete"
							: paused
								? "Scan paused"
								: currentLabel || "Scanning…"}
					</span>
					<span className="tabular-nums text-text-3">
						{completed.toLocaleString()} / {total.toLocaleString()} ({percent}%)
					</span>
				</div>
				<div className="h-2 w-full overflow-hidden rounded-full bg-surface-2">
					<div
						className={`h-full rounded-full transition-[width] duration-300 ${
							done ? "bg-success" : "bg-accent"
						}`}
						style={{ width: `${percent}%` }}
					/>
				</div>
				<div className="mt-2 text-[11.5px] text-text-3">
					External link checking {external ? "enabled" : "disabled"}.
				</div>
			</div>

			{findings.length === 0 ? (
				done ? (
					<div className="flex items-center gap-2 rounded-lg border border-border bg-surface p-4 text-[12.5px] text-text-2">
						<CheckCircle2 size={15} className="text-success" />
						No broken links or images were found.
					</div>
				) : (
					<div className="rounded-lg border border-border bg-surface p-4 text-[12.5px] text-text-3">
						No issues found yet…
					</div>
				)
			) : (
				<div className="overflow-hidden rounded-lg border border-border bg-surface">
					<header className="flex items-center justify-between border-b border-border bg-surface-2 px-3 py-2 text-[11.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
						<span>
							{findings.length} issue{findings.length === 1 ? "" : "s"} found
						</span>
					</header>
					<ul className="divide-y divide-border">
						{findings.map((finding) => (
							<FindingRow key={finding.key} finding={finding} />
						))}
					</ul>
				</div>
			)}
		</div>
	);
};

const FindingRow = ({ finding }: { finding: ScanFinding }) => {
	return (
		<li className="flex items-start gap-3 px-3 py-2.5">
			<span className="mt-0.5 shrink-0 text-warn">
				{finding.type === "image" ? <ImageIcon size={15} /> : <LinkIcon size={15} />}
			</span>
			<div className="min-w-0 flex-1">
				<div className="text-[12.5px] text-text">
					Broken {finding.type === "image" ? "image" : "link"}:{" "}
					<span className="break-all font-mono text-[12px] text-warn">{finding.url}</span>
				</div>
				<div className="mt-0.5 text-[11.5px] text-text-3">
					<span className="text-text-2">{finding.source}</span>
					{finding.field ? <> — field “{finding.field}”</> : null}
				</div>
			</div>
			<Link
				to={finding.editTo}
				className="mt-0.5 shrink-0 rounded-md border border-border px-2 py-1 text-[11.5px] font-medium text-text-2 transition-colors hover:border-border-strong hover:bg-hover"
			>
				Edit
			</Link>
		</li>
	);
};
