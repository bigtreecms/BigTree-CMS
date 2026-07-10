import type { ModuleReport, ModuleView } from "@/api/endpoints/modules";

export interface ReportColumn {
	key: string;
	label: string;
}

/**
 * Parse a report's `fields` (array | comma-string | key→label dict | nullish)
 * into `{ key, label }` columns. Returns `[]` for no fields so each caller can
 * supply its own default (the CSV export falls back to the view/first-row keys;
 * the on-screen table falls back to an `id` column).
 */
export const parseReportFields = (fields: ModuleReport["fields"]): ReportColumn[] => {
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

/** Columns derived from a view's `fields` map, title falling back to the key. */
export const viewFieldColumns = (view: ModuleView): ReportColumn[] =>
	Object.entries(view.fields ?? {}).map(([key, field]) => ({
		key,
		label: field?.title ?? key,
	}));
