import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { Plus, Trash } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { FieldGrid } from "@/components/ui/FieldGrid";
import { useDragReorder } from "@/hooks/useDragReorder";
import { useListEditor } from "@/hooks/useListEditor";

import {
	modulesApi,
	type ModuleView,
	type ModuleViewBody,
	type ModuleViewFieldConfig,
	type ModuleViewType,
} from "@/api/endpoints/modules";

import { useDbColumns } from "@/hooks/useDbColumns";
import { queryKeys } from "@/lib/queryKeys";

import { ConfirmDialog } from "@/components/ui/ConfirmDialog";

import { DataTableSelect } from "@/components/developer/DataTableSelect";
import { DataColumnSelect } from "@/components/developer/DataColumnSelect";

import { CheckboxInput, SelectInput, TextInput } from "./inputs";
import { AddSubButton, EditorCard, SubList, SubRow } from "./scaffold";
import { ViewActionsControl } from "./ViewActionsControl";
import { ViewTypeSettingsControl } from "./ViewTypeSettingsControl";
import { NEW_ROW, useSubCrud } from "./useSubCrud";
import { DragHandle } from "@/components/ui/DragHandle";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { IconButton } from "@/components/ui/IconButton";
import { SectionLabel } from "@/components/ui/SectionLabel";

interface ModuleViewsTabProps {
	moduleId: string;
	moduleTable: string;
}

/** One editable column row; carries through `parser`/`numeric` untouched. */
interface ColumnRow {
	key: string;
	title: string;
	parser?: string;
	numeric?: string | boolean;
	/** Column width in px ("" = auto). The renderer uses it as a proportional weight. */
	width?: string;
}

type Draft = {
	title: string;
	description: string;
	table: string;
	type: ModuleViewType;
	related_form: string;
	preview_url: string;
	exclude_from_search: boolean;
	sort_column: string;
	sort_direction: string;
	per_page: string;
	filter: string;
	columns: ColumnRow[];
	actions: Record<string, unknown>;
	/** Full settings blob, preserving per-view-type keys the generic fields don't cover. */
	settings: Record<string, unknown>;
};

const VIEW_TYPES: ModuleViewType[] = [
	"searchable",
	"nested",
	"draggable",
	"grouped",
	"images",
	"images-grouped",
];

const columnsToRows = (fields: ModuleView["fields"]): ColumnRow[] => {
	if (!fields || Array.isArray(fields)) {
		return [];
	}

	return Object.entries(fields).map(([key, cfg]) => ({
		key,
		title: cfg.title ?? "",
		parser: cfg.parser,
		numeric: cfg.numeric,
		width: cfg.width != null && cfg.width !== "" ? String(cfg.width) : "",
	}));
};

const rowsToColumns = (rows: ColumnRow[]): Record<string, ModuleViewFieldConfig> => {
	const out: Record<string, ModuleViewFieldConfig> = {};

	for (const row of rows) {
		if (!row.key) {
			continue;
		}

		out[row.key] = {
			title: row.title,
			parser: row.parser,
			numeric: row.numeric,
			width: row.width ?? "",
		};
	}

	return out;
};

const draftFromView = (v: ModuleView): Draft => ({
	title: v.title ?? "",
	description: v.description ?? "",
	table: v.table ?? "",
	type: v.type ?? "searchable",
	related_form: v.related_form ?? "",
	preview_url: v.preview_url ?? "",
	exclude_from_search: v.exclude_from_search === true || v.exclude_from_search === "on",
	sort_column: v.settings?.sort_column ?? "",
	sort_direction: v.settings?.sort_direction ?? "DESC",
	per_page: v.settings?.per_page != null ? String(v.settings.per_page) : "",
	filter: v.settings?.filter ?? "",
	columns: columnsToRows(v.fields),
	actions: (v.actions as Record<string, unknown>) ?? {},
	settings: (v.settings as Record<string, unknown>) ?? {},
});

const emptyDraft = (table: string): Draft => ({
	title: "",
	description: "",
	table,
	type: "searchable",
	related_form: "",
	preview_url: "",
	exclude_from_search: false,
	sort_column: "",
	sort_direction: "DESC",
	per_page: "",
	filter: "",
	columns: [],
	// Edit/Delete are checked by default for a new view, matching the legacy
	// module designer; the toggles add/remove the rest once a table is chosen.
	actions: { edit: "on", delete: "on" },
	settings: {},
});

