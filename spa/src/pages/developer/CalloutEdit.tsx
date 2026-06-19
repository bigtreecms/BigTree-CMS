import { useEffect, useState } from "react";
import { Link, Navigate, useNavigate, useParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft, Save } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { HeaderBtn } from "@/components/ui/HeaderBtn";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { ResourceDesigner, type ResourceEntry } from "@/components/developer/ResourceDesigner";
import { useResourceSettingsValidation } from "@/components/developer/field-settings/useResourceSettingsValidation";

import { calloutsApi, type CalloutEditBody } from "@/api/endpoints/callouts";
import type { TemplateResource } from "@/api/endpoints/templates";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { useReturnTo } from "@/hooks/useReturnTo";
import { validateRequired } from "@/lib/formValidation";

import { SelectField, TextField } from "./TemplateEdit";

export const CalloutEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const isAdd = !idParam;
	const navigate = useNavigate();
	const returnTo = useReturnTo("/developer/callouts");
	const queryClient = useQueryClient();

	const detailQ = useQuery({
		queryKey: ["callouts", "detail", idParam],
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
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

	useScrollToFirstError(fieldErrors);
	const [settingsErrors, setSettingsErrors] = useState<Record<number, Record<string, string>>>(
		{}
	);
	const [generalError, setGeneralError] = useState<string | null>(null);
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
			queryClient.invalidateQueries({ queryKey: ["callouts"] });
			toast.success(isAdd ? "Callout created" : "Callout saved");

			if (isAdd) {
				navigate(`/developer/callouts/${encodeURIComponent(fresh.id)}/edit`, {
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
		return <Navigate to="/developer/callouts" replace />;
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

	const set = (patch: Partial<CalloutEditBody>) => setBody((prev) => ({ ...prev, ...patch }));

	const title = isAdd ? "Add callout" : body.name || idParam || "Edit callout";

	return (
		<div className="mx-auto max-w-screen-lg px-6 py-4">
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
					<HeaderBtn icon={<ChevronLeft size={13} />} to="/developer/callouts">
						Back
					</HeaderBtn>
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
				</div>

				<TextField
					label="Description"
					value={body.description ?? ""}
					onChange={(v) => set({ description: v })}
				/>

				<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
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
				</div>

				<div>
					<div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
						Fields
					</div>
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

				<div className="flex justify-end gap-2 border-t border-border pt-3">
					<Link
						to="/developer/callouts"
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] text-text-2 hover:bg-hover"
					>
						Cancel
					</Link>
					<button
						type="submit"
						className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-50 hover:bg-accent-hover"
						disabled={saveMutation.isPending}
					>
						<Save size={13} />
						{saveMutation.isPending
							? "Saving…"
							: isAdd
								? "Create callout"
								: "Save callout"}
					</button>
				</div>
			</form>

			<UnsavedChangesGuard isDirty={isDirty} />
		</div>
	);
};
