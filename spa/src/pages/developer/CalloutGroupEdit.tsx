import { useEffect, useState } from "react";
import { Navigate, useNavigate, useParams } from "react-router-dom";
import { useMutation, useQueries, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Alert } from "@/components/ui/Alert";
import { FieldGrid } from "@/components/ui/FieldGrid";
import { MonoText } from "@/components/ui/MonoText";
import { Combobox, type ComboboxOption } from "@/components/ui/Combobox";
import { Button } from "@/components/ui/Button";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { FormFooter } from "@/components/ui/FormFooter";
import { FormShell } from "@/components/ui/FormShell";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import { calloutsApi, type CalloutGroupEditBody } from "@/api/endpoints/callouts";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { useReturnTo } from "@/hooks/useReturnTo";
import { validateRequired } from "@/lib/formValidation";
import { queryKeys } from "@/lib/queryKeys";

import { TextField } from "@/components/ui/TextField";
import { Loading } from "@/components/ui/Loading";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { IconButton } from "@/components/ui/IconButton";
import { SectionLabel } from "@/components/ui/SectionLabel";

export const CalloutGroupEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const isAdd = !idParam;
	const navigate = useNavigate();
	const returnTo = useReturnTo("/developer/callout-groups");
	const queryClient = useQueryClient();

	const [groupQ, calloutsQ] = useQueries({
		queries: [
			{
				queryKey: queryKeys.calloutGroups.detail(idParam as string),
				queryFn: () => calloutsApi.getGroup(idParam as string),
				enabled: !isAdd,
			},
			{
				queryKey: queryKeys.callouts.list(),
				queryFn: () => calloutsApi.list(),
			},
		],
	});

	const [body, setBody] = useState<CalloutGroupEditBody>(() =>
		isAdd ? { id: "", name: "", callouts: [] } : {}
	);
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

	useScrollToFirstError(fieldErrors);
	const [generalError, setGeneralError] = useState<string | null>(null);
	const [seeded, setSeeded] = useState(isAdd);

	useEffect(() => {
		if (!isAdd && groupQ.data) {
			setBody({
				id: groupQ.data.id,
				name: groupQ.data.name,
				callouts: groupQ.data.callouts ?? [],
			});
			setSeeded(true);
		}
	}, [isAdd, groupQ.data]);

	const saveMutation = useMutation({
		mutationFn: (next: CalloutGroupEditBody) =>
			isAdd
				? calloutsApi.createGroup(next)
				: calloutsApi.updateGroup(idParam as string, next),
		onSuccess: (fresh) => {
			queryClient.invalidateQueries({ queryKey: queryKeys.calloutGroups.root() });
			toast.success(isAdd ? "Group created" : "Group saved");

			if (isAdd) {
				navigate(`/developer/callout-groups/${encodeURIComponent(fresh.id)}/edit`, {
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
		return <Navigate to="/developer/callout-groups" replace />;
	}

	if (!isAdd && groupQ.isLoading) {
		return (
			<PageContainer width="narrow">
				<Loading variant="card" />
			</PageContainer>
		);
	}

	if (!isAdd && groupQ.error) {
		return (
			<PageContainer width="narrow">
				<ErrorPanel error={groupQ.error} />
			</PageContainer>
		);
	}

	const set = (patch: Partial<CalloutGroupEditBody>) =>
		setBody((prev) => ({ ...prev, ...patch }));

	const callouts = calloutsQ.data ?? [];
	const selectedIds = body.callouts ?? [];
	const selectedSet = new Set(selectedIds);
	const calloutById = new Map(callouts.map((c) => [c.id, c]));

	const addCallout = (id: string) => {
		if (selectedSet.has(id)) {
			return;
		}

		set({ callouts: [...selectedIds, id] });
	};

	const removeCallout = (id: string) => {
		set({ callouts: selectedIds.filter((c) => c !== id) });
	};

	const addOptions: ComboboxOption<string>[] = callouts
		.filter((c) => !selectedSet.has(c.id))
		.map((c) => ({ value: c.id, label: c.name, sublabel: c.id }));

	const title = isAdd ? "Add callout group" : body.name || idParam || "Edit callout group";

	return (
		<PageContainer width="narrow">
			<Breadcrumb
				items={[
					{ label: "Developer", to: "/developer" },
					{ label: "Callout groups", to: "/developer/callout-groups" },
					{ label: isAdd ? "Add" : "Edit" },
				]}
			/>

			<PageHead
				title={title}
				actions={
					<Button icon={<ChevronLeft size={13} />} to="/developer/callout-groups">
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
					saveMutation.mutate(body);
				}}
				footer={
					<FormFooter
						cancelTo="/developer/callout-groups"
						submitLabel={isAdd ? "Create group" : "Save group"}
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
							hint="Lowercase, hyphens or underscores."
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

					<div>
						<div className="mb-2 flex items-center justify-between gap-2">
							<SectionLabel>Callouts in this group</SectionLabel>
							<span className="text-[11.5px] tabular-nums text-text-3">
								{selectedIds.length === 1
									? "1 callout"
									: `${selectedIds.length} callouts`}
							</span>
						</div>

						{calloutsQ.isLoading ? (
							<Loading label="Loading callouts…" />
						) : callouts.length === 0 ? (
							<InlineEmpty pad="md">
								No callouts to pick from yet. Create one first.
							</InlineEmpty>
						) : (
							<div className="space-y-2">
								{selectedIds.length > 0 ? (
									<ul className="divide-y divide-border overflow-hidden rounded-md border border-border">
										{selectedIds.map((id) => {
											const callout = calloutById.get(id);

											return (
												<li
													key={id}
													className="flex items-center gap-3 px-3 py-2 text-[12.5px]"
												>
													<span className="min-w-0 flex-1">
														<div className="truncate text-text-2">
															{callout?.name ?? id}
														</div>
														<MonoText as="div">{id}</MonoText>
													</span>
													<IconButton
														tone="danger"
														onClick={() => removeCallout(id)}
														title="Remove from group"
														label={`Remove ${callout?.name ?? id} from group`}
													>
														<Trash size={13} />
													</IconButton>
												</li>
											);
										})}
									</ul>
								) : (
									<InlineEmpty pad="md">
										No callouts in this group yet — add one below.
									</InlineEmpty>
								)}

								<Combobox<string>
									value={null}
									onChange={(option) => {
										if (option) {
											addCallout(option.value);
										}
									}}
									options={addOptions}
									placeholder="Add a callout…"
									searchPlaceholder="Search callouts…"
									emptyLabel={
										addOptions.length === 0
											? "All callouts are in this group."
											: "No callouts match."
									}
									clearable={false}
									ariaLabel="Add a callout to this group"
								/>
							</div>
						)}
					</div>
				</div>
			</FormShell>

			<UnsavedChangesGuard isDirty={isDirty} />
		</PageContainer>
	);
};
