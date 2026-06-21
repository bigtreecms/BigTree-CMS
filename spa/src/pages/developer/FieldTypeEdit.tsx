import { useEffect, useState } from "react";
import { Navigate, useNavigate, useParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft, Save } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { InputSchemaBuilder } from "@/components/developer/InputSchemaBuilder";
import { ModuleSourceEditor } from "@/components/developer/field-module/ModuleSourceEditor";
import {
	MODULE_STARTER,
	MODULE_STARTER_SETTINGS,
} from "@/components/developer/field-module/starterTemplate";

import {
	fieldTypesApi,
	type FieldTypeCreateBody,
	type FieldUseCase,
	type InputDescriptor,
	type SettingDescriptor,
} from "@/api/endpoints/field-types";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { useReturnTo } from "@/hooks/useReturnTo";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { validateRequired } from "@/lib/formValidation";

import { TextField } from "@/components/ui/TextField";
import { EmptyState } from "@/components/ui/EmptyState";

/**
 * The detail endpoint returns a field type's raw record, where `use_cases` may be
 * either the stored associative map ({ templates: "on" }) or — for older flat
 * payloads — a list of slugs. Normalize both to the list the checkboxes use.
 */
const toUseCaseList = (value: unknown): string[] => {
	if (Array.isArray(value)) {
		return value.filter((v): v is string => typeof v === "string");
	}

	if (value && typeof value === "object") {
		return Object.entries(value as Record<string, unknown>)
			.filter(([, v]) => !!v)
			.map(([k]) => k);
	}

	return [];
};

const USE_CASES: Array<{ value: FieldUseCase; label: string }> = [
	{ value: "templates", label: "Templates" },
	{ value: "modules", label: "Modules" },
	{ value: "settings", label: "Settings" },
	{ value: "callouts", label: "Callouts" },
];

