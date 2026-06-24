import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";

import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";

import {
	modulesApi,
	type ModuleForm,
	type ModuleFormBody,
	type ModuleFormField,
} from "@/api/endpoints/modules";

import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { SectionLabel } from "@/components/ui/SectionLabel";
import {
	ResourceDesigner,
	toModuleFormFields,
	type ResourceEntry,
} from "@/components/developer/ResourceDesigner";

import { DataTableSelect } from "@/components/developer/DataTableSelect";
import { useResourceSettingsValidation } from "@/components/developer/field-settings/useResourceSettingsValidation";

import { CheckboxInput, SelectInput, TextInput } from "./inputs";
import { FormHooksEditor } from "./FormHooksEditor";
import { AddSubButton, EditorCard, SubList, SubRow } from "./scaffold";
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
	const crud = useSubCrud<ModuleForm, ModuleFormBody>({
		moduleId,
		resource: "forms",
		label: "Form",
		listFn: (id) => modulesApi.forms(id),
		createFn: (id, body) => modulesApi.createForm(id, body),
		updateFn: (id, sid, body) => modulesApi.updateForm(id, sid, body),
		deleteFn: (id, sid) => modulesApi.deleteForm(id, sid),
	});

	const [draft, setDraft] = useState<Draft>(() => emptyDraft(moduleTable));
	const [pendingDelete, setPendingDelete] = useState<ModuleForm | null>(null);
	const [settingsErrors, setSettingsErrors] = useState<Record<number, Record<string, string>>>(
		{}
	);

	// Nested settings errors are keyed by resource index; flatten so the
	// scroll-to-first-error hook can see whether any exist this submit.
	const flatSettingsErrors = useMemo(
		() => Object.assign({}, ...Object.values(settingsErrors)) as Record<string, string>,
		[settingsErrors]
	);

	useScrollToFirstError(flatSettingsErrors);

	const settingsValidation = useResourceSettingsValidation(
		draft.fields as unknown as ResourceEntry[],
		"modules"
	);

	// Drop stale per-field settings errors whenever a different row opens/closes.
	useEffect(() => {
		setSettingsErrors({});
	}, [crud.editingId]);

	const viewsQ = useQuery({
		queryKey: ["modules", moduleId, "views"],
		queryFn: () => modulesApi.views(moduleId),
	});

	useEffect(() => {
		if (crud.editingId === NEW_ROW) {
			setDraft(emptyDraft(moduleTable));
		} else if (crud.editingId) {
			const found = crud.items.find((f) => f.id === crud.editingId);

			if (found) {
				setDraft(draftFromForm(found));
			}
		}
	}, [crud.editingId, crud.items, moduleTable]);

	const viewOptions = [
		{ value: "", label: "— Return to default view —" },
		...(viewsQ.data ?? []).map((v) => ({ value: v.id, label: v.title })),
	];

	const editorTitle = crud.editingId === NEW_ROW ? "New form" : "Edit form";

	return (
		<div className="space-y-3">
			<SubList
				isLoading={crud.isLoading}
				loadingLabel="Loading forms…"
				emptyLabel="No forms yet. A form defines the add/edit screen for this module's entries."
				isEmpty={crud.items.length === 0}
			>
				{crud.items.map((f) => (
					<SubRow
						key={f.id}
						title={f.title}
						subtitle={f.table}
						badge={`${Array.isArray(f.fields) ? f.fields.length : 0} fields`}
						onEdit={() => crud.startEdit(f.id)}
						onDelete={() => setPendingDelete(f)}
					/>
				))}
			</SubList>

			{crud.editingId === null && <AddSubButton label="Add form" onClick={crud.startAdd} />}

			{crud.editingId !== null && (
				<EditorCard
					title={editorTitle}
					onClose={crud.cancel}
					onSave={() => {
						const sErrors = settingsValidation.validate();
						setSettingsErrors(sErrors);
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
					saving={crud.saving}
					saveLabel={crud.editingId === NEW_ROW ? "Create form" : "Save form"}
				>
					<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
						<TextInput
							label="Title"
							value={draft.title}
							onChange={(v) => setDraft((p) => ({ ...p, title: v }))}
							error={crud.fieldErrors.title}
							required
						/>
						<DataTableSelect
							label="Data table"
							value={draft.table}
							onChange={(v) => setDraft((p) => ({ ...p, table: v }))}
							error={crud.fieldErrors.table}
							required
						/>
						<SelectInput
							label="Return view"
							value={draft.return_view}
							onChange={(v) => setDraft((p) => ({ ...p, return_view: v }))}
							options={viewOptions}
							hint="Which view to show after a save."
						/>
						<TextInput
							label="Return URL"
							value={draft.return_url}
							onChange={(v) => setDraft((p) => ({ ...p, return_url: v }))}
							hint="Overrides the return view with an explicit URL."
							mono
						/>
						<TextInput
							label="Default position"
							value={draft.default_position}
							onChange={(v) => setDraft((p) => ({ ...p, default_position: v }))}
							hint="Default value for a positioned table's sort column."
						/>
					</div>

					<div className="flex flex-wrap gap-5">
						<CheckboxInput
							label="Enable tagging"
							checked={draft.tagging}
							onChange={(v) => setDraft((p) => ({ ...p, tagging: v }))}
						/>
						<CheckboxInput
							label="Open Graph fields"
							checked={draft.open_graph}
							onChange={(v) => setDraft((p) => ({ ...p, open_graph: v }))}
						/>
					</div>

					<div>
						<SectionLabel className="mb-2">Fields</SectionLabel>
						<ResourceDesigner
							resources={draft.fields as unknown as ResourceEntry[]}
							onChange={(next) =>
								setDraft((p) => ({ ...p, fields: toModuleFormFields(next) }))
							}
							keyField="column"
							useCase="modules"
							columnsTable={draft.table}
							settingsErrors={settingsErrors}
						/>
					</div>

					<FormHooksEditor
						value={draft.hooks}
						onChange={(v) => setDraft((p) => ({ ...p, hooks: v }))}
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
					title={`Delete form "${pendingDelete.title}"?`}
					description="Actions that open this form will need to be repointed. Entry data in the module's table is left intact."
					confirmLabel="Delete form"
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
