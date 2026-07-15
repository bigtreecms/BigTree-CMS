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
import { PageContainer } from "@/components/shell/PageContainer";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { Loading } from "@/components/ui/Loading";
import { Card } from "@/components/ui/Card";
import { SectionLabel } from "@/components/ui/SectionLabel";
import { ProgressBar } from "@/components/ui/ProgressBar";

import { integrityApi } from "@/api/endpoints/integrity";
import { useIntegrityScan, type ScanFinding } from "@/hooks/useIntegrityScan";
import { downloadCsv } from "@/lib/csv";
import { formatNumber, pluralize } from "@/lib/number";
import { todayStamp } from "@/lib/time";
import { toast } from "@/lib/toast";
import { queryKeys } from "@/lib/queryKeys";

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
		queryKey: queryKeys.dashboard.integrity(),
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
				`site-integrity-${todayStamp()}.csv`,
				["Location", "Title", "Type", "Broken URL", "Field"],
				rows.map((r) => [
					r.location,
					r.title,
					r.type === "image" ? "Image" : "Link",
					r.url,
					r.field,
				])
			);
			toast.success(`Exported ${pluralize(rows.length, "issue")}`);
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
		<PageContainer width="medium">
			<Breadcrumb
				items={[{ label: "Dashboard", to: "/dashboard" }, { label: "Site Integrity" }]}
			/>

			<PageHead
				actions={
					showResults ? (
						<>
							{isRunning ? (
								<Button icon={<Square size={13} />} onClick={scan.stop}>
									Stop
								</Button>
							) : null}

							<Button
								disabled={exporting || scan.findings.length === 0}
								icon={<Download size={13} />}
								onClick={handleExport}
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
				sub="Scan every page and module entry for broken links and missing images."
				title="Site Integrity"
			/>

			{!showResults ? (
				<StartPanel
					error={stateQuery.error}
					hasSession={hasSession}
					loading={stateQuery.isLoading}
					onReset={() => setConfirmReset(true)}
					onResume={() => scan.start(resumeExternal)}
					onStart={(external) => scan.start(external)}
				/>
			) : (
				<ScanResults
					completed={scan.completed}
					currentLabel={scan.currentLabel}
					external={scan.external}
					findings={scan.findings}
					percent={percent}
					phase={scan.phase}
					total={scan.total}
				/>
			)}

			<ConfirmDialog
				confirmLabel="Reset"
				description="Discards the current scan and any saved progress so the next run starts fresh."
				open={confirmReset}
				title="Reset integrity session?"
				variant="danger"
				onConfirm={handleReset}
				onOpenChange={setConfirmReset}
			/>
		</PageContainer>
	);
};

interface StartPanelProps {
	error: unknown;
	hasSession: boolean;
	loading: boolean;
	onReset: () => void;
	onResume: () => void;
	onStart: (external: boolean) => void;
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
					<ShieldCheck className="text-accent" size={16} />
					An integrity check is already in progress
				</div>
				<p className="mb-4 text-[13px] leading-relaxed text-text-3">
					Resume to continue where the scanner left off, or reset to begin a new scan.
				</p>
				<div className="flex flex-wrap gap-2">
					<Button variant="primary" onClick={onResume}>
						Resume session
					</Button>
					<Button icon={<RotateCcw size={14} />} variant="secondary" onClick={onReset}>
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
				<AlertTriangle className="mt-0.5 shrink-0 text-warn" size={14} />
				<p>
					Including external links takes <strong>significantly longer</strong> and may
					report <strong>false positives</strong>.
				</p>
			</div>
			<div className="flex flex-wrap gap-2">
				<Button
					icon={<Server size={14} />}
					variant="primary"
					onClick={() => onStart(false)}
				>
					Only internal links
				</Button>
				<Button
					icon={<Globe size={14} />}
					variant="secondary"
					onClick={() => onStart(true)}
				>
					Include external links
				</Button>
			</div>
		</Card>
	);
};

interface ScanResultsProps {
	completed: number;
	currentLabel: string;
	external: boolean;
	findings: ScanFinding[];
	percent: number;
	phase: string;
	total: number;
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
						{formatNumber(completed)} / {formatNumber(total)} ({percent}%)
					</span>
				</div>
				<ProgressBar
					className="w-full"
					label="Scan progress"
					size="md"
					tone={done ? "success" : "accent"}
					value={percent}
				/>
				<div className="mt-2 text-[11.5px] text-text-3">
					External link checking {external ? "enabled" : "disabled"}.
				</div>
			</div>

			{findings.length === 0 ? (
				done ? (
					<div className="flex items-center gap-2 rounded-lg border border-border bg-surface p-4 text-[12.5px] text-text-2">
						<CheckCircle2 className="text-success" size={15} />
						No broken links or images were found.
					</div>
				) : (
					<div className="rounded-lg border border-border bg-surface p-4 text-[12.5px] text-text-3">
						No issues found yet…
					</div>
				)
			) : (
				<div className="overflow-hidden rounded-lg border border-border bg-surface">
					<SectionLabel
						as="header"
						className="border-b border-border bg-surface-2 px-3 py-2"
						size="sm"
					>
						{pluralize(findings.length, "issue")} found
					</SectionLabel>
					<ul className="divide-y divide-border">
						{findings.map((finding) => (
							<FindingRow finding={finding} key={finding.key} />
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
				className="mt-0.5 shrink-0 rounded-md border border-border px-2 py-1 text-[11.5px] font-medium text-text-2 transition-colors hover:border-border-strong hover:bg-hover"
				to={finding.editTo}
			>
				Edit
			</Link>
		</li>
	);
};
