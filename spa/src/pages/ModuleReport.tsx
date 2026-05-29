import { Navigate, useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";

import { modulesApi } from "@/api/endpoints/modules";
import { ReportRenderer } from "@/renderer/reports/ReportRenderer";

/**
 * /modules/:id/report/:sid — the per-report runtime page.
 *
 * Resolves the module + report title for the breadcrumb / header and hands
 * the actual filter form + result rendering off to <ReportRenderer />. The
 * underlying data fetches (prepare/run) live inside the renderer so they only
 * fire when the report is actually viewed.
 */
export const ModuleReport = () => {
	const { id, sid } = useParams<{ id: string; sid: string }>();
	const moduleId = id ?? "";
	const reportId = sid ?? "";

	const moduleQuery = useQuery({
		queryKey: ["modules", "detail", moduleId],
		queryFn: () => modulesApi.get(moduleId),
		enabled: moduleId !== "",
	});

	const reportsQuery = useQuery({
		queryKey: ["modules", "reports", moduleId],
		queryFn: () => modulesApi.reports(moduleId),
		enabled: moduleId !== "",
	});

	if (moduleId === "" || reportId === "") {
		return <Navigate to="/modules" replace />;
	}

	const report = reportsQuery.data?.find((r) => r.id === reportId);

	const breadcrumbs = [
		{ label: "Modules", to: "/modules" },
		{ label: moduleQuery.data?.name ?? "…", to: `/modules/${encodeURIComponent(moduleId)}` },
		{ label: report?.title ?? "Report" },
	];

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb items={breadcrumbs} />

			<PageHead title={report?.title ?? moduleQuery.data?.name ?? "Report"} />

			{reportsQuery.isLoading ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading report…
				</div>
			) : !report ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					That report doesn't exist on this module.
				</div>
			) : (
				<ReportRenderer moduleId={moduleId} reportId={reportId} />
			)}
		</div>
	);
};
