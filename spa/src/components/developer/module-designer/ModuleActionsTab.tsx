import { useEffect } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";

import { modulesApi, type ModuleAction, type ModuleActionBody } from "@/api/endpoints/modules";

import { queryKeys } from "@/lib/queryKeys";
import { useToastMutation } from "@/hooks/useToastMutation";
import { FieldGrid } from "@/components/ui/FieldGrid";

import { useDragReorder } from "@/hooks/useDragReorder";

import { ActionModuleEditor } from "@/components/developer/action-module/ActionModuleEditor";
import { ACTION_STARTER } from "@/components/developer/action-module/starterTemplate";
import { IconSelect } from "@/components/developer/IconSelect";

import { CheckboxInput, SelectInput, TextInput } from "./inputs";
import { AddSubButton, EditorCard, SubDeleteDialog, SubList, SubRow } from "./scaffold";
import { NEW_ROW, useSubCrud } from "./useSubCrud";

const TARGET_MODULE = "module";

interface ModuleActionsTabProps {
	moduleId: string;
}

type Draft = {
	name: string;
	route: string;
	in_nav: boolean;
	icon: string;
	level: number;
	target: string;
	/** TSX source for a custom (module) action; "" when target isn't module. */
	source: string;
	/** Method on the module class a module action submits to. */
	handler: string;
};

const TARGET_NONE = "";

const draftFromAction = (a: ModuleAction): Draft => {
	let target = TARGET_NONE;

	if (a.render === "module") {
		target = TARGET_MODULE;
	} else if (a.form) {
		target = `form:${a.form}`;
	} else if (a.view) {
		target = `view:${a.view}`;
	} else if (a.report) {
		target = `report:${a.report}`;
	}

	return {
		name: a.name ?? "",
		route: a.route ?? "",
		in_nav: a.in_nav === true || a.in_nav === "on",
		icon: a.class ?? "",
		level: a.level ?? 0,
		target,
		// Source for an existing module action is loaded lazily from its schema.
		source: "",
		handler: a.handler ?? "",
	};
};

const emptyDraft = (): Draft => ({
	name: "",
	route: "",
	in_nav: true,
	icon: "",
	level: 0,
	target: TARGET_NONE,
	source: "",
	handler: "",
});

const toBody = (d: Draft): ModuleActionBody => {
	if (d.target === TARGET_MODULE) {
		return {
			name: d.name,
			route: d.route || undefined,
			in_nav: d.in_nav,
			class: d.icon || undefined,
			level: d.level,
			form: null,
			view: null,
			report: null,
			render: "module",
			handler: d.handler || undefined,
			module_source: d.source,
			contract_version: 1,
		};
	}

	const [kind, id] = d.target ? d.target.split(":") : [];

	return {
		name: d.name,
		route: d.route || undefined,
		in_nav: d.in_nav,
		class: d.icon || undefined,
		level: d.level,
		form: kind === "form" ? id : null,
		view: kind === "view" ? id : null,
		report: kind === "report" ? id : null,
		// Clear any module markers when this action isn't (or no longer is) a module.
		render: "",
	};
};

