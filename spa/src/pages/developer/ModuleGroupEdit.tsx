import { Navigate, useParams } from "react-router-dom";

import { FieldGrid } from "@/components/ui/FieldGrid";
import { FormFooter } from "@/components/ui/FormFooter";
import { FormShell } from "@/components/ui/FormShell";

import { DeveloperEditLayout } from "@/components/developer/DeveloperEditLayout";
import { ModuleGroupModulesList } from "@/components/developer/ModuleGroupModulesList";

import { modulesApi, type ModuleGroup } from "@/api/endpoints/modules";

import { queryKeys } from "@/lib/queryKeys";
import { developerEditPath } from "@/lib/routes";
import { useFormSubmit } from "@/hooks/useFormSubmit";
import { useResourceEditor } from "@/hooks/useResourceEditor";
import { validateRequired } from "@/lib/formValidation";

import { TextField } from "@/components/ui/TextField";

interface Body {
	name: string;
	position: number;
	route: string;
}

export const ModuleGroupEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const { error, fieldErrors, handleSubmit, onMutationError } = useFormSubmit();

	const { isAdd, detailQ, body, set, save, saving, isDirty } = useResourceEditor<
		ModuleGroup[],
		Body
	>({
		listPath: "/developer/module-groups",
		entityLabel: "Group",
		queryKey: queryKeys.moduleGroups.list(),
		queryFn: () => modulesApi.listGroups(),
		initialBody: { name: "", route: "", position: 0 },
		seed: (groups, id) => {
			const found = groups.find((g) => g.id === id);

			return found
				? { name: found.name, route: found.route ?? "", position: found.position ?? 0 }
				: { name: "", route: "", position: 0 };
		},
		create: (next) =>
			modulesApi.createGroup({ name: next.name, route: next.route || undefined }),
		update: (id, next) => modulesApi.updateGroup(id, next),
		invalidateKey: queryKeys.moduleGroups.root(),
		editPath: (id) => developerEditPath("module-groups", id),
		onError: (err) => onMutationError(err, "Save failed"),
	});

	if (!isAdd && !idParam) {
		return <Navigate replace to="/developer/module-groups" />;
	}

	return (
		<DeveloperEditLayout
			detailQuery={detailQ}
			error={error}
			isAdd={isAdd}
			isDirty={isDirty}
			listPath="/developer/module-groups"
			section="Module groups"
			title={isAdd ? "Add module group" : body.name || idParam || "Edit module group"}
			width="narrow"
		>
			{/* FormShell stays here: ModuleGroupModulesList sits outside the form. */}
			<FormShell
				footer={
					<FormFooter
						cancelTo="/developer/module-groups"
						loading={saving}
						loadingLabel="Saving…"
						submitLabel={isAdd ? "Create group" : "Save group"}
					/>
				}
				onSubmit={(e) =>
					handleSubmit(
						e,
						() =>
							validateRequired([{ field: "name", label: "Name", value: body.name }]),
						() => save(body)
					)
				}
			>
				<div className="space-y-4">
					<TextField
						required
						error={fieldErrors.name}
						label="Name"
						value={body.name}
						onChange={(v) => set({ name: v })}
					/>
					<FieldGrid>
						<TextField
							error={fieldErrors.route}
							hint="Used on the Modules tab URL."
							label="Route"
							value={body.route}
							onChange={(v) => set({ route: v })}
						/>
						<TextField
							label="Position"
							type="number"
							value={String(body.position)}
							onChange={(v) => set({ position: Number(v) || 0 })}
						/>
					</FieldGrid>
				</div>
			</FormShell>

			{!isAdd && idParam && (
				<div className="mt-4">
					<ModuleGroupModulesList groupId={idParam} />
				</div>
			)}
		</DeveloperEditLayout>
	);
};
