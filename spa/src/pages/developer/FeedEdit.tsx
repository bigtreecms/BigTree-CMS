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

import { DataTableSelect } from "@/components/developer/DataTableSelect";
import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { FeedSettingsControl } from "@/components/developer/FeedSettingsControl";
import { ResourceDesigner, type ResourceEntry } from "@/components/developer/ResourceDesigner";
import { useResourceSettingsValidation } from "@/components/developer/field-settings/useResourceSettingsValidation";

import { feedsApi, type FeedEditBody } from "@/api/endpoints/feeds";
import type { ModuleFormField } from "@/api/endpoints/modules";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { useReturnTo } from "@/hooks/useReturnTo";
import { validateRequired } from "@/lib/formValidation";

import { SelectField } from "@/components/ui/SelectField";
import { TextField } from "@/components/ui/TextField";
import { EmptyState } from "@/components/ui/EmptyState";

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
	const isAdd = !idParam;
	const navigate = useNavigate();
	const returnTo = useReturnTo("/developer/feeds");
	const queryClient = useQueryClient();

	const detailQ = useQuery({
		queryKey: ["feeds", "detail", idParam],
		queryFn: () => feedsApi.get(idParam as string),
		enabled: !isAdd,
	});

	const [body, setBody] = useState<FeedEditBody>(() =>
		isAdd
			? {
					id: "",
					name: "",
					description: "",
					table: "",
					type: "rss",
					settings: {},
					fields: [],
				}
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
		(body.fields ?? []) as unknown as ResourceEntry[],
		"feeds"
	);

	useEffect(() => {
		if (!isAdd && detailQ.data) {
			const next: FeedEditBody = {
				id: detailQ.data.id,
				name: detailQ.data.name,
				description: detailQ.data.description,
				table: detailQ.data.table,
				type: detailQ.data.type,
				settings: detailQ.data.settings,
				fields: detailQ.data.fields,
			};
			setBody(next);
			setSeeded(true);
		}
	}, [isAdd, detailQ.data]);

	const saveMutation = useMutation({
		mutationFn: (next: FeedEditBody) =>
			isAdd ? feedsApi.create(next) : feedsApi.update(idParam as string, next),
		onSuccess: (fresh) => {
			queryClient.invalidateQueries({ queryKey: ["feeds"] });
			toast.success(isAdd ? "Feed created" : "Feed saved");

			if (isAdd) {
				navigate(`/developer/feeds/${encodeURIComponent(fresh.id)}/edit`, {
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
		return <Navigate to="/developer/feeds" replace />;
	}

	if (!isAdd && detailQ.isLoading) {
		return (
			<div className="mx-auto max-w-5xl px-6 py-4">
				<EmptyState>Loading…</EmptyState>
			</div>
		);
	}

	if (!isAdd && detailQ.error) {
		return (
			<div className="mx-auto max-w-5xl px-6 py-4">
				<ErrorPanel error={detailQ.error} />
			</div>
		);
	}

	const set = (patch: Partial<FeedEditBody>) => setBody((prev) => ({ ...prev, ...patch }));

	const feedType = body.type ?? "custom";
	const title = isAdd ? "Add feed" : body.name || idParam || "Edit feed";

	return (
		<div className="mx-auto max-w-5xl px-6 py-4">
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
					const sErrors = settingsValidation.validate();

					if (Object.keys(errors).length > 0 || Object.keys(sErrors).length > 0) {
						setFieldErrors(errors);
						setSettingsErrors(sErrors);
						setGeneralError("Please fill in the required fields.");

						return;
					}

					setFieldErrors({});
					setSettingsErrors({});
					setGeneralError(null);
					saveMutation.mutate(body);
				}}
				className="space-y-4 rounded-xl border border-border bg-surface p-4"
			>
				<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
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
				</div>

				<TextField
					label="Description"
					value={body.description ?? ""}
					onChange={(v) => set({ description: v })}
				/>

				<div>
					<div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
						Feed settings
					</div>
					<FeedSettingsControl
						type={feedType}
						table={body.table ?? ""}
						settings={asObject(body.settings)}
						onChange={(next) => set({ settings: next })}
					/>
				</div>

				{feedType === "custom" && (
					<div>
						<div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
							Output fields
						</div>
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

				<div className="flex justify-end gap-2 border-t border-border pt-3">
					<Button to="/developer/feeds">Cancel</Button>
					<Button
						variant="primary"
						type="submit"
						icon={<Save size={13} />}
						disabled={saveMutation.isPending}
					>
						{saveMutation.isPending ? "Saving…" : isAdd ? "Create feed" : "Save feed"}
					</Button>
				</div>
			</form>

			<UnsavedChangesGuard isDirty={isDirty} />
		</div>
	);
};
