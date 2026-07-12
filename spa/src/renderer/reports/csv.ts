import type { ModuleReport, ModuleReportRunResponse } from "@/api/endpoints/modules";

import { downloadCsv as triggerCsvDownload } from "@/lib/csv";
import { decodeHtmlEntitiesDom } from "@/lib/html";
import { todayStamp } from "@/lib/time";
import { parseReportFields, viewFieldColumns, type ReportColumn } from "./reportColumns";

/**
 * Convert a report's run-response into a CSV file and trigger a browser
 * download. The legacy admin streams CSV directly from PHP; the SPA does the
 * serialization client-side because we already have the parsed rows.
 *
 * Column set:
 *   - csv reports use `report.fields` (a dict keyed by column → title) and
 *     respect that order.
 *   - view reports fall back to the related view's fields, then the row keys.
 *
 * Cells are decoded once (legacy storage often double-encodes HTML entities),
 * then passed through `lib/csv.downloadCsv` for formula-injection neutralization
 * and RFC 4180 quoting.
 */

export const downloadCsv = (response: ModuleReportRunResponse) => {
	const columns = collectColumns(response);

	if (columns.length === 0) {
		return;
	}

	triggerCsvDownload(
		csvFilename(response.report),
		columns.map((c) => c.label),
		response.items.map((row) => columns.map((c) => decodedCellString(row[c.key])))
	);
};

type CsvColumn = ReportColumn;

const collectColumns = (response: ModuleReportRunResponse): CsvColumn[] => {
	const { report, view, items } = response;

	if (report.fields) {
		const out = parseReportFields(report.fields);

		if (out.length > 0) {
			return out;
		}
	}

	if (view && view.fields) {
		return viewFieldColumns(view);
	}

	const first = items[0];

	if (first) {
		return Object.keys(first).map((key) => ({ key, label: key }));
	}

	return [];
};

/** Decode / stringify a report cell for CSV export (encoding is lib/csv's job). */
const decodedCellString = (value: unknown): string => {
	if (value === null || value === undefined) {
		return "";
	}

	if (typeof value === "string") {
		return decodeHtmlEntitiesDom(value);
	}

	if (typeof value === "number" || typeof value === "boolean") {
		return String(value);
	}

	try {
		return JSON.stringify(value);
	} catch {
		return "";
	}
};

const csvFilename = (report: ModuleReport): string => {
	const stamp = todayStamp();
	const slug = (report.title || "report")
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, "-")
		.replace(/^-+|-+$/g, "");

	return `${slug || "report"}-${stamp}.csv`;
};
