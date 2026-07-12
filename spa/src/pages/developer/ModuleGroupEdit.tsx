import { Navigate, useParams } from "react-router-dom";

import { FieldGrid } from "@/components/ui/FieldGrid";
import { FormFooter } from "@/components/ui/FormFooter";
import { FormShell } from "@/components/ui/FormShell";

import { DeveloperEditLayout } from "@/components/developer/DeveloperEditLayout";
import { ModuleGroupModulesList } from "@/components/developer/ModuleGroupModulesList";

import { modulesApi, type ModuleGroup } from "@/api/endpoints/modules";

import { queryKeys } from "@/lib/queryKeys";
import { useFormSubmit } from "@/hooks/useFormSubmit";
import { useResourceEditor } from "@/hooks/useResourceEditor";
import { validateRequired } from "@/lib/formValidation";

import { TextField } from "@/components/ui/TextField";

interface Body {
	name: string;
	route: string;
	position: number;
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
		editPath: (id) => `/developer/module-groups/${encodeURIComponent(id)}/edit`,
		onError: (err) => onMutationError(err, "Save failed"),
	});

	if (!isAdd && !idParam) {
		return <Navigate to="/developer/module-groups" replace />;
	}

	return (
		<DeveloperEditLayout
			width="narrow"
			section="Module groups"
			listPath="/developer/module-groups"
			isAdd={isAdd}
			title={isAdd ? "Add module group" : body.name || idParam || "Edit module group"}
			loading={!isAdd && detailQ.isLoading}
			queryError={isAdd ? undefined : detailQ.error}
			error={error}
			isDirty={isDirty}
		>
			{/* FormShell stays here: ModuleGroupModulesList sits outside the form. */}
			<FormShell
				onSubmit={(e) =>
					handleSubmit(
						e,
						() =>
							validateRequired([{ field: "name", label: "Name", value: body.name }]),
						() => save(body)
					)
				}
				footer={
					<FormFooter
						cancelTo="/developer/module-groups"
						submitLabel={isAdd ? "Create group" : "Save group"}
						loading={saving}
						loadingLabel="Saving…"
					/>
				}
			>
				<div className="space-y-4">
					<TextField
						label="Name"
						value={body.name}
						onChange={(v) => set({ name: v })}
						error={fieldErrors.name}
						required
					/>
					<FieldGrid>
						<TextField
							label="Route"
							value={body.route}
							onChange={(v) => set({ route: v })}
							hint="Used on the Modules tab URL."
							error={fieldErrors.route}
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
