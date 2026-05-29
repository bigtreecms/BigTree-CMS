import { useQuery } from "@tanstack/react-query";
import { AlertTriangle, CheckCircle2, FileText, Image, Layers } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import { dashboardApi } from "@/api/endpoints/dashboard";

/**
 * /system/integrity — admin-only read-out of the integrity stats the server
 * already computes for the dashboard summary. The endpoint also returns
 * verbose row lists (pages_with_missing_template_rows etc.); we keep the
 * stat tiles up top and render any extra arrays as expandable JSON below for
 * publishers who want to drill in without having to hit the API by hand.
 */
export const SiteIntegrity = () => {
	const query = useQuery({
		queryKey: ["dashboard", "integrity"],
		queryFn: () => dashboardApi.integrity(),
	});

	if (query.isLoading) {
		return (
			<div className="mx-auto max-w-screen-lg px-6 py-4">
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading integrity…
				</div>
			</div>
		);
	}

	if (query.error || !query.data) {
		return (
			<div className="mx-auto max-w-screen-lg px-6 py-4">
				<ErrorPanel error={query.error ?? new Error("No integrity data")} />
			</div>
		);
	}

	const stats = query.data;

	const tiles = [
		{
			label: "Pages",
			value: stats.pages_total,
			icon: <FileText size={16} className="text-text-3" />,
			tone: "ok" as const,
		},
		{
			label: "Pages with missing template",
			value: stats.pages_with_missing_template,
			icon: <AlertTriangle size={16} className="text-warn" />,
			tone: (stats.pages_with_missing_template > 0 ? "warn" : "ok") as "ok" | "warn",
		},
		{
			label: "Resources",
			value: stats.resources_total,
			icon: <Image size={16} className="text-text-3" />,
			tone: "ok" as const,
		},
		{
			label: "Orphan resource allocations",
			value: stats.orphan_resource_allocations,
			icon: <Layers size={16} className="text-warn" />,
			tone: (stats.orphan_resource_allocations > 0 ? "warn" : "ok") as "ok" | "warn",
		},
	];

	// Surface any verbose extras the verbose integrity endpoint may return,
	// without hardcoding their names — generic key-by-key render keeps us in
	// sync with whatever the server expands the payload with.
	const verboseExtras = Object.entries(stats).filter(([key, value]) => {
		if (
			key === "pages_with_missing_template" ||
			key === "pages_total" ||
			key === "resources_total" ||
			key === "orphan_resource_allocations"
		) {
			return false;
		}

		return Array.isArray(value) && (value as unknown[]).length > 0;
	});

	return (
		<div className="mx-auto max-w-screen-lg px-6 py-4">
			<Breadcrumb items={[{ label: "System" }, { label: "Integrity" }]} />

			<PageHead
				title="Site integrity"
				sub="Snapshot of the structural health of your install."
			/>

			<div className="mb-4 grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
				{tiles.map((tile) => (
					<div
						key={tile.label}
						className={`rounded-lg border border-border bg-surface p-3 ${
							tile.tone === "warn" ? "border-warn/40 bg-warn-bg/30" : ""
						}`}
					>
						<div className="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.06em] text-text-3">
							{tile.icon}
							{tile.label}
						</div>
						<div
							className={`text-[22px] font-semibold tabular-nums ${
								tile.tone === "warn" ? "text-warn" : "text-text"
							}`}
						>
							{Number(tile.value).toLocaleString()}
						</div>
					</div>
				))}
			</div>

			{verboseExtras.length === 0 ? (
				<div className="flex items-center gap-2 rounded-lg border border-border bg-surface p-4 text-[12.5px] text-text-3">
					<CheckCircle2 size={14} className="text-success" />
					No structural issues detected beyond the summary numbers above.
				</div>
			) : (
				<div className="space-y-3">
					{verboseExtras.map(([key, value]) => (
						<section
							key={key}
							className="overflow-hidden rounded-lg border border-border bg-surface"
						>
							<header className="border-b border-border bg-surface-2 px-3 py-2 text-[11.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
								{key.replace(/_/g, " ")}
							</header>
							<pre className="overflow-x-auto p-3 font-mono text-[11.5px] leading-relaxed text-text-2">
								{JSON.stringify(value, null, 2)}
							</pre>
						</section>
					))}
				</div>
			)}
		</div>
	);
};
