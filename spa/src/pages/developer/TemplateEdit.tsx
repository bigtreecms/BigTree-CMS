import { Navigate, useParams } from "react-router-dom";

import { FieldGrid } from "@/components/ui/FieldGrid";
import { Checkbox } from "@/components/ui/Checkbox";
import { SelectField } from "@/components/ui/SelectField";
import { TextField } from "@/components/ui/TextField";
import { SectionLabel } from "@/components/ui/SectionLabel";

import { DeveloperEditLayout } from "@/components/developer/DeveloperEditLayout";
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
import { developerEditPath } from "@/lib/routes";
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
		editPath: (id) => developerEditPath("templates", id),
		onError: (err) => submit.onMutationError(err, "Save failed"),
	});

	const settingsValidation = useResourceSettingsValidation(
		(body.resources ?? []) as unknown as ResourceEntry[],
		"templates"
	);

	if (!isAdd && !idParam) {
		return <Navigate replace to="/developer/templates" />;
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
		<DeveloperEditLayout
			detailQuery={detailQ}
			error={submit.error}
			formShellBounded={false}
			isAdd={isAdd}
			isDirty={isDirty}
			listPath="/developer/templates"
			saving={saving}
			section="Templates"
			sub={isAdd ? "Define a new page template." : "Editing template definition."}
			submitLabel={isAdd ? "Create template" : "Save template"}
			title={title}
			width="medium"
			onSubmit={handleSubmit}
		>
			<div className="space-y-4">
				<FieldGrid>
					<TextField
						required
						disabled={!isAdd}
						error={submit.fieldErrors.id}
						hint="Lowercase, hyphens or underscores. Cannot change after create."
						label="ID"
						value={body.id ?? ""}
						onChange={(v) => set({ id: v })}
					/>
					<TextField
						required
						error={submit.fieldErrors.name}
						label="Name"
						value={body.name ?? ""}
						onChange={(v) => set({ name: v })}
					/>
					{body.routed && (
						<TextField
							hint="Routed templates can bind to a module's content."
							label="Module (optional)"
							value={body.module ?? ""}
							onChange={(v) => set({ module: v })}
						/>
					)}
					<SelectField
						label="Minimum user level"
						options={[
							{ value: "0", label: "Editor (0)" },
							{ value: "1", label: "Admin (1)" },
							{ value: "2", label: "Developer (2)" },
						]}
						value={String(body.level ?? 0)}
						onChange={(v) => set({ level: Number(v) })}
					/>
				</FieldGrid>

				<Checkbox
					checked={Boolean(body.routed)}
					label="Routed template (template handler can capture URL segments)"
					onChange={(routed) => set({ routed })}
				/>

				<div>
					<SectionLabel className="mb-2">Resources (page content fields)</SectionLabel>
					<ResourceDesigner
						keyField="id"
						resources={(body.resources ?? []) as unknown as ResourceEntry[]}
						settingsErrors={submit.settingsErrors}
						useCase="templates"
						onChange={(next) =>
							set({ resources: next as unknown as TemplateResource[] })
						}
					/>
				</div>

				<FormHooksEditor
					value={(body.hooks as Record<string, unknown>) ?? {}}
					onChange={(next) => set({ hooks: next })}
				/>
			</div>
		</DeveloperEditLayout>
	);
};
