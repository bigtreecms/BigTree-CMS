import { useCallback, useEffect, useRef } from "react";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useQuery } from "@tanstack/react-query";

import {
	modulesApi,
	type ModuleReport,
	type ModuleReportBody,
	type ModuleReportFilter,
} from "@/api/endpoints/modules";

import { useDbColumns } from "@/hooks/useDbColumns";
import { queryKeys } from "@/lib/queryKeys";

import { FieldGrid } from "@/components/ui/FieldGrid";

import { DataTableSelect } from "@/components/developer/DataTableSelect";

import { CheckboxInput, SelectInput, TextInput } from "./inputs";
import {
	defaultFilterType,
	humanizeColumn,
	ReportFieldsEditor,
	ReportFiltersEditor,
	type FieldRow,
	type FilterRow,
} from "./ReportColumnEditors";
import { AddSubButton, EditorCard, SubDeleteDialog, SubList, SubRow } from "./scaffold";
import { NEW_ROW, useSubCrud } from "./useSubCrud";
import { InlineEmpty } from "@/components/ui/InlineEmpty";

interface ModuleReportsTabProps {
	moduleId: string;
	moduleTable: string;
}

type Draft = {
	title: string;
	table: string;
	type: "view" | "csv";
	view: string;
	parser: string;
	streaming: boolean;
	filters: FilterRow[];
	fields: FieldRow[];
};

const filterRowsFromReport = (filters: ModuleReport["filters"]): FilterRow[] => {
	if (!filters || Array.isArray(filters)) {
		return [];
	}

	return Object.entries(filters).map(([column, filter]) => {
		const { title, type, ...rest } = filter;

		return {
			column,
			title: title ?? humanizeColumn(column),
			type: type ?? "search",
			rest,
		};
	});
};

const fieldRowsFromReport = (fields: ModuleReport["fields"]): FieldRow[] => {
	if (!fields || typeof fields !== "object" || Array.isArray(fields)) {
		return [];
	}

	return Object.entries(fields).map(([column, title]) => ({
		column,
		title: typeof title === "string" ? title : humanizeColumn(column),
	}));
};

const draftFromReport = (r: ModuleReport): Draft => ({
	title: r.title ?? "",
	table: r.table ?? "",
	type: r.type === "csv" ? "csv" : "view",
	view: r.view ?? "",
	parser: r.parser ?? "",
	streaming: r.streaming === true || r.streaming === "on",
	filters: filterRowsFromReport(r.filters),
	fields: fieldRowsFromReport(r.fields),
});

const emptyDraft = (table: string): Draft => ({
	title: "",
	table,
	type: "view",
	view: "",
	parser: "",
	streaming: false,
	filters: [],
	fields: [],
});

const filtersToRecord = (rows: FilterRow[]): Record<string, ModuleReportFilter> => {
	const record: Record<string, ModuleReportFilter> = {};

	for (const row of rows) {
		if (row.column) {
			record[row.column] = { ...row.rest, title: row.title, type: row.type };
		}
	}

	return record;
};

const fieldsToRecord = (rows: FieldRow[]): Record<string, string> => {
	const record: Record<string, string> = {};

	for (const row of rows) {
		if (row.column) {
			record[row.column] = row.title;
		}
	}

	return record;
};

const toBody = (d: Draft): ModuleReportBody => ({
	title: d.title,
	table: d.table,
	type: d.type,
	view: d.type === "view" ? d.view || null : null,
	parser: d.parser || undefined,
	streaming: d.streaming,
	filters: filtersToRecord(d.filters),
	fields: fieldsToRecord(d.fields),
});

