import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useQuery } from "@tanstack/react-query";

import { queryKeys } from "@/lib/queryKeys";

import {
	modulesApi,
	type ModuleForm,
	type ModuleFormBody,
	type ModuleFormField,
} from "@/api/endpoints/modules";

import { FieldGrid } from "@/components/ui/FieldGrid";

import { DataTableSelect } from "@/components/developer/DataTableSelect";

import { CheckboxInput, SelectInput, TextInput } from "./inputs";
import { ModuleFieldsSection } from "./ModuleFieldsSection";
import { useModuleFieldsValidation } from "./useModuleFieldsValidation";
import { AddSubButton, EditorCard, SubDeleteDialog, SubList, SubRow } from "./scaffold";
import { NEW_ROW, useSubCrud } from "./useSubCrud";

interface ModuleFormsTabProps {
	moduleId: string;
	moduleTable: string;
}

type Draft = {
	title: string;
	table: string;
	default_position: string;
	return_view: string;
	return_url: string;
	tagging: boolean;
	open_graph: boolean;
	fields: ModuleFormField[];
	hooks: Record<string, unknown> | unknown[];
};

const draftFromForm = (f: ModuleForm): Draft => ({
	title: f.title ?? "",
	table: f.table ?? "",
	default_position: f.default_position ?? "",
	return_view: f.return_view ?? "",
	return_url: f.return_url ?? "",
	tagging: f.tagging === true || f.tagging === "on",
	open_graph: f.open_graph === true || f.open_graph === "on",
	fields: Array.isArray(f.fields) ? f.fields : [],
	hooks: (f.hooks as Record<string, unknown>) ?? {},
});

const emptyDraft = (table: string): Draft => ({
	title: "",
	table,
	default_position: "",
	return_view: "",
	return_url: "",
	tagging: false,
	open_graph: false,
	fields: [],
	hooks: {},
});

const toBody = (d: Draft): ModuleFormBody => ({
	title: d.title,
	table: d.table,
	fields: d.fields,
	default_position: d.default_position || undefined,
	return_view: d.return_view || null,
	return_url: d.return_url || undefined,
	tagging: d.tagging,
	open_graph: d.open_graph,
	hooks: d.hooks,
});

export const ModuleFormsTab = ({ moduleId, moduleTable }: ModuleFormsTabProps) => {
	const crud = useSubCrud<ModuleForm, ModuleFormBody, Draft>({
		moduleId,
		resource: "forms",
		label: "Form",
		moduleTable,
		emptyDraft,
		draftFromItem: draftFromForm,
		listFn: (id) => modulesApi.forms(id),
		createFn: (id, body) => modulesApi.createForm(id, body),
		updateFn: (id, sid, body) => modulesApi.updateForm(id, sid, body),
		deleteFn: (id, sid) => modulesApi.deleteForm(id, sid),
	});

	const { draft, setDraft } = crud;
	const deleteDialog = useConfirmDialog<ModuleForm>();
	const { settingsErrors, validate } = useModuleFieldsValidation(draft.fields, crud.editingId);

	const viewsQ = useQuery({
		queryKey: queryKeys.modules.moduleViews(moduleId),
		queryFn: () => modulesApi.views(moduleId),
	});

	const viewOptions = [
		{ value: "", label: "— Return to default view —" },
		...(viewsQ.data ?? []).map((v) => ({ value: v.id, label: v.title })),
	];

	const editorTitle = crud.editingId === NEW_ROW ? "New form" : "Edit form";

	return (
		<div className="space-y-3">
			<SubList
				emptyLabel="No forms yet. A form defines the add/edit screen for this module's entries."
				isEmpty={crud.items.length === 0}
				isLoading={crud.isLoading}
				loadingLabel="Loading forms…"
			>
				{crud.items.map((f) => (
					<SubRow
						badge={`${Array.isArray(f.fields) ? f.fields.length : 0} fields`}
						key={f.id}
						subtitle={f.table}
						title={f.title}
						onDelete={() => deleteDialog.open(f)}
						onEdit={() => crud.startEdit(f.id)}
					/>
				))}
			</SubList>

			{crud.editingId === null && <AddSubButton label="Add form" onClick={crud.startAdd} />}

			{crud.editingId !== null && (
				<EditorCard
					saveLabel={crud.editingId === NEW_ROW ? "Create form" : "Save form"}
					saving={crud.saving}
					title={editorTitle}
					onClose={crud.cancel}
					onSave={() => {
						const sErrors = validate();
						crud.save(
							crud.editingId,
							toBody(draft),
							[
								{ field: "title", label: "Title", value: draft.title },
								{ field: "table", label: "Data table", value: draft.table },
							],
							Object.keys(sErrors).length > 0
						);
					}}
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
							hint="Which view to show after a save."
							label="Return view"
							options={viewOptions}
							value={draft.return_view}
							onChange={(v) => setDraft((p) => ({ ...p, return_view: v }))}
						/>
						<TextInput
							mono
							hint="Overrides the return view with an explicit URL."
							label="Return URL"
							value={draft.return_url}
							onChange={(v) => setDraft((p) => ({ ...p, return_url: v }))}
						/>
						<TextInput
							hint="Default value for a positioned table's sort column."
							label="Default position"
							value={draft.default_position}
							onChange={(v) => setDraft((p) => ({ ...p, default_position: v }))}
						/>
					</FieldGrid>

					<div className="flex flex-wrap gap-5">
						<CheckboxInput
							checked={draft.tagging}
							label="Enable tagging"
							onChange={(v) => setDraft((p) => ({ ...p, tagging: v }))}
						/>
						<CheckboxInput
							checked={draft.open_graph}
							label="Open Graph fields"
							onChange={(v) => setDraft((p) => ({ ...p, open_graph: v }))}
						/>
					</div>

					<ModuleFieldsSection
						columnsTable={draft.table}
						fields={draft.fields}
						hooks={draft.hooks}
						settingsErrors={settingsErrors}
						onFieldsChange={(next) => setDraft((p) => ({ ...p, fields: next }))}
						onHooksChange={(v) => setDraft((p) => ({ ...p, hooks: v }))}
					/>
				</EditorCard>
			)}

			<SubDeleteDialog
				description="Actions that open this form will need to be repointed. Entry data in the module's table is left intact."
				dialog={deleteDialog}
				labelFor={(f) => f.title}
				noun="form"
				onConfirm={(id) => crud.remove(id)}
			/>
		</div>
	);
};
