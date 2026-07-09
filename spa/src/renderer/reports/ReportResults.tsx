import { useMemo, type ReactNode } from "react";

import { Button } from "@/components/ui/Button";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import type { ModuleReportRunResponse } from "@/api/endpoints/modules";
import { formatCellValue } from "@/renderer/views/viewHelpers";

/**
 * Renders the row payload from POST /modules/{id}/reports/{sid}/run as a
 * tabular DataTable. The column set comes from the report's related view
 * fields (text variant) or — for image-style views — a thumbnail + title
 * fallback. The legacy admin chose between text.php and images.php; we keep
 * the same dispatch but in a single component because the result table is
 * always read-only.
 *
 * The PHP getReportResults service already applies per-column parsers and
 * resolves poplists, so values arrive as primitives the SPA can stringify.
 */

interface ReportResultsProps {
	results: ModuleReportRunResponse;
	onDownloadCsv: () => void;
	downloadIcon: ReactNode;
}

export const ReportResults = ({ results, onDownloadCsv, downloadIcon }: ReportResultsProps) => {
	const { view, items, meta } = results;

	const columns: DataTableColumn<Record<string, unknown>>[] = useMemo(() => {
		const fieldEntries = view && view.fields ? Object.entries(view.fields) : [];

		if (fieldEntries.length === 0) {
			return [
				{
					key: "id",
					header: "ID",
					width: "minmax(0,1fr)",
					cell: (row) => formatCellValue(row.id),
				},
			];
		}

		return fieldEntries.map(([column, field]) => ({
			key: column,
			header: field?.title ?? column,
			width: "minmax(0,1fr)",
			align: field?.numeric ? "right" : "left",
			headerAlign: field?.numeric ? "right" : "left",
			cell: (row) => (
				<span className={field?.numeric ? "tabular-nums text-text-2" : "text-text-2"}>
					{formatCellValue(row[column])}
				</span>
			),
		}));
	}, [view]);

	return (
		<div className="space-y-3">
			<div className="flex items-center justify-between">
				<div className="text-[12.5px] text-text-3">
					{meta.count} row{meta.count === 1 ? "" : "s"}
				</div>
				{items.length > 0 && (
					<Button icon={downloadIcon} onClick={onDownloadCsv}>
						Export CSV
					</Button>
				)}
			</div>

			<DataTable<Record<string, unknown>>
				columns={columns}
				rows={items}
				getRowKey={(row) => {
					const id = row.id;

					if (typeof id === "string" || typeof id === "number") {
						return id;
					}

					return JSON.stringify(row);
				}}
				emptyLabel="No matching rows."
			/>
		</div>
	);
};
