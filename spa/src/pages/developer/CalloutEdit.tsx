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
import { FormFooter } from "@/components/ui/FormFooter";
import { FormShell } from "@/components/ui/FormShell";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { ResourceDesigner, type ResourceEntry } from "@/components/developer/ResourceDesigner";
import { useResourceSettingsValidation } from "@/components/developer/field-settings/useResourceSettingsValidation";

import { calloutsApi, type CalloutEditBody } from "@/api/endpoints/callouts";
import type { TemplateResource } from "@/api/endpoints/templates";

import { toast } from "@/lib/toast";
import { queryKeys } from "@/lib/queryKeys";
import { useFormSubmit } from "@/hooks/useFormSubmit";
import { useReturnTo } from "@/hooks/useReturnTo";
import { validateRequired } from "@/lib/formValidation";

import { SelectField } from "@/components/ui/SelectField";
import { TextField } from "@/components/ui/TextField";
import { Loading } from "@/components/ui/Loading";
import { SectionLabel } from "@/components/ui/SectionLabel";

export const CalloutEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const isAdd = !idParam;
	const navigate = useNavigate();
	const returnTo = useReturnTo("/developer/callouts");
	const queryClient = useQueryClient();

	const detailQ = useQuery({
		queryKey: queryKeys.callouts.detail(idParam as string),
		queryFn: () => calloutsApi.get(idParam as string),
		enabled: !isAdd,
	});

	const [body, setBody] = useState<CalloutEditBody>(() =>
		isAdd
			? {
					id: "",
					name: "",
					description: "",
					level: 0,
					display_field: "",
					display_default: "",
					resources: [],
				}
			: {}
	);
	const { error, setError, fieldErrors, setFieldErrors, onMutationError } = useFormSubmit();
	const [settingsErrors, setSettingsErrors] = useState<Record<number, Record<string, string>>>(
		{}
	);
	const [seeded, setSeeded] = useState(isAdd);

	const settingsValidation = useResourceSettingsValidation(
		(body.resources ?? []) as unknown as ResourceEntry[],
		"callouts"
	);

	useEffect(() => {
		if (!isAdd && detailQ.data) {
			setBody({
				id: detailQ.data.id,
				name: detailQ.data.name,
				description: detailQ.data.description,
				level: detailQ.data.level,
				display_field: detailQ.data.display_field,
				display_default: detailQ.data.display_default,
				resources: detailQ.data.resources,
			});
			setSeeded(true);
		}
	}, [isAdd, detailQ.data]);

	const saveMutation = useMutation({
		mutationFn: (next: CalloutEditBody) =>
			isAdd ? calloutsApi.create(next) : calloutsApi.update(idParam as string, next),
		onSuccess: (fresh) => {
			queryClient.invalidateQueries({ queryKey: queryKeys.callouts.root() });
			toast.success(isAdd ? "Callout created" : "Callout saved");

			if (isAdd) {
				navigate(`/developer/callouts/${encodeURIComponent(fresh.id)}/edit`, {
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
		return <Navigate to="/developer/callouts" replace />;
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

	const set = (patch: Partial<CalloutEditBody>) => setBody((prev) => ({ ...prev, ...patch }));

	const title = isAdd ? "Add callout" : body.name || idParam || "Edit callout";

	return (
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
					saveMutation.mutate(body);
				}}
				footer={
					<FormFooter
						cancelTo="/developer/callouts"
						submitLabel={isAdd ? "Create callout" : "Save callout"}
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
	);
};
