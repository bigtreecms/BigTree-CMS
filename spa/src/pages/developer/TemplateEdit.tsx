import { Navigate, useParams } from "react-router-dom";
import { ChevronLeft } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Alert } from "@/components/ui/Alert";
import { FieldGrid } from "@/components/ui/FieldGrid";
import { EditPageGuard } from "@/components/ui/EditPageGuard";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { FormFooter } from "@/components/ui/FormFooter";
import { FormShell } from "@/components/ui/FormShell";
import { SelectField } from "@/components/ui/SelectField";
import { TextField } from "@/components/ui/TextField";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { SectionLabel } from "@/components/ui/SectionLabel";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { FormHooksEditor } from "@/components/developer/module-designer/FormHooksEditor";
import { ResourceDesigner, type ResourceEntry } from "@/components/developer/ResourceDesigner";
import { useResourceSettingsValidation } from "@/components/developer/field-settings/useResourceSettingsValidation";

import {
	templatesApi,
	type TemplateEditBody,
	type TemplateResource,
	type TemplateSummary,
} from "@/api/endpoints/templates";

import { useDesignerSubmit } from "@/components/developer/useDesignerSubmit";

import { queryKeys } from "@/lib/queryKeys";
import { useResourceEditor } from "@/hooks/useResourceEditor";

/**
 * Combined add / edit screen. Add mode: no `:id` route param.
 *
 * The form body is the union of mutable template fields; on submit we POST
 * (create) or PATCH (edit). Field 422s are routed by name.
 */
export const TemplateEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const submit = useDesignerSubmit();

	const { isAdd, detailQ, body, set, save, saving, isDirty } = useResourceEditor<
		TemplateSummary,
		TemplateEditBody
	>({
		listPath: "/developer/templates",
		entityLabel: "Template",
		queryKey: queryKeys.templates.detail(idParam as string),
		queryFn: () => templatesApi.get(idParam as string),
		initialBody: {
			id: "",
			name: "",
			module: "",
			level: 0,
			routed: false,
			resources: [],
			hooks: {},
		},
		seed: (data) => ({
			id: data.id,
			name: data.name,
			module: data.module,
			level: data.level,
			routed: data.routed,
			resources: data.resources,
			hooks: data.hooks ?? {},
		}),
		create: (next) => templatesApi.create(next),
		update: (id, next) => templatesApi.update(id, next),
		invalidateKey: queryKeys.templates.root(),
		editPath: (id) => `/developer/templates/${encodeURIComponent(id)}/edit`,
		onError: (err) => submit.onMutationError(err, "Save failed"),
	});

	const settingsValidation = useResourceSettingsValidation(
		(body.resources ?? []) as unknown as ResourceEntry[],
		"templates"
	);

	if (!isAdd && !idParam) {
		return <Navigate to="/developer/templates" replace />;
	}

	const handleSubmit = submit.buildSubmit({
		required: [
			{ field: "id", label: "ID", value: body.id },
			{ field: "name", label: "Name", value: body.name },
		],
		settingsValidation,
		save: () => save(body),
		saving,
	});

	const title = isAdd ? "Add template" : body.name || idParam || "Edit template";

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
						{ label: "Templates", to: "/developer/templates" },
						{ label: isAdd ? "Add" : "Edit" },
					]}
				/>

				<PageHead
					title={title}
					sub={isAdd ? "Define a new page template." : "Editing template definition."}
					actions={
						<Button icon={<ChevronLeft size={13} />} to="/developer/templates">
							Back
						</Button>
					}
				/>

				<DeveloperSectionNav />

				{submit.error && (
					<Alert tone="danger" className="mb-3">
						{submit.error}
					</Alert>
				)}

				<FormShell
					bounded={false}
					onSubmit={handleSubmit}
					footer={
						<FormFooter
							cancelTo="/developer/templates"
							submitLabel={isAdd ? "Create template" : "Save template"}
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
								error={submit.fieldErrors.id}
								disabled={!isAdd}
								required
							/>
							<TextField
								label="Name"
								value={body.name ?? ""}
								onChange={(v) => set({ name: v })}
								error={submit.fieldErrors.name}
								required
							/>
							{body.routed && (
								<TextField
									label="Module (optional)"
									value={body.module ?? ""}
									onChange={(v) => set({ module: v })}
									hint="Routed templates can bind to a module's content."
								/>
							)}
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
						</FieldGrid>

						<Checkbox
							label="Routed template (template handler can capture URL segments)"
							checked={Boolean(body.routed)}
							onChange={(routed) => set({ routed })}
						/>

						<div>
							<SectionLabel className="mb-2">
								Resources (page content fields)
							</SectionLabel>
							<ResourceDesigner
								resources={(body.resources ?? []) as unknown as ResourceEntry[]}
								onChange={(next) =>
									set({ resources: next as unknown as TemplateResource[] })
								}
								keyField="id"
								useCase="templates"
								settingsErrors={submit.settingsErrors}
							/>
						</div>

						<FormHooksEditor
							value={(body.hooks as Record<string, unknown>) ?? {}}
							onChange={(next) => set({ hooks: next })}
						/>
					</div>
				</FormShell>

				<UnsavedChangesGuard isDirty={isDirty} />
			</PageContainer>
		</EditPageGuard>
	);
};
