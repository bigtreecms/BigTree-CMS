import { Navigate, useParams } from "react-router-dom";

import { FieldGrid } from "@/components/ui/FieldGrid";

import { DataTableSelect } from "@/components/developer/DataTableSelect";
import { DeveloperEditLayout } from "@/components/developer/DeveloperEditLayout";
import { FeedSettingsControl } from "@/components/developer/FeedSettingsControl";
import { ResourceDesigner, type ResourceEntry } from "@/components/developer/ResourceDesigner";
import { useResourceSettingsValidation } from "@/components/developer/field-settings/useResourceSettingsValidation";

import { feedsApi, type FeedEditBody, type FeedSummary } from "@/api/endpoints/feeds";
import type { ModuleFormField } from "@/api/endpoints/modules";

import { useDesignerSubmit } from "@/components/developer/useDesignerSubmit";

import { queryKeys } from "@/lib/queryKeys";
import { developerEditPath } from "@/lib/routes";
import { useResourceEditor } from "@/hooks/useResourceEditor";

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
	const submit = useDesignerSubmit();

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
		editPath: (id) => developerEditPath("feeds", id),
		onError: (err) => submit.onMutationError(err, "Save failed"),
	});

	const settingsValidation = useResourceSettingsValidation(
		(body.fields ?? []) as unknown as ResourceEntry[],
		"feeds"
	);

	if (!isAdd && !idParam) {
		return <Navigate replace to="/developer/feeds" />;
	}

	const feedType = body.type ?? "custom";
	const title = isAdd ? "Add feed" : body.name || idParam || "Edit feed";

	return (
		<DeveloperEditLayout
			detailQuery={detailQ}
			error={submit.error}
			formShellBounded={false}
			isAdd={isAdd}
			isDirty={isDirty}
			listPath="/developer/feeds"
			saving={saving}
			section="Feeds"
			submitLabel={isAdd ? "Create feed" : "Save feed"}
			title={title}
			width="medium"
			onSubmit={submit.buildSubmit({
				required: [
					{ field: "id", label: "ID", value: body.id },
					{ field: "name", label: "Name", value: body.name },
				],
				settingsValidation,
				save: () => save(body),
				saving,
			})}
		>
			<div className="space-y-4">
				<FieldGrid>
					<TextField
						required
						disabled={!isAdd}
						error={submit.fieldErrors.id}
						hint="Becomes the public path under /feeds/{id}/."
						label="ID / route"
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
					<DataTableSelect
						hint="Database table the feed pulls rows from."
						label="Source table"
						value={body.table ?? ""}
						onChange={(v) => set({ table: v })}
					/>
					<SelectField
						label="Type"
						options={FEED_TYPES}
						value={feedType}
						onChange={(v) => set({ type: v })}
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
						settings={asObject(body.settings)}
						table={body.table ?? ""}
						type={feedType}
						onChange={(next) => set({ settings: next })}
					/>
				</div>

				{feedType === "custom" && (
					<div>
						<SectionLabel className="mb-2">Output fields</SectionLabel>
						<ResourceDesigner
							keyField="column"
							resources={(body.fields ?? []) as unknown as ResourceEntry[]}
							settingsErrors={submit.settingsErrors}
							useCase="feeds"
							onChange={(next) =>
								set({ fields: next as unknown as ModuleFormField[] })
							}
						/>
					</div>
				)}
			</div>
		</DeveloperEditLayout>
	);
};
