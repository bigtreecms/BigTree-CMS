import type { ModuleReport, ModuleReportRunResponse } from "@/api/endpoints/modules";

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

	const decoder = createDecoder();
	const header = columns.map((c) => csvCell(c.label, decoder));
	const body = response.items.map((row) =>
		columns.map((c) => csvCell(row[c.key], decoder)).join(",")
	);

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

interface CsvColumn {
	key: string;
	label: string;
}

const collectColumns = (response: ModuleReportRunResponse): CsvColumn[] => {
	const { report, view, items } = response;

	if (report.fields) {
		const out = readReportFields(report.fields);

		if (out.length > 0) {
			return out;
		}
	}

	if (view && view.fields) {
		return Object.entries(view.fields).map(([key, field]) => ({
			key,
			label: field?.title ?? key,
		}));
	}

	const first = items[0];

	if (first) {
		return Object.keys(first).map((key) => ({ key, label: key }));
	}

	return [];
};

const readReportFields = (fields: ModuleReport["fields"]): CsvColumn[] => {
	if (!fields) {
		return [];
	}

	if (Array.isArray(fields)) {
		return fields.map((label) => ({ key: String(label), label: String(label) }));
	}

	if (typeof fields === "string") {
		return [{ key: fields, label: fields }];
	}

	return Object.entries(fields).map(([key, label]) => ({ key, label: String(label) }));
};

const csvCell = (value: unknown, decoder: HTMLTextAreaElement | null): string => {
	if (value === null || value === undefined) {
		return '""';
	}

	let raw: string;

	if (typeof value === "string") {
		raw = decodeOnce(value, decoder);
	} else if (typeof value === "number" || typeof value === "boolean") {
		raw = String(value);
	} else {
		try {
			raw = JSON.stringify(value);
		} catch {
			raw = "";
		}
	}

	return `"${raw.replace(/"/g, '""')}"`;
};

const createDecoder = (): HTMLTextAreaElement | null => {
	if (typeof document === "undefined") {
		return null;
	}

	return document.createElement("textarea");
};

const decodeOnce = (value: string, el: HTMLTextAreaElement | null): string => {
	if (!el || value.indexOf("&") === -1) {
		return value;
	}

	el.innerHTML = value;

	return el.value;
};

const csvFilename = (report: ModuleReport): string => {
	const stamp = new Date().toISOString().slice(0, 10);
	const slug = (report.title || "report")
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, "-")
		.replace(/^-+|-+$/g, "");

	return `${slug || "report"}-${stamp}.csv`;
};