const toBody = (d: Draft): ModuleViewBody => ({
	title: d.title,
	description: d.description || undefined,
	table: d.table,
	type: d.type,
	related_form: d.related_form || null,
	preview_url: d.preview_url || undefined,
	exclude_from_search: d.exclude_from_search,
	settings: {
		// Preserve per-view-type keys (group_field, nesting_column, image, …)
		// the generic fields below don't cover, then overlay the generics.
		...d.settings,
		sort_column: d.sort_column || undefined,
		sort_direction: d.sort_direction || undefined,
		per_page: d.per_page || undefined,
		filter: d.filter || undefined,
	},
	fields: rowsToColumns(d.columns),
	actions: d.actions as Record<string, string>,
});

export const ModuleViewsTab = ({ moduleId, moduleTable }: ModuleViewsTabProps) => {
	const crud = useSubCrud<ModuleView, ModuleViewBody>({
		moduleId,
		resource: "views",
		label: "View",
		listFn: (id) => modulesApi.views(id),
		createFn: (id, body) => modulesApi.createView(id, body),
		updateFn: (id, sid, body) => modulesApi.updateView(id, sid, body),
		deleteFn: (id, sid) => modulesApi.deleteView(id, sid),
	});

	const [draft, setDraft] = useState<Draft>(() => emptyDraft(moduleTable));
	const deleteDialog = useConfirmDialog<ModuleView>();

	const formsQ = useQuery({
		queryKey: queryKeys.modules.moduleForms(moduleId),
		queryFn: () => modulesApi.forms(moduleId),
	});

	// Columns of the chosen table drive the column picker and which row-action
	// toggles are offered. Only fetched once a table is selected.
	const columnsQ = useDbColumns(draft.table);

	useEffect(() => {
		if (crud.editingId === NEW_ROW) {
			setDraft(emptyDraft(moduleTable));
		} else if (crud.editingId) {
			const found = crud.items.find((v) => v.id === crud.editingId);

			if (found) {
				setDraft(draftFromView(found));
			}
		}
	}, [crud.editingId, crud.items, moduleTable]);

	const formOptions = [
		{ value: "", label: "— No related form —" },
		...(formsQ.data ?? []).map((f) => ({ value: f.id, label: f.title })),
	];

	const columnEditor = useListEditor<ColumnRow>(draft.columns, (columns) =>
		setDraft((p) => ({ ...p, columns }))
	);
	const setColumn = columnEditor.update;
	const removeColumn = columnEditor.remove;

	const addColumn = () => columnEditor.add({ key: "", title: "" });

	// Columns have no stable id, so drag-reorder runs on array index: `reorder`
	// receives the new order of original indices and rebuilds the list.
	const reorderColumns = (orderedIndices: number[]) =>
		setDraft((p) => ({ ...p, columns: orderedIndices.map((i) => p.columns[i]!) }));

	const columnDrag = useDragReorder<{ id: number }, number>(
		draft.columns.map((_, i) => ({ id: i })),
		() => {},
		reorderColumns
	);

	const editorTitle = crud.editingId === NEW_ROW ? "New view" : "Edit view";

	return (
		<div className="space-y-3">
			<SubList
				isLoading={crud.isLoading}
				loadingLabel="Loading views…"
				emptyLabel="No views yet. A view is the list/table editors browse this module's entries in."
				isEmpty={crud.items.length === 0}
			>
				{crud.items.map((v) => (
					<SubRow
						key={v.id}
						title={v.title}
						subtitle={v.table}
						badge={v.type}
						onEdit={() => crud.startEdit(v.id)}
						onDelete={() => deleteDialog.open(v)}
					/>
				))}
			</SubList>

			{crud.editingId === null && <AddSubButton label="Add view" onClick={crud.startAdd} />}

			{crud.editingId !== null && (
				<EditorCard
					title={editorTitle}
					onClose={crud.cancel}
					onSave={() =>
						crud.save(crud.editingId, toBody(draft), [
							{ field: "title", label: "Title", value: draft.title },
							{ field: "table", label: "Data table", value: draft.table },
						])
					}
					saving={crud.saving}
					saveLabel={crud.editingId === NEW_ROW ? "Create view" : "Save view"}
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
						<SelectInput
							label="Type"
							value={draft.type}
							onChange={(v) => setDraft((p) => ({ ...p, type: v as ModuleViewType }))}
							options={VIEW_TYPES.map((t) => ({ value: t, label: t }))}
						/>
						<SelectInput
							label="Related form"
							value={draft.related_form}
							onChange={(v) => setDraft((p) => ({ ...p, related_form: v }))}
							options={formOptions}
							hint="The form opened when editing a row."
						/>
					</FieldGrid>

					<TextInput
						label="Description"
						value={draft.description}
						onChange={(v) => setDraft((p) => ({ ...p, description: v }))}
					/>

					<div className="grid grid-cols-1 gap-4 md:grid-cols-3">
						<TextInput
							label="Sort column"
							value={draft.sort_column}
							onChange={(v) => setDraft((p) => ({ ...p, sort_column: v }))}
							mono
						/>
						<SelectInput
							label="Sort direction"
							value={draft.sort_direction}
							onChange={(v) => setDraft((p) => ({ ...p, sort_direction: v }))}
							options={[
								{ value: "DESC", label: "Descending" },
								{ value: "ASC", label: "Ascending" },
							]}
						/>
						<TextInput
							label="Per page"
							value={draft.per_page}
							onChange={(v) => setDraft((p) => ({ ...p, per_page: v }))}
						/>
					</div>

					<TextInput
						label="Filter (WHERE clause)"
						value={draft.filter}
						onChange={(v) => setDraft((p) => ({ ...p, filter: v }))}
						hint="Optional SQL filter applied to every query in this view."
						mono
					/>

					<div>
						<SectionLabel className="mb-2">Columns</SectionLabel>
						{!draft.table ? (
							<InlineEmpty align="center">
								Select a data table to add columns.
							</InlineEmpty>
						) : draft.columns.length === 0 ? (
							<InlineEmpty align="center">No columns configured.</InlineEmpty>
						) : (
							<ul className="space-y-1.5">
								{draft.columns.map((col, index) => {
									const isDragging = columnDrag.dragId === index;
									const isDropTarget =
										columnDrag.overId === index && columnDrag.dragId !== index;

									return (
										<li
											key={index}
											className={`flex items-center gap-2 rounded-md border border-border bg-surface px-2 py-1.5 transition-colors ${
												isDragging ? "bg-accent-soft shadow-md" : ""
											} ${
												isDropTarget
													? "shadow-[inset_0_2px_0_0_var(--color-accent)]"
													: ""
											}`}
											onDragOver={(e) => columnDrag.onDragOver(e, index)}
											onDrop={columnDrag.onDrop}
										>
											<DragHandle
												draggable
												onDragStart={(e) =>
													columnDrag.onDragStart(e, index)
												}
												onDragEnd={columnDrag.onDragEnd}
											/>
											<DataColumnSelect
												table={draft.table}
												value={col.key}
												onChange={(v) => setColumn(index, { key: v })}
												ariaLabel="Column"
												className="w-48 shrink-0"
											/>
											<input
												type="text"
												value={col.title}
												aria-label="Column heading"
												onChange={(e) =>
													setColumn(index, { title: e.target.value })
												}
												placeholder="Heading"
												className="flex-1 rounded-md border border-border bg-surface px-2 py-1 text-[12.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
											/>
											<input
												type="number"
												min={0}
												value={col.width ?? ""}
												onChange={(e) =>
													setColumn(index, { width: e.target.value })
												}
												placeholder="auto"
												title="Column width in px (relative weight; blank = auto)"
												aria-label="Column width"
												className="w-20 shrink-0 rounded-md border border-border bg-surface px-2 py-1 text-[12.5px] tabular-nums focus:outline-none focus:ring-1 focus:ring-accent-ring"
											/>
											<IconButton
												tone="danger"
												onClick={() => removeColumn(index)}
												label="Remove column"
											>
												<Trash size={13} />
											</IconButton>
										</li>
									);
								})}
							</ul>
						)}
						<Button
							variant="secondary"
							icon={<Plus size={13} />}
							className="mt-2"
							disabled={!draft.table}
							onClick={addColumn}
							title={draft.table ? undefined : "Select a data table first"}
						>
							Add column
						</Button>
					</div>

					{draft.type !== "searchable" && draft.type !== "draggable" && (
						<div>
							<SectionLabel className="mb-2">{draft.type} settings</SectionLabel>
							<ViewTypeSettingsControl
								type={draft.type}
								table={draft.table}
								settings={draft.settings}
								onChange={(s) => setDraft((p) => ({ ...p, settings: s }))}
							/>
						</div>
					)}

					<ViewActionsControl
						value={draft.actions}
						onChange={(v) => setDraft((p) => ({ ...p, actions: v }))}
						columns={columnsQ.data ?? []}
						loading={columnsQ.isLoading}
						tableSelected={draft.table !== ""}
					/>

					<CheckboxInput
						label="Exclude this view's rows from global search"
						checked={draft.exclude_from_search}
						onChange={(v) => setDraft((p) => ({ ...p, exclude_from_search: v }))}
					/>
				</EditorCard>
			)}

			{deleteDialog.item && (
				<ConfirmDialog
					open={deleteDialog.isOpen}
					onOpenChange={(v) => {
						if (!v) deleteDialog.close();
					}}
					title={`Delete view "${deleteDialog.item.title}"?`}
					description="Actions and reports that reference this view will need to be repointed. Entry data is left intact."
					confirmLabel="Delete view"
					variant="danger"
					onConfirm={() => {
						crud.remove(deleteDialog.item!.id);
						deleteDialog.close();
					}}
				/>
			)}
		</div>
	);
};
