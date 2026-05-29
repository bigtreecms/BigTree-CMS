import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ArrowDown, ArrowUp, Plus, Trash } from "lucide-react";

import {
	modulesApi,
	type ModuleView,
	type ModuleViewBody,
	type ModuleViewFieldConfig,
	type ModuleViewType,
} from "@/api/endpoints/modules";

import { ConfirmDialog } from "@/components/ui/ConfirmDialog";

import { CheckboxInput, JsonInput, SelectInput, TextInput } from "./inputs";
import { AddSubButton, EditorCard, SubList, SubRow } from "./scaffold";
import { NEW_ROW, useSubCrud } from "./useSubCrud";

interface ModuleViewsTabProps {
	moduleId: string;
	moduleTable: string;
}

/** One editable column row; carries through `parser`/`numeric` untouched. */
interface ColumnRow {
	key: string;
	title: string;
	width: string;
	parser?: string;
	numeric?: string | boolean;
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
		width: cfg.width != null ? String(cfg.width) : "",
		parser: cfg.parser,
		numeric: cfg.numeric,
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
			width: row.width,
			parser: row.parser,
			numeric: row.numeric,
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
	actions: {},
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
	const [pendingDelete, setPendingDelete] = useState<ModuleView | null>(null);

	const formsQ = useQuery({
		queryKey: ["modules", moduleId, "forms"],
		queryFn: () => modulesApi.forms(moduleId),
	});

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

	const setColumn = (index: number, patch: Partial<ColumnRow>) =>
		setDraft((p) => ({
			...p,
			columns: p.columns.map((c, i) => (i === index ? { ...c, ...patch } : c)),
		}));

	const addColumn = () =>
		setDraft((p) => ({ ...p, columns: [...p.columns, { key: "", title: "", width: "" }] }));

	const removeColumn = (index: number) =>
		setDraft((p) => ({ ...p, columns: p.columns.filter((_, i) => i !== index) }));

	const moveColumn = (index: number, direction: "up" | "down") => {
		const swap = direction === "up" ? index - 1 : index + 1;

		setDraft((p) => {
			if (swap < 0 || swap >= p.columns.length) {
				return p;
			}

			const next = [...p.columns];
			const removed = next.splice(index, 1)[0];

			if (removed) {
				next.splice(swap, 0, removed);
			}

			return { ...p, columns: next };
		});
	};

	const toBody = (d: Draft): ModuleViewBody => ({
		title: d.title,
		description: d.description || undefined,
		table: d.table,
		type: d.type,
		related_form: d.related_form || null,
		preview_url: d.preview_url || undefined,
		exclude_from_search: d.exclude_from_search,
		settings: {
			sort_column: d.sort_column || undefined,
			sort_direction: d.sort_direction || undefined,
			per_page: d.per_page || undefined,
			filter: d.filter || undefined,
		},
		fields: rowsToColumns(d.columns),
		actions: d.actions as Record<string, string>,
	});

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
						onDelete={() => setPendingDelete(v)}
					/>
				))}
			</SubList>

			{crud.editingId === null && <AddSubButton label="Add view" onClick={crud.startAdd} />}

			{crud.editingId !== null && (
				<EditorCard
					title={editorTitle}
					onClose={crud.cancel}
					onSave={() => crud.save(crud.editingId, toBody(draft))}
					saving={crud.saving}
					saveLabel={crud.editingId === NEW_ROW ? "Create view" : "Save view"}
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
					</div>

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
						<div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
							Columns
						</div>
						{draft.columns.length === 0 ? (
							<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-4 text-center text-[12.5px] text-text-3">
								No columns configured.
							</div>
						) : (
							<ul className="space-y-1.5">
								{draft.columns.map((col, index) => (
									<li
										key={index}
										className="flex items-center gap-2 rounded-md border border-border bg-surface px-2 py-1.5"
									>
										<div className="flex flex-col">
											<button
												type="button"
												className="rounded p-0.5 text-text-3 hover:bg-hover hover:text-text disabled:opacity-30"
												onClick={() => moveColumn(index, "up")}
												disabled={index === 0}
												aria-label="Move up"
											>
												<ArrowUp size={11} />
											</button>
											<button
												type="button"
												className="rounded p-0.5 text-text-3 hover:bg-hover hover:text-text disabled:opacity-30"
												onClick={() => moveColumn(index, "down")}
												disabled={index === draft.columns.length - 1}
												aria-label="Move down"
											>
												<ArrowDown size={11} />
											</button>
										</div>
										<input
											type="text"
											value={col.key}
											onChange={(e) =>
												setColumn(index, { key: e.target.value })
											}
											placeholder="column"
											className="w-40 rounded-md border border-border bg-surface px-2 py-1 font-mono text-[12px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
										/>
										<input
											type="text"
											value={col.title}
											onChange={(e) =>
												setColumn(index, { title: e.target.value })
											}
											placeholder="Heading"
											className="flex-1 rounded-md border border-border bg-surface px-2 py-1 text-[12.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
										/>
										<input
											type="text"
											value={col.width}
											onChange={(e) =>
												setColumn(index, { width: e.target.value })
											}
											placeholder="width"
											className="w-20 rounded-md border border-border bg-surface px-2 py-1 text-[12.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
										/>
										<button
											type="button"
											className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
											onClick={() => removeColumn(index)}
											aria-label="Remove column"
										>
											<Trash size={13} />
										</button>
									</li>
								))}
							</ul>
						)}
						<button
							type="button"
							className="mt-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
							onClick={addColumn}
						>
							<Plus size={13} />
							Add column
						</button>
					</div>

					<JsonInput
						label="Row actions"
						value={draft.actions}
						onChange={(v) => setDraft((p) => ({ ...p, actions: v }))}
						hint='Keyed by action: "edit"/"delete" set to "on" for built-ins, or a config object for custom row actions.'
					/>

					<CheckboxInput
						label="Exclude this view's rows from global search"
						checked={draft.exclude_from_search}
						onChange={(v) => setDraft((p) => ({ ...p, exclude_from_search: v }))}
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
					title={`Delete view "${pendingDelete.title}"?`}
					description="Actions and reports that reference this view will need to be repointed. Entry data is left intact."
					confirmLabel="Delete view"
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
