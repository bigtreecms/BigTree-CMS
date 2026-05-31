import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { modulesApi, type ModuleAction, type ModuleActionBody } from "@/api/endpoints/modules";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";

import { useDragReorder } from "@/hooks/useDragReorder";

import { CheckboxInput, SelectInput, TextInput } from "./inputs";
import { AddSubButton, EditorCard, SubList, SubRow } from "./scaffold";
import { NEW_ROW, useSubCrud } from "./useSubCrud";

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
};

const TARGET_NONE = "";

const draftFromAction = (a: ModuleAction): Draft => {
	let target = TARGET_NONE;

	if (a.form) {
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
	};
};

const emptyDraft = (): Draft => ({
	name: "",
	route: "",
	in_nav: true,
	icon: "",
	level: 0,
	target: TARGET_NONE,
});

export const ModuleActionsTab = ({ moduleId }: ModuleActionsTabProps) => {
	const queryClient = useQueryClient();
	const crud = useSubCrud<ModuleAction, ModuleActionBody>({
		moduleId,
		resource: "actions",
		label: "Action",
		listFn: (id) => modulesApi.actions(id),
		createFn: (id, body) => modulesApi.createAction(id, body),
		updateFn: (id, sid, body) => modulesApi.updateAction(id, sid, body),
		deleteFn: (id, sid) => modulesApi.deleteAction(id, sid),
	});

	const [draft, setDraft] = useState<Draft>(emptyDraft);
	const [pendingDelete, setPendingDelete] = useState<ModuleAction | null>(null);

	const formsQ = useQuery({
		queryKey: ["modules", moduleId, "forms"],
		queryFn: () => modulesApi.forms(moduleId),
	});
	const viewsQ = useQuery({
		queryKey: ["modules", moduleId, "views"],
		queryFn: () => modulesApi.views(moduleId),
	});
	const reportsQ = useQuery({
		queryKey: ["modules", moduleId, "reports"],
		queryFn: () => modulesApi.reports(moduleId),
	});

	useEffect(() => {
		if (crud.editingId === NEW_ROW) {
			setDraft(emptyDraft());
		} else if (crud.editingId) {
			const found = crud.items.find((a) => a.id === crud.editingId);

			if (found) {
				setDraft(draftFromAction(found));
			}
		}
	}, [crud.editingId, crud.items]);

	const reorderMutation = useMutation({
		mutationFn: (ids: string[]) => modulesApi.reorderActions(moduleId, ids),
		onSuccess: () =>
			queryClient.invalidateQueries({ queryKey: ["modules", moduleId, "actions"] }),
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Reorder failed");
			queryClient.invalidateQueries({ queryKey: ["modules", moduleId, "actions"] });
		},
	});

	const drag = useDragReorder<ModuleAction, string>(
		crud.items,
		(next) => queryClient.setQueryData(["modules", moduleId, "actions"], next),
		(ids) => reorderMutation.mutate(ids)
	);

	const targetOptions = [
		{ value: TARGET_NONE, label: "— No target —" },
		...(formsQ.data ?? []).map((f) => ({ value: `form:${f.id}`, label: `Form: ${f.title}` })),
		...(viewsQ.data ?? []).map((v) => ({ value: `view:${v.id}`, label: `View: ${v.title}` })),
		...(reportsQ.data ?? []).map((r) => ({
			value: `report:${r.id}`,
			label: `Report: ${r.title}`,
		})),
	];

	const toBody = (d: Draft): ModuleActionBody => {
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
		};
	};

	const handleSave = () => crud.save(crud.editingId, toBody(draft));

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
						subtitle={a.route}
						badge={a.in_nav === true || a.in_nav === "on" ? "in nav" : undefined}
						onEdit={() => crud.startEdit(a.id)}
						onDelete={() => setPendingDelete(a)}
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
					<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
						<TextInput
							label="Name"
							value={draft.name}
							onChange={(v) => setDraft((p) => ({ ...p, name: v }))}
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
							onChange={(v) => setDraft((p) => ({ ...p, target: v }))}
							options={targetOptions}
							hint="The form, view or report this action opens."
						/>
						<TextInput
							label="Icon"
							value={draft.icon}
							onChange={(v) => setDraft((p) => ({ ...p, icon: v }))}
							hint="BigTree icon glyph."
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
					</div>

					<CheckboxInput
						label="Show in module navigation"
						checked={draft.in_nav}
						onChange={(v) => setDraft((p) => ({ ...p, in_nav: v }))}
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
					title={`Delete action "${pendingDelete.name}"?`}
					description="This removes the action from the module's navigation. The form/view it points to is left intact."
					confirmLabel="Delete action"
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
