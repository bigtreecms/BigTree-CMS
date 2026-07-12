import { useMemo, useState } from "react";
import { useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";

import { FieldGrid } from "@/components/ui/FieldGrid";
import { Checkbox } from "@/components/ui/Checkbox";

import { DeveloperEditLayout } from "@/components/developer/DeveloperEditLayout";
import { FieldSettingsEditor } from "@/components/developer/FieldSettingsEditor";
import type { ResourceEntry } from "@/components/developer/ResourceDesigner";
import { useResourceSettingsValidation } from "@/components/developer/field-settings/useResourceSettingsValidation";

import { settingsApi, type SettingCreateBody, type SettingDetail } from "@/api/endpoints/settings";
import { fieldTypesApi, fieldTypesForUseCase } from "@/api/endpoints/field-types";
import type { ModuleFormField } from "@/api/endpoints/modules";

import { HTMLFieldLazy } from "@/renderer/fields/HTMLFieldLazy";

import { queryKeys } from "@/lib/queryKeys";
import { useFormSubmit } from "@/hooks/useFormSubmit";
import { useResourceEditor } from "@/hooks/useResourceEditor";
import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { settingEditPath } from "@/lib/routes";
import { validateRequired } from "@/lib/formValidation";

import { TextField } from "@/components/ui/TextField";
import { Select } from "@/components/ui/Select";
import { Field, FieldLabel } from "@/components/ui/Field";

/**
 * Descriptions are WYSIWYG HTML. We reuse the renderer's HTMLField in its
 * "simple" variant (bold / italic / underline / link) — the same editor the
 * value-side uses for html-type settings.
 */
const DESCRIPTION_FIELD: ModuleFormField = {
	column: "description",
	title: "Description",
	type: "html",
	settings: { simple: true, height: 160 },
};

/**
 * /developer/settings/add — create a setting definition.
 * /developer/settings/:id/edit — edit an existing setting definition.
 *
 * This is the *definition* (schema) editor — name, type, type-specific
 * settings, and the encrypted/locked/system flags. The value-side editing
 * happens separately at /settings/:id/edit (which uses FieldRenderer based on
 * the `type` set here), which is also where a freshly created setting lands.
 */
export const SettingConfigure = () => {
	const { id: routeId } = useParams<{ id: string }>();
	const { error, fieldErrors, handleSubmit, onMutationError } = useFormSubmit();
	const [settingsErrors, setSettingsErrors] = useState<Record<string, string>>({});

	useScrollToFirstError(settingsErrors);

	const { isAdd, detailQ, body, set, save, saving, isDirty } = useResourceEditor<
		SettingDetail,
		SettingCreateBody
	>({
		listPath: "/developer/settings",
		entityLabel: "Setting",
		queryKey: queryKeys.settings.detail(routeId ?? ""),
		queryFn: () => settingsApi.get(routeId ?? ""),
		initialBody: {
			id: "",
			name: "",
			description: "",
			type: "text",
			settings: {},
			locked: false,
			system: false,
			encrypted: false,
		},
		seed: (d) => ({
			id: d.id,
			name: d.name ?? "",
			description: d.description ?? "",
			type: d.type ?? "text",
			settings: (d.settings as SettingCreateBody["settings"]) ?? {},
			locked: !!d.locked,
			system: !!d.system,
			encrypted: !!d.encrypted,
			extension: d.extension ?? undefined,
		}),
		create: (next) => settingsApi.create(next),
		update: (id, next) => settingsApi.updateDefinition(id, next),
		invalidateKey: queryKeys.settings.root(),
		// A freshly created definition opens straight into its value editor.
		editPath: (id) => settingEditPath(id),
		onError: (err) => onMutationError(err, "Save failed"),
	});

	const isEdit = !isAdd;

	// Validate the setting's own field-type settings by treating it as a
	// single-entry resource list, reusing the resource-designer validator.
	const settingResources = useMemo(
		() =>
			[
				{ type: body.type ?? "text", title: "", settings: body.settings ?? {} },
			] as ResourceEntry[],
		[body.type, body.settings]
	);
	const settingsValidation = useResourceSettingsValidation(settingResources, "settings");

	// Field type changes invalidate the type-specific settings, so clear them.
	const changeType = (type: string) => set({ type, settings: {} });

	const fieldTypesQ = useQuery({
		queryKey: queryKeys.fieldTypes.list(),
		queryFn: () => fieldTypesApi.list(),
	});

	// Same Default / Custom optgroups the legacy settings form draws, sourced
	// from the field-type registry so custom + extension types are selectable.
	const typeGroups = useMemo(() => {
		const all = fieldTypesForUseCase(fieldTypesQ.data, "settings");

		return [
			{ label: "Default", options: all.filter((t) => t.group === "default") },
			{ label: "Custom", options: all.filter((t) => t.group === "custom") },
		].filter((g) => g.options.length > 0);
	}, [fieldTypesQ.data]);

	const typeKnown = typeGroups.some((g) => g.options.some((o) => o.id === body.type));

	return (
		<DeveloperEditLayout
			width="narrow"
			section="Settings"
			listPath="/developer/settings"
			isAdd={isAdd}
			title={isEdit ? "Edit setting" : "Add setting"}
			sub={
				isEdit
					? "Edit the definition. Values are edited from the user-facing Settings list."
					: "Define a new setting. The value-editor opens after creation."
			}
			detailQuery={detailQ}
			error={error}
			isDirty={isDirty}
			onSubmit={(e) =>
				handleSubmit(
					e,
					() => {
						const errors = validateRequired([
							{ field: "id", label: "ID", value: body.id },
						]);
						const sErrors = settingsValidation.validate()[0] ?? {};

						// Surface settings errors alongside required-field errors.
						setSettingsErrors(sErrors);

						if (Object.keys(sErrors).length > 0 && Object.keys(errors).length === 0) {
							// Force the validate path to fail so handleSubmit sets the banner.
							return { _settings: "invalid" };
						}

						return errors;
					},
					() => {
						setSettingsErrors({});
						save(body);
					}
				)
			}
			submitLabel={isEdit ? "Save setting" : "Create setting"}
			saving={saving}
		>
			<div className="space-y-4">
				<FieldGrid>
					<TextField
						label="ID"
						value={body.id}
						onChange={(v) => set({ id: v })}
						hint={
							isEdit
								? "Stable storage key, cannot be changed."
								: "Stable storage key, cannot change later."
						}
						error={fieldErrors.id}
						disabled={isEdit}
						required
					/>
					<TextField
						label="Name"
						value={body.name ?? ""}
						onChange={(v) => set({ name: v })}
						error={fieldErrors.name}
					/>
				</FieldGrid>

				<div>
					<FieldLabel>Description</FieldLabel>
					<HTMLFieldLazy
						field={DESCRIPTION_FIELD}
						value={body.description ?? ""}
						onChange={(v) => set({ description: typeof v === "string" ? v : "" })}
					/>
				</div>

				<div>
					<Field className="max-w-sm" label="Field type">
						<Select
							value={body.type ?? "text"}
							onChange={(e) => changeType(e.target.value)}
							disabled={fieldTypesQ.isLoading}
						>
							{fieldTypesQ.isLoading && (
								<option value={body.type ?? "text"}>Loading field types…</option>
							)}
							{!fieldTypesQ.isLoading && !typeKnown && (
								<option value={body.type ?? ""}>{body.type}</option>
							)}
							{typeGroups.map((group) => (
								<optgroup key={group.label} label={group.label}>
									{group.options.map((o) => (
										<option key={o.id} value={o.id}>
											{o.name}
										</option>
									))}
								</optgroup>
							))}
						</Select>
						<span className="mt-1 block text-[11px] text-text-3">
							Determines the editor shown when setting this value, and the options
							below.
						</span>
					</Field>

					<div className="mt-3">
						<FieldLabel>Field settings</FieldLabel>
						<div className="rounded-md border border-border bg-surface-2 p-3">
							<FieldSettingsEditor
								type={body.type ?? "text"}
								useCase="settings"
								value={body.settings}
								onChange={(v) => set({ settings: v })}
								hideLabel
								errors={settingsErrors}
							/>
						</div>
					</div>
				</div>

				<div className="grid grid-cols-1 gap-3 rounded-md border border-border bg-surface-2 p-3 md:grid-cols-3">
					<Checkbox
						label="Encrypted at rest"
						checked={!!body.encrypted}
						onChange={(encrypted) => set({ encrypted })}
					/>
					<Checkbox
						label="Locked (cannot delete)"
						checked={!!body.locked}
						onChange={(locked) => set({ locked })}
					/>
					<Checkbox
						label="System"
						checked={!!body.system}
						onChange={(system) => set({ system })}
					/>
				</div>
			</div>
		</DeveloperEditLayout>
	);
};
