import { useConfirmDialog } from "@/hooks/useConfirmDialog";

import {
	modulesApi,
	type ModuleEmbedForm,
	type ModuleEmbedFormBody,
	type ModuleFormField,
} from "@/api/endpoints/modules";

import { FieldGrid } from "@/components/ui/FieldGrid";

import { DataTableSelect } from "@/components/developer/DataTableSelect";

import { CheckboxInput, TextareaInput, TextInput } from "./inputs";
import { ModuleFieldsSection } from "./ModuleFieldsSection";
import { useModuleFieldsValidation } from "./useModuleFieldsValidation";
import { AddSubButton, EditorCard, SubDeleteDialog, SubList, SubRow } from "./scaffold";
import { NEW_ROW, useSubCrud } from "./useSubCrud";

interface ModuleEmbedFormsTabProps {
	moduleId: string;
	moduleTable: string;
}

type Draft = {
	title: string;
	table: string;
	default_position: string;
	default_pending: boolean;
	css: string;
	redirect_url: string;
	thank_you_message: string;
	fields: ModuleFormField[];
	hooks: Record<string, unknown> | unknown[];
};

const draftFromForm = (f: ModuleEmbedForm): Draft => ({
	title: f.title ?? "",
	table: f.table ?? "",
	default_position: f.default_position ?? "",
	default_pending: f.default_pending === true || f.default_pending === "on",
	css: f.css ?? "",
	redirect_url: f.redirect_url ?? "",
	thank_you_message: f.thank_you_message ?? "",
	fields: Array.isArray(f.fields) ? f.fields : [],
	hooks: (f.hooks as Record<string, unknown>) ?? {},
});

const emptyDraft = (table: string): Draft => ({
	title: "",
	table,
	default_position: "",
	default_pending: false,
	css: "",
	redirect_url: "",
	thank_you_message: "",
	fields: [],
	hooks: {},
});

const toBody = (d: Draft): ModuleEmbedFormBody => ({
	title: d.title,
	table: d.table,
	fields: d.fields,
	default_position: d.default_position || undefined,
	default_pending: d.default_pending,
	css: d.css || undefined,
	redirect_url: d.redirect_url || undefined,
	thank_you_message: d.thank_you_message || undefined,
	hooks: d.hooks,
});

export const ModuleEmbedFormsTab = ({ moduleId, moduleTable }: ModuleEmbedFormsTabProps) => {
	const crud = useSubCrud<ModuleEmbedForm, ModuleEmbedFormBody, Draft>({
		moduleId,
		resource: "embed-forms",
		label: "Embed form",
		moduleTable,
		emptyDraft,
		draftFromItem: draftFromForm,
		listFn: (id) => modulesApi.embedForms(id),
		createFn: (id, body) => modulesApi.createEmbedForm(id, body),
		updateFn: (id, sid, body) => modulesApi.updateEmbedForm(id, sid, body),
		deleteFn: (id, sid) => modulesApi.deleteEmbedForm(id, sid),
	});

	const { draft, setDraft } = crud;
	const deleteDialog = useConfirmDialog<ModuleEmbedForm>();
	const { settingsErrors, validate } = useModuleFieldsValidation(draft.fields, crud.editingId);

	const editorTitle = crud.editingId === NEW_ROW ? "New embed form" : "Edit embed form";

	return (
		<div className="space-y-3">
			<SubList
				isLoading={crud.isLoading}
				loadingLabel="Loading embed forms…"
				emptyLabel="No embed forms yet. Embed forms render standalone on third-party pages."
				isEmpty={crud.items.length === 0}
			>
				{crud.items.map((f) => (
					<SubRow
						key={f.id}
						title={f.title}
						subtitle={f.table}
						badge={`${Array.isArray(f.fields) ? f.fields.length : 0} fields`}
						onEdit={() => crud.startEdit(f.id)}
						onDelete={() => deleteDialog.open(f)}
					/>
				))}
			</SubList>

			{crud.editingId === null && (
				<AddSubButton label="Add embed form" onClick={crud.startAdd} />
			)}

			{crud.editingId !== null && (
				<EditorCard
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
					saving={crud.saving}
					saveLabel={crud.editingId === NEW_ROW ? "Create embed form" : "Save embed form"}
				>
					<FieldGrid>
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
						<TextInput
							label="Redirect URL"
							value={draft.redirect_url}
							onChange={(v) => setDraft((p) => ({ ...p, redirect_url: v }))}
							hint="Where to send the visitor after submission."
							mono
						/>
						<TextInput
							label="Default position"
							value={draft.default_position}
							onChange={(v) => setDraft((p) => ({ ...p, default_position: v }))}
						/>
					</FieldGrid>

					<CheckboxInput
						label="Submissions start as pending changes"
						checked={draft.default_pending}
						onChange={(v) => setDraft((p) => ({ ...p, default_pending: v }))}
					/>

					<TextareaInput
						label="Thank-you message"
						value={draft.thank_you_message}
						onChange={(v) => setDraft((p) => ({ ...p, thank_you_message: v }))}
						rows={3}
					/>

					<TextareaInput
						label="CSS hooks"
						value={draft.css}
						onChange={(v) => setDraft((p) => ({ ...p, css: v }))}
						rows={3}
						mono
						hint="Stylesheet URL or inline CSS applied to the embedded form."
					/>

					<ModuleFieldsSection
						fields={draft.fields}
						onFieldsChange={(next) => setDraft((p) => ({ ...p, fields: next }))}
						settingsErrors={settingsErrors}
						columnsTable={draft.table}
						hooks={draft.hooks}
						onHooksChange={(v) => setDraft((p) => ({ ...p, hooks: v }))}
					/>
				</EditorCard>
			)}

			<SubDeleteDialog
				dialog={deleteDialog}
				noun="embed form"
				labelFor={(f) => f.title}
				description="Any third-party page embedding this form will stop working. Submitted entries are left intact."
				onConfirm={(id) => crud.remove(id)}
			/>
		</div>
	);
};
