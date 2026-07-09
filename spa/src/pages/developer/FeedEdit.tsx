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

import { DataTableSelect } from "@/components/developer/DataTableSelect";
import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { FeedSettingsControl } from "@/components/developer/FeedSettingsControl";
import { ResourceDesigner, type ResourceEntry } from "@/components/developer/ResourceDesigner";
import { useResourceSettingsValidation } from "@/components/developer/field-settings/useResourceSettingsValidation";

import { feedsApi, type FeedEditBody, type FeedSummary } from "@/api/endpoints/feeds";
import type { ModuleFormField } from "@/api/endpoints/modules";

import { queryKeys } from "@/lib/queryKeys";
import { useFormSubmit } from "@/hooks/useFormSubmit";
import { useResourceEditor } from "@/hooks/useResourceEditor";
import { validateRequired } from "@/lib/formValidation";

import { SelectField } from "@/components/ui/SelectField";
import { TextField } from "@/components/ui/TextField";
import { SectionLabel } from "@/components/ui/SectionLabel";

const FEED_TYPES = [
	{ value: "custom", label: "Custom" },
	{ value: "rss", label: "RSS 0.91" },
	{ value: "rss2", label: "RSS 2.0" },
];

const asObject = (value: unknown): Record<string, unknown> =>
	value && typeof value === "object" && !Array.isArray(value)
		? (value as Record<string, unknown>)
		: {};

export const FeedEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const { error, setError, fieldErrors, setFieldErrors, onMutationError } = useFormSubmit();
	const [settingsErrors, setSettingsErrors] = useState<Record<number, Record<string, string>>>(
		{}
	);

	const { isAdd, detailQ, body, set, save, saving, isDirty } = useResourceEditor<
		FeedSummary,
		FeedEditBody
	>({
		listPath: "/developer/feeds",
		entityLabel: "Feed",
		queryKey: queryKeys.feeds.detail(idParam as string),
		queryFn: () => feedsApi.get(idParam as string),
		initialBody: {
			id: "",
			name: "",
			description: "",
			table: "",
			type: "rss",
			settings: {},
			fields: [],
		},
		seed: (data) => ({
			id: data.id,
			name: data.name,
			description: data.description,
			table: data.table,
			type: data.type,
			settings: data.settings,
			fields: data.fields,
		}),
		create: (next) => feedsApi.create(next),
		update: (id, next) => feedsApi.update(id, next),
		invalidateKey: queryKeys.feeds.root(),
		editPath: (id) => `/developer/feeds/${encodeURIComponent(id)}/edit`,
		onError: (err) => onMutationError(err, "Save failed"),
	});

	const settingsValidation = useResourceSettingsValidation(
		(body.fields ?? []) as unknown as ResourceEntry[],
		"feeds"
	);

	if (!isAdd && !idParam) {
		return <Navigate to="/developer/feeds" replace />;
	}

	const feedType = body.type ?? "custom";
	const title = isAdd ? "Add feed" : body.name || idParam || "Edit feed";

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
						{ label: "Feeds", to: "/developer/feeds" },
						{ label: isAdd ? "Add" : "Edit" },
					]}
				/>

				<PageHead
					title={title}
					actions={
						<Button icon={<ChevronLeft size={13} />} to="/developer/feeds">
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
							cancelTo="/developer/feeds"
							submitLabel={isAdd ? "Create feed" : "Save feed"}
							loading={saving}
							loadingLabel="Saving…"
						/>
					}
				>
					<div className="space-y-4">
						<FieldGrid>
							<TextField
								label="ID / route"
								value={body.id ?? ""}
								onChange={(v) => set({ id: v })}
								hint="Becomes the public path under /feeds/{id}/."
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
							<DataTableSelect
								label="Source table"
								value={body.table ?? ""}
								onChange={(v) => set({ table: v })}
								hint="Database table the feed pulls rows from."
							/>
							<SelectField
								label="Type"
								value={feedType}
								onChange={(v) => set({ type: v })}
								options={FEED_TYPES}
							/>
						</FieldGrid>

						<TextField
							label="Description"
							value={body.description ?? ""}
							onChange={(v) => set({ description: v })}
						/>

						<div>
							<SectionLabel className="mb-2">Feed settings</SectionLabel>
							<FeedSettingsControl
								type={feedType}
								table={body.table ?? ""}
								settings={asObject(body.settings)}
								onChange={(next) => set({ settings: next })}
							/>
						</div>

						{feedType === "custom" && (
							<div>
								<SectionLabel className="mb-2">Output fields</SectionLabel>
								<ResourceDesigner
									resources={(body.fields ?? []) as unknown as ResourceEntry[]}
									onChange={(next) =>
										set({ fields: next as unknown as ModuleFormField[] })
									}
									keyField="column"
									useCase="feeds"
									settingsErrors={settingsErrors}
								/>
							</div>
						)}
					</div>
				</FormShell>

				<UnsavedChangesGuard isDirty={isDirty} />
			</PageContainer>
		</EditPageGuard>
	);
};
