import { useQuery } from "@tanstack/react-query";

import { PageHead } from "@/components/shell/PageHead";

import { modulesApi } from "@/api/endpoints/modules";
import { useModuleContext } from "@/pages/ModuleLayout";
import { ReportRenderer } from "@/renderer/reports/ReportRenderer";

interface ModuleReportProps {
	reportId: string;
}

/**
 * The per-report runtime page, rendered by <ModuleDispatcher /> when the active
 * action relates to a report. Resolves the report title for the header and hands
 * the filter form + result rendering off to <ReportRenderer />. The underlying
 * data fetches (prepare/run) live inside the renderer so they only fire when the
 * report is actually viewed.
 */
export const ModuleReport = ({ reportId }: ModuleReportProps) => {
	const { moduleId, module } = useModuleContext();

	const reportsQuery = useQuery({
		queryKey: ["modules", "reports", moduleId],
		queryFn: () => modulesApi.reports(moduleId),
		enabled: moduleId !== "",
	});

	const report = reportsQuery.data?.find((r) => r.id === reportId);

	return (
		<>
			<PageHead title={report?.title ?? module?.name ?? "Report"} />

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
		</>
	);
};
