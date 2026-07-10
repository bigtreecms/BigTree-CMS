import { useMemo, useState } from "react";
import { useQuery, useMutation } from "@tanstack/react-query";
import { Download, Pencil } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Loading } from "@/components/ui/Loading";
import { modulesApi } from "@/api/endpoints/modules";
import type {
	ModuleReport,
	ModuleReportFilter,
	ModuleReportFilterOption,
	ModuleReportRunRequest,
	ModuleReportRunResponse,
} from "@/api/endpoints/modules";
import { pluralize } from "@/lib/number";
import { toast } from "@/lib/toast";
import { moduleDetailPath } from "@/lib/routes";
import { queryKeys } from "@/lib/queryKeys";
import { useAuthStore } from "@/auth/store";
import { LEVEL } from "@/lib/permissions";

import { ReportFilterForm, type ReportFilterFormValues } from "./ReportFilterForm";
import { ReportResults } from "./ReportResults";
import { downloadCsv } from "./csv";
import { parseReportFields, viewFieldColumns } from "./reportColumns";

/**
 * Runtime for a saved module report.
 *
 * Two-step flow that mirrors the legacy PHP admin (auto-modules/report.php):
 *
 *   1. Show the filter form built from `report.filters`, plus sort controls
 *      driven by `report.fields` (csv reports) or the related view's fields
 *      (view reports).
 *   2. POST `/modules/{id}/reports/{sid}/run` with the filter values, then
 *      render the result rows either as a table (view reports) or as a
 *      downloadable CSV (csv reports — server returns rows, we serialize).
 *
 * The filter form's dropdown option sets come from the prepare endpoint so the
 * SPA never has to know the underlying table layout.
 */

export interface ReportRendererProps {
	moduleId: string;
	reportId: string;
}

export const ReportRenderer = ({ moduleId, reportId }: ReportRendererProps) => {
	const user = useAuthStore((s) => s.user);
	const [results, setResults] = useState<ModuleReportRunResponse | null>(null);

	const prepareQuery = useQuery({
		queryKey: queryKeys.modules.reportPrepare(moduleId, reportId),
		queryFn: () => modulesApi.prepareReport(moduleId, reportId),
	});

	const runMutation = useMutation({
		mutationFn: (body: ModuleReportRunRequest) =>
			modulesApi.runReport(moduleId, reportId, body),
		onSuccess: (response) => {
			setResults(response);

			if (response.report.type === "csv") {
				downloadCsv(response);
				toast.success(`Exported ${response.meta.count} rows`);
			}
		},
		onError: () => {
			toast.error("Could not run report");
		},
	});

	const handleSubmit = (values: ReportFilterFormValues) => {
		runMutation.mutate({
			filters: values.filters,
			sort: { field: values.sortField, order: values.sortOrder },
		});
	};

	const handleDownloadCsv = () => {
		if (!results) {
			return;
		}

		downloadCsv(results);
	};

	const report = prepareQuery.data?.report;
	const filters = useMemo(() => collectFilters(report), [report]);
	const sortFields = useMemo(() => collectSortFields(prepareQuery.data), [prepareQuery.data]);

	if (prepareQuery.isLoading) {
		return <Loading variant="card" label="Loading report…" />;
	}

	if (prepareQuery.isError || !report) {
		return <EmptyState>That report doesn't exist on this module.</EmptyState>;
	}

	const isDeveloper = (user?.level ?? 0) >= LEVEL.DEVELOPER;

	return (
		<div className="space-y-6">
			{isDeveloper && (
				<div className="flex justify-end">
					<Button to={moduleDetailPath(moduleId)} icon={<Pencil size={14} />}>
						Edit Report in Developer
					</Button>
				</div>
			)}

			<ReportFilterForm
				filters={filters}
				filterOptions={prepareQuery.data?.filter_options ?? {}}
				sortFields={sortFields}
				reportType={report.type}
				submitting={runMutation.isPending}
				onSubmit={handleSubmit}
			/>

			{results && results.report.type === "view" && (
				<ReportResults
					results={results}
					onDownloadCsv={handleDownloadCsv}
					downloadIcon={<Download size={14} />}
				/>
			)}

			{results && results.report.type === "csv" && (
				<Card padding="lg" className="text-center text-[13px] text-text-2">
					Exported {pluralize(results.meta.count, "row")}.
					<div className="mt-3">
						<Button
							variant="primary"
							icon={<Download size={14} />}
							onClick={handleDownloadCsv}
						>
							Download again
						</Button>
					</div>
				</Card>
			)}
		</div>
	);
};

interface FilterEntry {
	column: string;
	filter: ModuleReportFilter;
}

const collectFilters = (report: ModuleReport | undefined): FilterEntry[] => {
	if (!report || !report.filters) {
		return [];
	}

	if (Array.isArray(report.filters)) {
		return report.filters
			.map((filter, index) => ({ column: String(index), filter }))
			.filter((entry) => entry.filter && typeof entry.filter === "object");
	}

	return Object.entries(report.filters).map(([column, filter]) => ({ column, filter }));
};

interface SortField {
	key: string;
	label: string;
}

const collectSortFields = (
	prepared:
		| {
				report: ModuleReport;
				view: ModuleReportRunResponse["view"];
		  }
		| undefined
): SortField[] => {
	if (!prepared) {
		return [];
	}

	const { report, view } = prepared;

	if (report.type === "csv") {
		if (!report.fields) {
			return [{ key: "id", label: "ID" }];
		}

		return parseReportFields(report.fields);
	}

	if (view && view.fields) {
		return viewFieldColumns(view);
	}

	return [{ key: "id", label: "ID" }];
};

// Re-exported so other modules (e.g. the future module-action launcher) can
// reuse the same filter-option shape without importing through the renderer.
export type { ModuleReportFilterOption };
