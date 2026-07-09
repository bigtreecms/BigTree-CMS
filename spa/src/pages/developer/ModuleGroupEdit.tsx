import { Navigate, useParams } from "react-router-dom";
import { ChevronLeft } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Alert } from "@/components/ui/Alert";
import { FieldGrid } from "@/components/ui/FieldGrid";
import { EditPageGuard } from "@/components/ui/EditPageGuard";
import { Button } from "@/components/ui/Button";
import { FormFooter } from "@/components/ui/FormFooter";
import { FormShell } from "@/components/ui/FormShell";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
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
		<EditPageGuard
			width="narrow"
			loading={!isAdd && detailQ.isLoading}
			error={isAdd ? undefined : detailQ.error}
		>
			<PageContainer width="narrow">
				<Breadcrumb
					items={[
						{ label: "Developer", to: "/developer" },
						{ label: "Module groups", to: "/developer/module-groups" },
						{ label: isAdd ? "Add" : "Edit" },
					]}
				/>

				<PageHead
					title={isAdd ? "Add module group" : body.name || idParam || "Edit module group"}
					actions={
						<Button icon={<ChevronLeft size={13} />} to="/developer/module-groups">
							Back
						</Button>
					}
				/>

				<DeveloperSectionNav />

				{error && (
					<Alert tone="danger" className="mb-3">
						{error}
					</Alert>
				)}

				<FormShell
					onSubmit={(e) =>
						handleSubmit(
							e,
							() =>
								validateRequired([
									{ field: "name", label: "Name", value: body.name },
								]),
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

				<UnsavedChangesGuard isDirty={isDirty} />
			</PageContainer>
		</EditPageGuard>
	);
};
