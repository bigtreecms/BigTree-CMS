import { Navigate, useParams } from "react-router-dom";

import { FieldGrid } from "@/components/ui/FieldGrid";

import { DeveloperEditLayout } from "@/components/developer/DeveloperEditLayout";
import { ResourceDesigner, type ResourceEntry } from "@/components/developer/ResourceDesigner";
import { useResourceSettingsValidation } from "@/components/developer/field-settings/useResourceSettingsValidation";

import { calloutsApi, type CalloutEditBody, type CalloutSummary } from "@/api/endpoints/callouts";
import type { TemplateResource } from "@/api/endpoints/templates";

import { useDesignerSubmit } from "@/components/developer/useDesignerSubmit";

import { queryKeys } from "@/lib/queryKeys";
import { useResourceEditor } from "@/hooks/useResourceEditor";

import { SelectField } from "@/components/ui/SelectField";
import { TextField } from "@/components/ui/TextField";
import { SectionLabel } from "@/components/ui/SectionLabel";

export const CalloutEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const submit = useDesignerSubmit();

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
		onError: (err) => submit.onMutationError(err, "Save failed"),
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
		<DeveloperEditLayout
			width="medium"
			section="Callouts"
			listPath="/developer/callouts"
			isAdd={isAdd}
			title={title}
			sub={isAdd ? "Define a new callout type." : "Editing callout definition."}
			loading={!isAdd && detailQ.isLoading}
			queryError={isAdd ? undefined : detailQ.error}
			error={submit.error}
			isDirty={isDirty}
			formShellBounded={false}
			onSubmit={submit.buildSubmit({
				required: [
					{ field: "id", label: "ID", value: body.id },
					{ field: "name", label: "Name", value: body.name },
				],
				settingsValidation,
				save: () => save(body),
				saving,
			})}
			submitLabel={isAdd ? "Create callout" : "Save callout"}
			saving={saving}
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
						settingsErrors={submit.settingsErrors}
						displayFieldId={body.display_field}
						onSetDisplayField={(id) =>
							set({ display_field: id === body.display_field ? "" : id })
						}
					/>
				</div>
			</div>
		</DeveloperEditLayout>
	);
};