export const FieldTypeEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const isAdd = !idParam;
	const navigate = useNavigate();
	const returnTo = useReturnTo("/developer/field-types");
	const queryClient = useQueryClient();

	const detailQ = useQuery({
		queryKey: ["field-types", "detail", idParam],
		queryFn: () => fieldTypesApi.get(idParam as string),
		enabled: !isAdd,
	});

	const [body, setBody] = useState<FieldTypeCreateBody>(() =>
		isAdd ? { id: "", name: "", use_cases: [], input_schema: [] } : { id: "" }
	);
	const [mode, setMode] = useState<"declarative" | "module">("declarative");
	const [seeded, setSeeded] = useState(isAdd);
	const [isLegacy, setIsLegacy] = useState(false);
	const [settingsParseError, setSettingsParseError] = useState(false);
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

	useScrollToFirstError(fieldErrors);
	const [generalError, setGeneralError] = useState<string | null>(null);

	useEffect(() => {
		if (!isAdd && detailQ.data) {
			const data = detailQ.data;
			const inputSchema = Array.isArray(data.input_schema)
				? (data.input_schema as InputDescriptor[])
				: [];
			const moduleSource = typeof data.module_source === "string" ? data.module_source : "";
			const isModule = data.render === "module" || moduleSource !== "" || !!data.asset_url;

			setBody({
				id: data.id,
				name: data.name,
				use_cases: toUseCaseList(data.use_cases),
				input_schema: inputSchema,
				module_source: moduleSource,
				settings_schema: Array.isArray(data.settings_schema)
					? (data.settings_schema as SettingDescriptor[])
					: [],
			});
			setMode(isModule ? "module" : "declarative");
			setSeeded(true);
			setSettingsParseError(data.settings_parse_error === true);

			// A pre-existing record with no input_schema and no module is a legacy
			// draw.php type — saving here migrates it to the chosen mode. An
			// extension-delivered module (asset_url, no local source) is also flagged
			// so the author knows saving replaces it with local code.
			setIsLegacy(
				(!isModule && inputSchema.length === 0) || (!!data.asset_url && !moduleSource)
			);
		}
	}, [isAdd, detailQ.data]);

	const saveMutation = useMutation({
		mutationFn: (next: FieldTypeCreateBody) =>
			isAdd ? fieldTypesApi.create(next) : fieldTypesApi.update(idParam as string, next),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["field-types"] });
			toast.success(isAdd ? "Field type created" : "Field type saved");
			navigate(returnTo);
		},
		onError: (err) => {
			if (err instanceof ApiError) {
				const fe = err.fieldErrors();

				if (Object.keys(fe).length > 0) {
					setFieldErrors(fe);
				}

				setGeneralError(err.message);
			} else {
				setGeneralError(err instanceof Error ? err.message : "Save failed");
			}
		},
	});

	// Dirty while the user has edited the form, but not while a save is in flight
	// (a successful save navigates away and must not be intercepted).
	const isDirty = useDirtyTracker({ body, mode }, seeded) && !saveMutation.isPending;

	if (!isAdd && !idParam) {
		return <Navigate to="/developer/field-types" replace />;
	}

	if (!isAdd && detailQ.isLoading) {
		return (
			<div className="mx-auto max-w-screen-md px-6 py-4">
				<EmptyState>Loading…</EmptyState>
			</div>
		);
	}

	if (!isAdd && detailQ.error) {
		return (
			<div className="mx-auto max-w-screen-md px-6 py-4">
				<ErrorPanel error={detailQ.error} />
			</div>
		);
	}

	const set = (patch: Partial<FieldTypeCreateBody>) => setBody((prev) => ({ ...prev, ...patch }));
	const selectedUseCases = new Set(body.use_cases ?? []);

	const toggleUseCase = (v: string) => {
		const next = new Set(selectedUseCases);

		if (next.has(v)) {
			next.delete(v);
		} else {
			next.add(v);
		}

		set({ use_cases: Array.from(next) });
	};

	const title = isAdd ? "Add custom field type" : body.name || idParam || "Edit field type";

	return (
		<div className="mx-auto max-w-screen-md px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Developer", to: "/developer" },
					{ label: "Field types", to: "/developer/field-types" },
					{ label: isAdd ? "Add" : "Edit" },
				]}
			/>

			<PageHead
				title={title}
				sub="Compose a custom field type from built-in primitives (declarative), or write a JavaScript module that draws it in the SPA."
				actions={
					<Button icon={<ChevronLeft size={13} />} to="/developer/field-types">
						Back
					</Button>
				}
			/>

			<DeveloperSectionNav />

			{generalError && (
				<div className="mb-3 rounded-md border border-danger/40 bg-danger/5 px-3 py-2 text-[12.5px] text-danger">
					{generalError}
				</div>
			)}

			<form
				onSubmit={(e) => {
					e.preventDefault();

					const errors = validateRequired([
						{ field: "id", label: "ID", value: body.id },
						{ field: "name", label: "Name", value: body.name },
					]);

					if (Object.keys(errors).length > 0) {
						setFieldErrors(errors);
						setGeneralError("Please fill in the required fields.");

						return;
					}

					setFieldErrors({});
					setGeneralError(null);

					if (mode !== "module") {
						saveMutation.mutate({
							...body,
							render: "declarative",
							value_type: "object",
							input_schema: body.input_schema ?? [],
						});

						return;
					}

					if (!(body.module_source ?? "").trim()) {
						setGeneralError("Please write the module's code before saving.");

						return;
					}

					saveMutation.mutate({
						...body,
						render: "module",
						input_schema: [],
						module_source: body.module_source ?? "",
						value_type: body.value_type ?? "string",
						settings_schema: body.settings_schema ?? [],
					});
				}}
				className="space-y-4 rounded-xl border border-border bg-surface p-4"
			>
				<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
					<TextField
						label="ID"
						value={body.id ?? ""}
						onChange={(v) => set({ id: v })}
						hint="A unique identifier (letters, numbers, - or _)."
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
				</div>

				<div>
					<div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
						Use cases
					</div>
					<div className="flex flex-wrap gap-3 rounded-md border border-border bg-surface-2 p-3">
						{USE_CASES.map((u) => (
							<Checkbox
								key={u.value}
								label={u.label}
								checked={selectedUseCases.has(u.value)}
								onChange={() => toggleUseCase(u.value)}
							/>
						))}
					</div>
				</div>

				{isLegacy && (
					<div className="rounded-md border border-accent/30 bg-accent-soft px-3 py-2 text-[12px] text-text-2">
						This is a legacy <code>draw.php</code> field type. It still renders through
						the server bridge, but new types can&apos;t be authored that way — pick a
						render mode below to migrate it. Saving will convert it.
					</div>
				)}

				<div>
					<div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
						Rendering
					</div>
					<div className="flex flex-col gap-2 rounded-md border border-border bg-surface-2 p-3">
						<label className="flex items-start gap-2 text-[12.5px] text-text-2">
							<input
								type="radio"
								name="render-mode"
								className="mt-0.5 h-4 w-4 accent-accent"
								checked={mode === "declarative"}
								onChange={() => setMode("declarative")}
							/>
							<span>
								<span className="font-medium">Declarative</span> — compose this
								field from built-in primitives. No code; renders natively in the
								SPA.
							</span>
						</label>
						<label className="flex items-start gap-2 text-[12.5px] text-text-2">
							<input
								type="radio"
								name="render-mode"
								className="mt-0.5 h-4 w-4 accent-accent"
								checked={mode === "module"}
								onChange={() => {
									setMode("module");

									if (!(body.module_source ?? "").trim()) {
										set({
											module_source: MODULE_STARTER,
											settings_schema:
												body.settings_schema &&
												body.settings_schema.length > 0
													? body.settings_schema
													: MODULE_STARTER_SETTINGS,
										});
									}
								}}
							/>
							<span>
								<span className="font-medium">JavaScript module</span> — write code
								that draws the field. Runs locally in the SPA; you implicitly trust
								your own code (distribution trust is handled when packaging an
								extension).
							</span>
						</label>
					</div>
				</div>

				{mode === "declarative" ? (
					<div>
						<div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
							Fields
						</div>
						<InputSchemaBuilder
							value={body.input_schema ?? []}
							onChange={(next) => set({ input_schema: next })}
						/>
					</div>
				) : (
					<ModuleSourceEditor
						value={body.module_source ?? ""}
						onChange={(v) => set({ module_source: v })}
						settingsSchema={body.settings_schema ?? []}
						onSettingsSchemaChange={(next) => {
							setSettingsParseError(false);
							set({ settings_schema: next });
						}}
						settingsParseError={settingsParseError}
						typeId={body.id ?? ""}
						name={body.name ?? ""}
					/>
				)}

				<div className="flex justify-end gap-2 border-t border-border pt-3">
					<Button to="/developer/field-types">Cancel</Button>
					<Button
						variant="primary"
						type="submit"
						icon={<Save size={13} />}
						disabled={saveMutation.isPending}
					>
						{saveMutation.isPending ? "Saving…" : isAdd ? "Create field type" : "Save"}
					</Button>
				</div>
			</form>

			<UnsavedChangesGuard isDirty={isDirty} />
		</div>
	);
};
