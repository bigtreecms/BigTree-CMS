import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";

import {
	modulesApi,
	type ModuleReport,
	type ModuleReportBody,
	type ModuleReportFilter,
} from "@/api/endpoints/modules";

import { ConfirmDialog } from "@/components/ui/ConfirmDialog";

import { CheckboxInput, JsonInput, SelectInput, TextInput } from "./inputs";
import { AddSubButton, EditorCard, SubList, SubRow } from "./scaffold";
import { NEW_ROW, useSubCrud } from "./useSubCrud";

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
	filters: Record<string, ModuleReportFilter>;
	fields: Record<string, unknown>;
};

const asFilterRecord = (filters: ModuleReport["filters"]): Record<string, ModuleReportFilter> => {
	if (!filters || Array.isArray(filters)) {
		return {};
	}

	return filters;
};

const asFieldRecord = (fields: ModuleReport["fields"]): Record<string, unknown> => {
	if (fields && typeof fields === "object" && !Array.isArray(fields)) {
		return fields as Record<string, unknown>;
	}

	return {};
};

const draftFromReport = (r: ModuleReport): Draft => ({
	title: r.title ?? "",
	table: r.table ?? "",
	type: r.type === "csv" ? "csv" : "view",
	view: r.view ?? "",
	parser: r.parser ?? "",
	streaming: r.streaming === true || r.streaming === "on",
	filters: asFilterRecord(r.filters),
	fields: asFieldRecord(r.fields),
});

const emptyDraft = (table: string): Draft => ({
	title: "",
	table,
	type: "view",
	view: "",
	parser: "",
	streaming: false,
	filters: {},
	fields: {},
});

export const ModuleReportsTab = ({ moduleId, moduleTable }: ModuleReportsTabProps) => {
	const crud = useSubCrud<ModuleReport, ModuleReportBody>({
		moduleId,
		resource: "reports",
		label: "Report",
		listFn: (id) => modulesApi.reports(id),
		createFn: (id, body) => modulesApi.createReport(id, body),
		updateFn: (id, sid, body) => modulesApi.updateReport(id, sid, body),
		deleteFn: (id, sid) => modulesApi.deleteReport(id, sid),
	});

	const [draft, setDraft] = useState<Draft>(() => emptyDraft(moduleTable));
	const [pendingDelete, setPendingDelete] = useState<ModuleReport | null>(null);

	const viewsQ = useQuery({
		queryKey: ["modules", moduleId, "views"],
		queryFn: () => modulesApi.views(moduleId),
	});

	useEffect(() => {
		if (crud.editingId === NEW_ROW) {
			setDraft(emptyDraft(moduleTable));
		} else if (crud.editingId) {
			const found = crud.items.find((r) => r.id === crud.editingId);

			if (found) {
				setDraft(draftFromReport(found));
			}
		}
	}, [crud.editingId, crud.items, moduleTable]);

	const viewOptions = [
		{ value: "", label: "— No view —" },
		...(viewsQ.data ?? []).map((v) => ({ value: v.id, label: v.title })),
	];

	const toBody = (d: Draft): ModuleReportBody => ({
		title: d.title,
		table: d.table,
		type: d.type,
		view: d.type === "view" ? d.view || null : null,
		parser: d.parser || undefined,
		streaming: d.streaming,
		filters: d.filters,
		fields: d.fields as Record<string, string>,
	});

	const editorTitle = crud.editingId === NEW_ROW ? "New report" : "Edit report";

	return (
		<div className="space-y-3">
			<SubList
				isLoading={crud.isLoading}
				loadingLabel="Loading reports…"
				emptyLabel="No reports yet. Reports filter a table and render the results as a view or CSV."
				isEmpty={crud.items.length === 0}
			>
				{crud.items.map((r) => (
					<SubRow
						key={r.id}
						title={r.title}
						subtitle={r.table}
						badge={r.type}
						onEdit={() => crud.startEdit(r.id)}
						onDelete={() => setPendingDelete(r)}
					/>
				))}
			</SubList>

			{crud.editingId === null && <AddSubButton label="Add report" onClick={crud.startAdd} />}

			{crud.editingId !== null && (
				<EditorCard
					title={editorTitle}
					onClose={crud.cancel}
					onSave={() => crud.save(crud.editingId, toBody(draft))}
					saving={crud.saving}
					saveLabel={crud.editingId === NEW_ROW ? "Create report" : "Save report"}
				>
					<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
						<TextInput
							label="Title"
							value={draft.title}
							onChange={(v) => setDraft((p) => ({ ...p, title: v }))}
							required
						/>
						<TextInput
							label="Data table"
							value={draft.table}
							onChange={(v) => setDraft((p) => ({ ...p, table: v }))}
							mono
							required
						/>
						<SelectInput
							label="Output type"
							value={draft.type}
							onChange={(v) => setDraft((p) => ({ ...p, type: v as "view" | "csv" }))}
							options={[
								{ value: "view", label: "Render in a view" },
								{ value: "csv", label: "CSV download" },
							]}
						/>
						{draft.type === "view" && (
							<SelectInput
								label="Results view"
								value={draft.view}
								onChange={(v) => setDraft((p) => ({ ...p, view: v }))}
								options={viewOptions}
								hint="Supplies the row template for results."
							/>
						)}
					</div>

					<TextInput
						label="Parser"
						value={draft.parser}
						onChange={(v) => setDraft((p) => ({ ...p, parser: v }))}
						hint="Optional PHP parser applied to each result row."
						mono
					/>

					<CheckboxInput
						label="Stream large result sets"
						checked={draft.streaming}
						onChange={(v) => setDraft((p) => ({ ...p, streaming: v }))}
					/>

					<JsonInput
						label="Filters"
						value={draft.filters}
						onChange={(v) =>
							setDraft((p) => ({
								...p,
								filters: v as Record<string, ModuleReportFilter>,
							}))
						}
						hint='Keyed by column: { "status": { "title": "Status", "type": "dropdown", "options": {…} } }'
					/>

					<JsonInput
						label="Fields"
						value={draft.fields}
						onChange={(v) => setDraft((p) => ({ ...p, fields: v }))}
						hint="Keyed by column → heading. Controls which columns appear in CSV output."
					/>
				</EditorCard>
			)}

			{pendingDelete && (
				<ConfirmDialog
					open
					onOpenChange={(open) => {
						if (!open) {
							setPendingDelete(null);
						}
					}}
					title={`Delete report "${pendingDelete.title}"?`}
					description="Actions that open this report will need to be repointed. Entry data is left intact."
					confirmLabel="Delete report"
					variant="danger"
					onConfirm={() => {
						crud.remove(pendingDelete.id);
						setPendingDelete(null);
					}}
				/>
			)}
		</div>
	);
};
