import { useEffect, useState } from "react";
import { Navigate, useNavigate, useParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft, Save } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { FormHooksEditor } from "@/components/developer/module-designer/FormHooksEditor";
import { ResourceDesigner, type ResourceEntry } from "@/components/developer/ResourceDesigner";
import { useResourceSettingsValidation } from "@/components/developer/field-settings/useResourceSettingsValidation";

import {
	templatesApi,
	type TemplateEditBody,
	type TemplateResource,
} from "@/api/endpoints/templates";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { useReturnTo } from "@/hooks/useReturnTo";
import { validateRequired } from "@/lib/formValidation";

/**
 * Combined add / edit screen. Add mode: no `:id` route param.
 *
 * The form body is the union of mutable template fields; on submit we POST
 * (create) or PATCH (edit). Field 422s are routed by name.
 */
export const TemplateEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const isAdd = !idParam;
	const navigate = useNavigate();
	const returnTo = useReturnTo("/developer/templates");
	const queryClient = useQueryClient();

	const detailQ = useQuery({
		queryKey: ["templates", "detail", idParam],
		queryFn: () => templatesApi.get(idParam as string),
		enabled: !isAdd,
	});

	const [body, setBody] = useState<TemplateEditBody>(() =>
		isAdd
			? { id: "", name: "", module: "", level: 0, routed: false, resources: [], hooks: {} }
			: {}
	);
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

	useScrollToFirstError(fieldErrors);
	const [settingsErrors, setSettingsErrors] = useState<Record<number, Record<string, string>>>(
		{}
	);
	const [generalError, setGeneralError] = useState<string | null>(null);
	const [seeded, setSeeded] = useState(isAdd);

	const settingsValidation = useResourceSettingsValidation(
		(body.resources ?? []) as unknown as ResourceEntry[],
		"templates"
	);

	useEffect(() => {
		if (!isAdd && detailQ.data) {
			setBody({
				id: detailQ.data.id,
				name: detailQ.data.name,
				module: detailQ.data.module,
				level: detailQ.data.level,
				routed: detailQ.data.routed,
				resources: detailQ.data.resources,
				hooks: detailQ.data.hooks ?? {},
			});
			setSeeded(true);
		}
	}, [isAdd, detailQ.data]);

	const saveMutation = useMutation({
		mutationFn: (next: TemplateEditBody) =>
			isAdd ? templatesApi.create(next) : templatesApi.update(idParam as string, next),
		onSuccess: (fresh) => {
			queryClient.invalidateQueries({ queryKey: ["templates"] });
			toast.success(isAdd ? "Template created" : "Template saved");

			if (isAdd) {
				navigate(`/developer/templates/${encodeURIComponent(fresh.id)}/edit`, {
					replace: true,
				});
			} else {
				navigate(returnTo);
			}
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

	const isDirty = useDirtyTracker(body, seeded) && !saveMutation.isPending;

	if (!isAdd && !idParam) {
		return <Navigate to="/developer/templates" replace />;
	}

	if (!isAdd && detailQ.isLoading) {
		return (
			<div className="mx-auto max-w-screen-lg px-6 py-4">
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading…
				</div>
			</div>
		);
	}

	if (!isAdd && detailQ.error) {
		return (
			<div className="mx-auto max-w-screen-lg px-6 py-4">
				<ErrorPanel error={detailQ.error} />
			</div>
		);
	}

	const set = (patch: Partial<TemplateEditBody>) => setBody((prev) => ({ ...prev, ...patch }));

	const handleSubmit = (event: React.FormEvent) => {
		event.preventDefault();

		if (saveMutation.isPending) {
			return;
		}

		const errors = validateRequired([
			{ field: "id", label: "ID", value: body.id },
			{ field: "name", label: "Name", value: body.name },
		]);
		const sErrors = settingsValidation.validate();

		if (Object.keys(errors).length > 0 || Object.keys(sErrors).length > 0) {
			setFieldErrors(errors);
			setSettingsErrors(sErrors);
			setGeneralError("Please fill in the required fields.");

			return;
		}

		setGeneralError(null);
		setFieldErrors({});
		setSettingsErrors({});
		saveMutation.mutate(body);
	};

	const title = isAdd ? "Add template" : body.name || idParam || "Edit template";

	return (
		<div className="mx-auto max-w-screen-lg px-6 py-4">
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

			{generalError && (
				<div className="mb-3 rounded-md border border-danger/40 bg-danger/5 px-3 py-2 text-[12.5px] text-danger">
					{generalError}
				</div>
			)}

			<form
				onSubmit={handleSubmit}
				className="space-y-4 rounded-xl border border-border bg-surface p-4"
			>
				<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
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
				</div>

				<label className="flex items-center gap-2 text-[12.5px] text-text-2">
					<input
						type="checkbox"
						className="h-4 w-4 accent-accent"
						checked={Boolean(body.routed)}
						onChange={(e) => set({ routed: e.target.checked })}
					/>
					Routed template (template handler can capture URL segments)
				</label>

				<div>
					<div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
						Resources (page content fields)
					</div>
					<ResourceDesigner
						resources={(body.resources ?? []) as unknown as ResourceEntry[]}
						onChange={(next) =>
							set({ resources: next as unknown as TemplateResource[] })
						}
						keyField="id"
						useCase="templates"
						settingsErrors={settingsErrors}
					/>
				</div>

				<FormHooksEditor
					value={(body.hooks as Record<string, unknown>) ?? {}}
					onChange={(next) => set({ hooks: next })}
				/>

				<div className="flex justify-end gap-2 border-t border-border pt-3">
					<Button to="/developer/templates">Cancel</Button>
					<Button
						variant="primary"
						type="submit"
						icon={<Save size={13} />}
						disabled={saveMutation.isPending}
					>
						{saveMutation.isPending
							? "Saving…"
							: isAdd
								? "Create template"
								: "Save template"}
					</Button>
				</div>
			</form>

			<UnsavedChangesGuard isDirty={isDirty} />
		</div>
	);
};

// Small local field primitives kept in-file to avoid scattering more shared UI
// across the codebase until we extract a real form-controls module.

interface TextFieldProps {
	label: string;
	value: string;
	onChange: (next: string) => void;
	hint?: string;
	error?: string;
	disabled?: boolean;
	required?: boolean;
	type?: string;
}

export const TextField = ({
	label,
	value,
	onChange,
	hint,
	error,
	disabled,
	required,
	type = "text",
}: TextFieldProps) => (
	<label className="block">
		<span className="mb-1 block text-[12px] font-medium text-text-2">
			{label}
			{required && <span className="text-danger"> *</span>}
		</span>
		<input
			type={type}
			value={value}
			onChange={(e) => onChange(e.target.value)}
			disabled={disabled}
			className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring disabled:cursor-not-allowed disabled:opacity-60"
		/>
		{hint && <span className="mt-1 block text-[11px] text-text-3">{hint}</span>}
		{error && (
			<span data-field-error className="mt-1 block text-[11.5px] text-danger">
				{error}
			</span>
		)}
	</label>
);

interface SelectFieldProps {
	label: string;
	value: string;
	onChange: (next: string) => void;
	options: Array<{ value: string; label: string }>;
}

export const SelectField = ({ label, value, onChange, options }: SelectFieldProps) => (
	<label className="block">
		<span className="mb-1 block text-[12px] font-medium text-text-2">{label}</span>
		<select
			value={value}
			onChange={(e) => onChange(e.target.value)}
			className="w-full rounded-md border border-border bg-surface px-2.5 py-2 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
		>
			{options.map((o) => (
				<option key={o.value} value={o.value}>
					{o.label}
				</option>
			))}
		</select>
	</label>
);
