import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Alert } from "@/components/ui/Alert";
import { FieldGrid } from "@/components/ui/FieldGrid";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { FormFooter } from "@/components/ui/FormFooter";
import { FormShell } from "@/components/ui/FormShell";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { FieldSettingsEditor } from "@/components/developer/FieldSettingsEditor";
import type { ResourceEntry } from "@/components/developer/ResourceDesigner";
import { useResourceSettingsValidation } from "@/components/developer/field-settings/useResourceSettingsValidation";

import { settingsApi, type SettingCreateBody } from "@/api/endpoints/settings";
import { fieldTypesApi, fieldTypesForUseCase } from "@/api/endpoints/field-types";
import type { ModuleFormField } from "@/api/endpoints/modules";

import { HTMLFieldLazy } from "@/renderer/fields/HTMLFieldLazy";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { queryKeys } from "@/lib/queryKeys";
import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { validateRequired } from "@/lib/formValidation";

import { TextField } from "@/components/ui/TextField";
import { Select } from "@/components/ui/Select";
import { Loading } from "@/components/ui/Loading";
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
 * the `type` set here).
 */
export const SettingConfigure = () => {
	const navigate = useNavigate();
	const queryClient = useQueryClient();
	const { id: routeId } = useParams<{ id: string }>();
	const isEdit = Boolean(routeId);
	const settingId = routeId ?? "";

	const [body, setBody] = useState<SettingCreateBody>({
		id: "",
		name: "",
		description: "",
		type: "text",
		settings: {},
		locked: false,
		system: false,
		encrypted: false,
	});
	const [seeded, setSeeded] = useState(false);
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
	const [settingsErrors, setSettingsErrors] = useState<Record<string, string>>({});
	const [generalError, setGeneralError] = useState<string | null>(null);

	useScrollToFirstError(fieldErrors);
	useScrollToFirstError(settingsErrors);

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

	// In edit mode, load the existing definition and seed the form once.
	const existingQ = useQuery({
		queryKey: queryKeys.settings.detail(settingId),
		queryFn: () => settingsApi.get(settingId),
		enabled: isEdit,
	});

	useEffect(() => {
		if (!isEdit || seeded || !existingQ.data) {
			return;
		}

		const d = existingQ.data;

		setBody({
			id: d.id,
			name: d.name ?? "",
			description: d.description ?? "",
			type: d.type ?? "text",
			settings: (d.settings as SettingCreateBody["settings"]) ?? {},
			locked: !!d.locked,
			system: !!d.system,
			encrypted: !!d.encrypted,
			extension: d.extension ?? undefined,
		});
		setSeeded(true);
	}, [isEdit, seeded, existingQ.data]);

	function handleMutationError(err: unknown) {
		if (err instanceof ApiError) {
			const fe = err.fieldErrors();

			if (Object.keys(fe).length > 0) {
				setFieldErrors(fe);
			}

			setGeneralError(err.message);
		} else {
			setGeneralError(err instanceof Error ? err.message : "Save failed");
		}
	}

	const createMutation = useMutation({
		mutationFn: () => settingsApi.create(body),
		onSuccess: (fresh) => {
			queryClient.invalidateQueries({ queryKey: queryKeys.settings.root() });
			toast.success("Setting created");
			navigate(`/settings/${encodeURIComponent(fresh.id)}/edit`);
		},
		onError: handleMutationError,
	});

	const updateMutation = useMutation({
		mutationFn: () => settingsApi.updateDefinition(settingId, body),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: queryKeys.settings.root() });
			toast.success("Setting saved");
			navigate("/developer/settings");
		},
		onError: handleMutationError,
	});

	const set = (patch: Partial<SettingCreateBody>) => setBody((prev) => ({ ...prev, ...patch }));

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

	const isPending = createMutation.isPending || updateMutation.isPending;

	// In add mode the form is pristine immediately; in edit mode wait for the
	// loaded definition to seed `body` before baselining.
	const isDirty = useDirtyTracker(body, !isEdit || seeded) && !isPending;

	const submit = () => {
		const errors = validateRequired([{ field: "id", label: "ID", value: body.id }]);
		const sErrors = settingsValidation.validate()[0] ?? {};

		if (Object.keys(errors).length > 0 || Object.keys(sErrors).length > 0) {
			setFieldErrors(errors);
			setSettingsErrors(sErrors);
			setGeneralError("Please fill in the required fields.");

			return;
		}

		setFieldErrors({});
		setSettingsErrors({});
		setGeneralError(null);

		if (isEdit) {
			updateMutation.mutate();
		} else {
			createMutation.mutate();
		}
	};

	if (isEdit && (existingQ.isLoading || !existingQ.data)) {
		return (
			<PageContainer width="narrow">
				<Loading variant="card" />
			</PageContainer>
		);
	}

	return (
		<PageContainer width="narrow">
			<Breadcrumb
				items={[
					{ label: "Developer", to: "/developer" },
					{ label: "Settings", to: "/developer/settings" },
					{ label: isEdit ? body.name || settingId : "Add" },
				]}
			/>

			<PageHead
				title={isEdit ? "Edit setting" : "Add setting"}
				sub={
					isEdit
						? "Edit the definition. Values are edited from the user-facing Settings list."
						: "Define a new setting. The value-editor opens after creation."
				}
				actions={
					<Button icon={<ChevronLeft size={13} />} to="/developer/settings">
						Back
					</Button>
				}
			/>

			<DeveloperSectionNav />

			{generalError && (
				<Alert tone="danger" className="mb-3">
					{generalError}
				</Alert>
			)}

			<FormShell
				onSubmit={(e) => {
					e.preventDefault();
					submit();
				}}
				footer={
					<FormFooter
						cancelTo="/developer/settings"
						submitLabel={isEdit ? "Save setting" : "Create setting"}
						loading={isPending}
						loadingLabel={isEdit ? "Saving…" : "Creating…"}
					/>
				}
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
									<option value={body.type ?? "text"}>
										Loading field types…
									</option>
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
			</FormShell>

			<UnsavedChangesGuard isDirty={isDirty} />
		</PageContainer>
	);
};