export const ModuleReportsTab = ({ moduleId, moduleTable }: ModuleReportsTabProps) => {
	// Tracks the table whose columns were last auto-populated into the draft so
	// we regenerate defaults when (and only when) the table actually changes —
	// without clobbering a saved report's own filters/fields on edit.
	const builtForTable = useRef<string | null>(null);

	// Keep builtForTable aligned with the selection as the shared draft sync runs:
	// a fresh report auto-populates from its table's columns; a saved report's own
	// filters/fields are authoritative, so mark its table as already built.
	const handleSync = useCallback((item: ModuleReport | null) => {
		builtForTable.current = item ? (item.table ?? "") : null;
	}, []);

	const crud = useSubCrud<ModuleReport, ModuleReportBody, Draft>({
		moduleId,
		resource: "reports",
		label: "Report",
		moduleTable,
		emptyDraft,
		draftFromItem: draftFromReport,
		onSync: handleSync,
		listFn: (id) => modulesApi.reports(id),
		createFn: (id, body) => modulesApi.createReport(id, body),
		updateFn: (id, sid, body) => modulesApi.updateReport(id, sid, body),
		deleteFn: (id, sid) => modulesApi.deleteReport(id, sid),
	});

	const { draft, setDraft } = crud;
	const deleteDialog = useConfirmDialog<ModuleReport>();

	const viewsQ = useQuery({
		queryKey: queryKeys.modules.moduleViews(moduleId),
		queryFn: () => modulesApi.views(moduleId),
	});

	const columnsQ = useDbColumns(draft.table, {
		enabled: crud.editingId !== null && draft.table !== "",
	});

	// When a (new or changed) table's columns arrive, seed every column as a
	// default filter and CSV field — the legacy load-report.php behavior.
	useEffect(() => {
		const columns = columnsQ.data;

		if (!draft.table || !columns) {
			return;
		}

		if (builtForTable.current === draft.table) {
			return;
		}

		builtForTable.current = draft.table;

		setDraft((p) => ({
			...p,
			filters: columns.map((c) => ({
				column: c.value,
				title: humanizeColumn(c.value),
				type: defaultFilterType(c),
				rest: {},
			})),
			fields: columns.map((c) => ({
				column: c.value,
				title: humanizeColumn(c.value),
			})),
		}));
	}, [columnsQ.data, draft.table, setDraft]);

	const viewOptions = [
		{ value: "", label: "— No view —" },
		...(viewsQ.data ?? []).map((v) => ({ value: v.id, label: v.title })),
	];

	const editorTitle = crud.editingId === NEW_ROW ? "New report" : "Edit report";

	return (
		<div className="space-y-3">
			<SubList
				emptyLabel="No reports yet. Reports filter a table and render the results as a view or CSV."
				isEmpty={crud.items.length === 0}
				isLoading={crud.isLoading}
				loadingLabel="Loading reports…"
			>
				{crud.items.map((r) => (
					<SubRow
						badge={r.type}
						key={r.id}
						subtitle={r.table}
						title={r.title}
						onDelete={() => deleteDialog.open(r)}
						onEdit={() => crud.startEdit(r.id)}
					/>
				))}
			</SubList>

			{crud.editingId === null && <AddSubButton label="Add report" onClick={crud.startAdd} />}

			{crud.editingId !== null && (
				<EditorCard
					saveLabel={crud.editingId === NEW_ROW ? "Create report" : "Save report"}
					saving={crud.saving}
					title={editorTitle}
					onClose={crud.cancel}
					onSave={() =>
						crud.save(crud.editingId, toBody(draft), [
							{ field: "title", label: "Title", value: draft.title },
							{ field: "table", label: "Data table", value: draft.table },
						])
					}
				>
					<FieldGrid>
						<TextInput
							required
							error={crud.fieldErrors.title}
							label="Title"
							value={draft.title}
							onChange={(v) => setDraft((p) => ({ ...p, title: v }))}
						/>
						<DataTableSelect
							required
							error={crud.fieldErrors.table}
							label="Data table"
							value={draft.table}
							onChange={(v) => setDraft((p) => ({ ...p, table: v }))}
						/>
						<SelectInput
							label="Output type"
							options={[
								{ value: "view", label: "Render in a view" },
								{ value: "csv", label: "CSV download" },
							]}
							value={draft.type}
							onChange={(v) => setDraft((p) => ({ ...p, type: v as "view" | "csv" }))}
						/>
						{draft.type === "view" && (
							<SelectInput
								hint="Supplies the row template for results."
								label="Results view"
								options={viewOptions}
								value={draft.view}
								onChange={(v) => setDraft((p) => ({ ...p, view: v }))}
							/>
						)}
					</FieldGrid>

					<TextInput
						mono
						hint="Optional PHP parser applied to each result row."
						label="Parser"
						value={draft.parser}
						onChange={(v) => setDraft((p) => ({ ...p, parser: v }))}
					/>

					<CheckboxInput
						checked={draft.streaming}
						label="Stream large result sets"
						onChange={(v) => setDraft((p) => ({ ...p, streaming: v }))}
					/>

					{draft.table === "" ? (
						<InlineEmpty align="center">
							Choose a data table to configure filters and fields.
						</InlineEmpty>
					) : (
						<>
							<ReportFiltersEditor
								columns={columnsQ.data ?? []}
								loading={columnsQ.isLoading}
								rows={draft.filters}
								onChange={(rows) => setDraft((p) => ({ ...p, filters: rows }))}
							/>

							{draft.type === "csv" && (
								<ReportFieldsEditor
									columns={columnsQ.data ?? []}
									loading={columnsQ.isLoading}
									rows={draft.fields}
									onChange={(rows) => setDraft((p) => ({ ...p, fields: rows }))}
								/>
							)}
						</>
					)}
				</EditorCard>
			)}

			<SubDeleteDialog
				description="Actions that open this report will need to be repointed. Entry data is left intact."
				dialog={deleteDialog}
				labelFor={(r) => r.title}
				noun="report"
				onConfirm={(id) => crud.remove(id)}
			/>
		</div>
	);
};
