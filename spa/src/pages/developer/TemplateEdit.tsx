import { useEffect, useState } from "react";
import { Navigate, useNavigate, useParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Alert } from "@/components/ui/Alert";
import { FieldGrid } from "@/components/ui/FieldGrid";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { FormFooter } from "@/components/ui/FormFooter";
import { FormShell } from "@/components/ui/FormShell";
import { SelectField } from "@/components/ui/SelectField";
import { TextField } from "@/components/ui/TextField";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { Loading } from "@/components/ui/Loading";
import { SectionLabel } from "@/components/ui/SectionLabel";
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

import { toast } from "@/lib/toast";
import { queryKeys } from "@/lib/queryKeys";
import { useFormSubmit } from "@/hooks/useFormSubmit";
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
		queryKey: queryKeys.templates.detail(idParam as string),
		queryFn: () => templatesApi.get(idParam as string),
		enabled: !isAdd,
	});

	const [body, setBody] = useState<TemplateEditBody>(() =>
		isAdd
			? { id: "", name: "", module: "", level: 0, routed: false, resources: [], hooks: {} }
			: {}
	);
	const { error, setError, fieldErrors, setFieldErrors, onMutationError } = useFormSubmit();
	const [settingsErrors, setSettingsErrors] = useState<Record<number, Record<string, string>>>(
		{}
	);
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
			queryClient.invalidateQueries({ queryKey: queryKeys.templates.root() });
			toast.success(isAdd ? "Template created" : "Template saved");

			if (isAdd) {
				navigate(`/developer/templates/${encodeURIComponent(fresh.id)}/edit`, {
					replace: true,
				});
			} else {
				navigate(returnTo);
			}
		},
		onError: (err) => onMutationError(err, "Save failed"),
	});

	const isDirty = useDirtyTracker(body, seeded) && !saveMutation.isPending;

	if (!isAdd && !idParam) {
		return <Navigate to="/developer/templates" replace />;
	}

	if (!isAdd && detailQ.isLoading) {
		return (
			<PageContainer width="medium">
				<Loading variant="card" />
			</PageContainer>
		);
	}

	if (!isAdd && detailQ.error) {
		return (
			<PageContainer width="medium">
				<ErrorPanel error={detailQ.error} />
			</PageContainer>
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
			setError("Please fill in the required fields.");

			return;
		}

		setError(null);
		setFieldErrors({});
		setSettingsErrors({});
		saveMutation.mutate(body);
	};

	const title = isAdd ? "Add template" : body.name || idParam || "Edit template";

	return (
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

			{error && (
				<Alert tone="danger" className="mb-3">
					{error}
				</Alert>
			)}

			<FormShell
				bounded={false}
				onSubmit={handleSubmit}
				footer={
					<FormFooter
						cancelTo="/developer/templates"
						submitLabel={isAdd ? "Create template" : "Save template"}
						loading={saveMutation.isPending}
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
							settingsErrors={settingsErrors}
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
	);
};
