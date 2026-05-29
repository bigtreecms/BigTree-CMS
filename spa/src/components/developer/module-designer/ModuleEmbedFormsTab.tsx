import { useEffect, useState } from "react";

import {
	modulesApi,
	type ModuleEmbedForm,
	type ModuleEmbedFormBody,
	type ModuleFormField,
} from "@/api/endpoints/modules";

import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import {
	ResourceDesigner,
	toModuleFormFields,
	type ResourceEntry,
} from "@/components/developer/ResourceDesigner";

import { CheckboxInput, JsonInput, TextareaInput, TextInput } from "./inputs";
import { AddSubButton, EditorCard, SubList, SubRow } from "./scaffold";
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

export const ModuleEmbedFormsTab = ({ moduleId, moduleTable }: ModuleEmbedFormsTabProps) => {
	const crud = useSubCrud<ModuleEmbedForm, ModuleEmbedFormBody>({
		moduleId,
		resource: "embed-forms",
		label: "Embed form",
		listFn: (id) => modulesApi.embedForms(id),
		createFn: (id, body) => modulesApi.createEmbedForm(id, body),
		updateFn: (id, sid, body) => modulesApi.updateEmbedForm(id, sid, body),
		deleteFn: (id, sid) => modulesApi.deleteEmbedForm(id, sid),
	});

	const [draft, setDraft] = useState<Draft>(() => emptyDraft(moduleTable));
	const [pendingDelete, setPendingDelete] = useState<ModuleEmbedForm | null>(null);

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
						onDelete={() => setPendingDelete(f)}
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
					onSave={() => crud.save(crud.editingId, toBody(draft))}
					saving={crud.saving}
					saveLabel={crud.editingId === NEW_ROW ? "Create embed form" : "Save embed form"}
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
					</div>

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

					<div>
						<div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
							Fields
						</div>
						<ResourceDesigner
							resources={draft.fields as unknown as ResourceEntry[]}
							onChange={(next) =>
								setDraft((p) => ({ ...p, fields: toModuleFormFields(next) }))
							}
							keyField="column"
							useCase="modules"
						/>
					</div>

					<JsonInput
						label="Hooks"
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
					title={`Delete embed form "${pendingDelete.title}"?`}
					description="Any third-party page embedding this form will stop working. Submitted entries are left intact."
					confirmLabel="Delete embed form"
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