export const ModuleActionsTab = ({ moduleId }: ModuleActionsTabProps) => {
	const queryClient = useQueryClient();
	const crud = useSubCrud<ModuleAction, ModuleActionBody, Draft>({
		moduleId,
		resource: "actions",
		label: "Action",
		emptyDraft,
		draftFromItem: draftFromAction,
		listFn: (id) => modulesApi.actions(id),
		createFn: (id, body) => modulesApi.createAction(id, body),
		updateFn: (id, sid, body) => modulesApi.updateAction(id, sid, body),
		deleteFn: (id, sid) => modulesApi.deleteAction(id, sid),
	});

	const { draft, setDraft } = crud;
	const deleteDialog = useConfirmDialog<ModuleAction>();

	const formsQ = useQuery({
		queryKey: queryKeys.modules.moduleForms(moduleId),
		queryFn: () => modulesApi.forms(moduleId),
	});
	const viewsQ = useQuery({
		queryKey: queryKeys.modules.moduleViews(moduleId),
		queryFn: () => modulesApi.views(moduleId),
	});
	const reportsQ = useQuery({
		queryKey: queryKeys.modules.moduleReports(moduleId),
		queryFn: () => modulesApi.reports(moduleId),
	});

	const editingExisting = crud.editingId && crud.editingId !== NEW_ROW ? crud.editingId : null;

	// A module action's source lives on disk, not on the list record — load it from
	// the schema endpoint when editing one, and seed the draft once it arrives.
	const schemaQ = useQuery({
		queryKey: queryKeys.modules.moduleActionSchema(moduleId, editingExisting),
		queryFn: () => modulesApi.actionSchema(moduleId, editingExisting as string),
		enabled: !!editingExisting && draft.target === TARGET_MODULE,
	});

	useEffect(() => {
		const schema = schemaQ.data;

		if (schema?.render === "module" && schema.module_source) {
			setDraft((p) =>
				p.source
					? p
					: {
							...p,
							source: schema.module_source ?? "",
							handler: p.handler || schema.handler,
						}
			);
		}
	}, [schemaQ.data, setDraft]);

	const reorderMutation = useToastMutation({
		mutationFn: (ids: string[]) => modulesApi.reorderActions(moduleId, ids),
		errorMessage: "Reorder failed",
		onSuccess: () =>
			queryClient.invalidateQueries({ queryKey: queryKeys.modules.moduleActions(moduleId) }),
		onError: () =>
			queryClient.invalidateQueries({ queryKey: queryKeys.modules.moduleActions(moduleId) }),
	});

	const drag = useDragReorder<ModuleAction, string>(
		crud.items,
		(next) => queryClient.setQueryData(queryKeys.modules.moduleActions(moduleId), next),
		(ids) => reorderMutation.mutate(ids)
	);

	const targetOptions = [
		{ value: TARGET_NONE, label: "— No target —" },
		{ value: TARGET_MODULE, label: "Custom action (module)" },
		...(formsQ.data ?? []).map((f) => ({ value: `form:${f.id}`, label: `Form: ${f.title}` })),
		...(viewsQ.data ?? []).map((v) => ({ value: `view:${v.id}`, label: `View: ${v.title}` })),
		...(reportsQ.data ?? []).map((r) => ({
			value: `report:${r.id}`,
			label: `Report: ${r.title}`,
		})),
	];

	// Picking the module target seeds the starter source so the editor isn't blank.
	const onTargetChange = (value: string) =>
		setDraft((p) => ({
			...p,
			target: value,
			source: value === TARGET_MODULE && !p.source ? ACTION_STARTER : p.source,
		}));

	const handleSave = () =>
		crud.save(crud.editingId, toBody(draft), [
			{ field: "name", label: "Name", value: draft.name },
		]);

	const editorTitle = crud.editingId === NEW_ROW ? "New action" : "Edit action";

	return (
		<div className="space-y-3">
			<SubList
				isLoading={crud.isLoading}
				loadingLabel="Loading actions…"
				emptyLabel="No actions yet. Actions are the entry points shown in the module's nav."
				isEmpty={crud.items.length === 0}
			>
				{crud.items.map((a) => (
					<SubRow
						key={a.id}
						title={a.name}
						subtitle={a.route ?? undefined}
						badge={a.in_nav === true || a.in_nav === "on" ? "in nav" : undefined}
						onEdit={() => crud.startEdit(a.id)}
						onDelete={() => deleteDialog.open(a)}
						reorderable
						isDragging={drag.dragId === a.id}
						isDropTarget={drag.overId === a.id && drag.dragId !== a.id}
						onDragStart={(e) => drag.onDragStart(e, a.id)}
						onDragOver={(e) => drag.onDragOver(e, a.id)}
						onDrop={drag.onDrop}
						onDragEnd={drag.onDragEnd}
					/>
				))}
			</SubList>

			{crud.editingId === null && <AddSubButton label="Add action" onClick={crud.startAdd} />}

			{crud.editingId !== null && (
				<EditorCard
					title={editorTitle}
					onClose={crud.cancel}
					onSave={handleSave}
					saving={crud.saving}
					saveLabel={crud.editingId === NEW_ROW ? "Create action" : "Save action"}
				>
					<FieldGrid>
						<TextInput
							label="Name"
							value={draft.name}
							onChange={(v) => setDraft((p) => ({ ...p, name: v }))}
							error={crud.fieldErrors.name}
							required
						/>
						<TextInput
							label="Route"
							value={draft.route}
							onChange={(v) => setDraft((p) => ({ ...p, route: v }))}
							hint="URL segment under the module. Auto-generated when blank."
							mono
						/>
						<SelectInput
							label="Target"
							value={draft.target}
							onChange={onTargetChange}
							options={targetOptions}
							hint="The form, view or report this action opens — or a custom module."
						/>
						<IconSelect
							label="Icon"
							value={draft.icon}
							onChange={(v) => setDraft((p) => ({ ...p, icon: v }))}
							hint="Shown beside the action in the module navigation."
						/>
						<SelectInput
							label="Minimum level"
							value={String(draft.level)}
							onChange={(v) => setDraft((p) => ({ ...p, level: Number(v) }))}
							options={[
								{ value: "0", label: "Editor (0)" },
								{ value: "1", label: "Admin (1)" },
								{ value: "2", label: "Developer (2)" },
							]}
						/>
					</FieldGrid>

					<CheckboxInput
						label="Show in module navigation"
						checked={draft.in_nav}
						onChange={(v) => setDraft((p) => ({ ...p, in_nav: v }))}
					/>

					{draft.target === TARGET_MODULE && (
						<div className="space-y-3 border-t border-border pt-3">
							<TextInput
								label="Handler"
								value={draft.handler}
								onChange={(v) => setDraft((p) => ({ ...p, handler: v }))}
								hint="Method on the module class host.invoke() submits to. Opt it in via getActionHandlers(). Optional — leave blank for a UI-only action."
								mono
							/>
							<ActionModuleEditor
								value={draft.source}
								onChange={(v) => setDraft((p) => ({ ...p, source: v }))}
								name={draft.name}
								route={draft.route}
							/>
						</div>
					)}
				</EditorCard>
			)}

			<SubDeleteDialog
				dialog={deleteDialog}
				noun="action"
				labelFor={(a) => a.name}
				description="This removes the action from the module's navigation. The form/view it points to is left intact."
				onConfirm={(id) => crud.remove(id)}
			/>
		</div>
	);
};
