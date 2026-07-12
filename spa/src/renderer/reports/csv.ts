import type { ModuleReport, ModuleReportRunResponse } from "@/api/endpoints/modules";

import { csvCell as encodeCsvCell } from "@/lib/csv";
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
 * then quoted with embedded `"` doubled to match RFC 4180.
 */

export const downloadCsv = (response: ModuleReportRunResponse) => {
	const columns = collectColumns(response);

	if (columns.length === 0) {
		return;
	}

	const header = columns.map((c) => csvCell(c.label));
	const body = response.items.map((row) => columns.map((c) => csvCell(row[c.key])).join(","));

	const csv = [header.join(","), ...body].join("\n");
	const blob = new Blob(["﻿" + csv], { type: "text/csv;charset=utf-8" });
	const url = URL.createObjectURL(blob);
	const a = document.createElement("a");
	a.href = url;
	a.download = csvFilename(response.report);
	document.body.appendChild(a);
	a.click();
	document.body.removeChild(a);
	URL.revokeObjectURL(url);
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

const csvCell = (value: unknown): string => {
	if (value === null || value === undefined) {
		return '""';
	}

	let raw: string;

	if (typeof value === "string") {
		raw = decodeHtmlEntitiesDom(value);
	} else if (typeof value === "number" || typeof value === "boolean") {
		raw = String(value);
	} else {
		try {
			raw = JSON.stringify(value);
		} catch {
			raw = "";
		}
	}

	return encodeCsvCell(raw);
};

const csvFilename = (report: ModuleReport): string => {
	const stamp = todayStamp();
	const slug = (report.title || "report")
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, "-")
		.replace(/^-+|-+$/g, "");

	return `${slug || "report"}-${stamp}.csv`;
};
