import { useEffect, useState } from "react";
import { Navigate, useNavigate, useParams } from "react-router-dom";
import { useMutation, useQueries, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft, Save, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { Combobox, type ComboboxOption } from "@/components/ui/Combobox";
import { Button } from "@/components/ui/Button";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import { calloutsApi, type CalloutGroupEditBody } from "@/api/endpoints/callouts";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { useReturnTo } from "@/hooks/useReturnTo";
import { validateRequired } from "@/lib/formValidation";

import { TextField } from "./TemplateEdit";

export const CalloutGroupEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const isAdd = !idParam;
	const navigate = useNavigate();
	const returnTo = useReturnTo("/developer/callout-groups");
	const queryClient = useQueryClient();

	const [groupQ, calloutsQ] = useQueries({
		queries: [
			{
				queryKey: ["callout-groups", "detail", idParam],
				queryFn: () => calloutsApi.getGroup(idParam as string),
				enabled: !isAdd,
			},
			{
				queryKey: ["callouts", "list"],
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
			queryClient.invalidateQueries({ queryKey: ["callout-groups"] });
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
			<div className="mx-auto max-w-screen-md px-6 py-4">
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading…
				</div>
			</div>
		);
	}

	if (!isAdd && groupQ.error) {
		return (
			<div className="mx-auto max-w-screen-md px-6 py-4">
				<ErrorPanel error={groupQ.error} />
			</div>
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
		<div className="mx-auto max-w-screen-md px-6 py-4">
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
					saveMutation.mutate(body);
				}}
				className="space-y-4 rounded-xl border border-border bg-surface p-4"
			>
				<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
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
				</div>

				<div>
					<div className="mb-2 flex items-center justify-between gap-2">
						<div className="text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
							Callouts in this group
						</div>
						<span className="text-[11.5px] tabular-nums text-text-3">
							{selectedIds.length === 1
								? "1 callout"
								: `${selectedIds.length} callouts`}
						</span>
					</div>

					{calloutsQ.isLoading ? (
						<div className="text-[12.5px] text-text-3">Loading callouts…</div>
					) : callouts.length === 0 ? (
						<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-3 text-[12.5px] text-text-3">
							No callouts to pick from yet. Create one first.
						</div>
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
													<div className="truncate font-mono text-[11px] text-text-3">
														{id}
													</div>
												</span>
												<button
													type="button"
													className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
													onClick={() => removeCallout(id)}
													title="Remove from group"
													aria-label={`Remove ${callout?.name ?? id} from group`}
												>
													<Trash size={13} />
												</button>
											</li>
										);
									})}
								</ul>
							) : (
								<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-3 text-[12.5px] text-text-3">
									No callouts in this group yet — add one below.
								</div>
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

				<div className="flex justify-end gap-2 border-t border-border pt-3">
					<Button to="/developer/callout-groups">Cancel</Button>
					<Button
						variant="primary"
						type="submit"
						icon={<Save size={13} />}
						disabled={saveMutation.isPending}
					>
						{saveMutation.isPending ? "Saving…" : isAdd ? "Create group" : "Save group"}
					</Button>
				</div>
			</form>

			<UnsavedChangesGuard isDirty={isDirty} />
		</div>
	);
};
