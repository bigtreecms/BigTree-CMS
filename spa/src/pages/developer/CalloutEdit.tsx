import { useState } from "react";
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
import { ResourceDesigner, type ResourceEntry } from "@/components/developer/ResourceDesigner";
import { useResourceSettingsValidation } from "@/components/developer/field-settings/useResourceSettingsValidation";

import { calloutsApi, type CalloutEditBody, type CalloutSummary } from "@/api/endpoints/callouts";
import type { TemplateResource } from "@/api/endpoints/templates";

import { queryKeys } from "@/lib/queryKeys";
import { useFormSubmit } from "@/hooks/useFormSubmit";
import { useResourceEditor } from "@/hooks/useResourceEditor";
import { validateRequired } from "@/lib/formValidation";

import { SelectField } from "@/components/ui/SelectField";
import { TextField } from "@/components/ui/TextField";
import { SectionLabel } from "@/components/ui/SectionLabel";

export const CalloutEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const { error, setError, fieldErrors, setFieldErrors, onMutationError } = useFormSubmit();
	const [settingsErrors, setSettingsErrors] = useState<Record<number, Record<string, string>>>(
		{}
	);

	const { isAdd, detailQ, body, set, save, saving, isDirty } = useResourceEditor<
		CalloutSummary,
		CalloutEditBody
	>({
		listPath: "/developer/callouts",
		entityLabel: "Callout",
		queryKey: queryKeys.callouts.detail(idParam as string),
		queryFn: () => calloutsApi.get(idParam as string),
		initialBody: {
			id: "",
			name: "",
			description: "",
			level: 0,
			display_field: "",
			display_default: "",
			resources: [],
		},
		seed: (data) => ({
			id: data.id,
			name: data.name,
			description: data.description,
			level: data.level,
			display_field: data.display_field,
			display_default: data.display_default,
			resources: data.resources,
		}),
		create: (next) => calloutsApi.create(next),
		update: (id, next) => calloutsApi.update(id, next),
		invalidateKey: queryKeys.callouts.root(),
		editPath: (id) => `/developer/callouts/${encodeURIComponent(id)}/edit`,
		onError: (err) => onMutationError(err, "Save failed"),
	});

	const settingsValidation = useResourceSettingsValidation(
		(body.resources ?? []) as unknown as ResourceEntry[],
		"callouts"
	);

	if (!isAdd && !idParam) {
		return <Navigate to="/developer/callouts" replace />;
	}

	const title = isAdd ? "Add callout" : body.name || idParam || "Edit callout";

	return (
		<EditPageGuard
			width="medium"
			loading={!isAdd && detailQ.isLoading}
			error={isAdd ? undefined : detailQ.error}
		>
			<PageContainer width="medium">
				<Breadcrumb
					items={[
						{ label: "Developer", to: "/developer" },
						{ label: "Callouts", to: "/developer/callouts" },
						{ label: isAdd ? "Add" : "Edit" },
					]}
				/>

				<PageHead
					title={title}
					sub={isAdd ? "Define a new callout type." : "Editing callout definition."}
					actions={
						<Button icon={<ChevronLeft size={13} />} to="/developer/callouts">
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
					bounded={false}
					onSubmit={(e) => {
						e.preventDefault();

						const errors = validateRequired([
							{ field: "id", label: "ID", value: body.id },
							{ field: "name", label: "Name", value: body.name },
						]);
						const sErrors = settingsValidation.validate();

						if (Object.keys(errors).length > 0 || Object.keys(sErrors).length > 0) {
							setFieldErrors(errors);
							setSettingsErrors(sErrors);
							setError("Please fill in the required fields.");

							return;
						}

						setFieldErrors({});
						setSettingsErrors({});
						setError(null);
						save(body);
					}}
					footer={
						<FormFooter
							cancelTo="/developer/callouts"
							submitLabel={isAdd ? "Create callout" : "Save callout"}
							loading={saving}
							loadingLabel="Saving…"
						/>
					}
				>
					<div className="space-y-4">
						<FieldGrid>
							<TextField
								label="ID"
								value={body.id ?? ""}
								onChange={(v) => set({ id: v })}
								hint="Lowercase, hyphens or underscores. Cannot change after create."
								error={fieldErrors.id}
								disabled={!isAdd}
								required
							/>
							<TextField
								label="Name"
								value={body.name ?? ""}
								onChange={(v) => set({ name: v })}
								error={fieldErrors.name}
								required
							/>
						</FieldGrid>

						<TextField
							label="Description"
							value={body.description ?? ""}
							onChange={(v) => set({ description: v })}
						/>

						<FieldGrid>
							<SelectField
								label="Minimum user level"
								value={String(body.level ?? 0)}
								onChange={(v) => set({ level: Number(v) })}
								options={[
									{ value: "0", label: "Editor (0)" },
									{ value: "1", label: "Admin (1)" },
									{ value: "2", label: "Developer (2)" },
								]}
							/>
							<TextField
								label="Default title text"
								value={body.display_default ?? ""}
								onChange={(v) => set({ display_default: v })}
								hint="Fallback shown when display_field is empty."
							/>
						</FieldGrid>

						<div>
							<SectionLabel className="mb-2">Fields</SectionLabel>
							<ResourceDesigner
								resources={(body.resources ?? []) as unknown as ResourceEntry[]}
								onChange={(next) =>
									set({ resources: next as unknown as TemplateResource[] })
								}
								keyField="id"
								useCase="callouts"
								settingsErrors={settingsErrors}
								displayFieldId={body.display_field}
								onSetDisplayField={(id) =>
									set({ display_field: id === body.display_field ? "" : id })
								}
							/>
						</div>
					</div>
				</FormShell>

				<UnsavedChangesGuard isDirty={isDirty} />
			</PageContainer>
		</EditPageGuard>
	);
};
