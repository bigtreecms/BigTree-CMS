import { Navigate, useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Trash } from "lucide-react";

import { FieldGrid } from "@/components/ui/FieldGrid";
import { MonoText } from "@/components/ui/MonoText";
import { Combobox, type ComboboxOption } from "@/components/ui/Combobox";

import { DeveloperEditLayout } from "@/components/developer/DeveloperEditLayout";

import {
	calloutsApi,
	type CalloutGroup,
	type CalloutGroupEditBody,
} from "@/api/endpoints/callouts";

import { useFormSubmit } from "@/hooks/useFormSubmit";
import { useResourceEditor } from "@/hooks/useResourceEditor";
import { validateRequired } from "@/lib/formValidation";
import { queryKeys } from "@/lib/queryKeys";
import { developerEditPath } from "@/lib/routes";

import { TextField } from "@/components/ui/TextField";
import { Loading } from "@/components/ui/Loading";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { IconButton } from "@/components/ui/IconButton";
import { SectionLabel } from "@/components/ui/SectionLabel";

export const CalloutGroupEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const { error, fieldErrors, handleSubmit, onMutationError } = useFormSubmit();

	const { isAdd, detailQ, body, set, save, saving, isDirty } = useResourceEditor<
		CalloutGroup,
		CalloutGroupEditBody
	>({
		listPath: "/developer/callout-groups",
		entityLabel: "Group",
		queryKey: queryKeys.calloutGroups.detail(idParam as string),
		queryFn: () => calloutsApi.getGroup(idParam as string),
		initialBody: { id: "", name: "", callouts: [] },
		seed: (data) => ({
			id: data.id,
			name: data.name,
			callouts: data.callouts ?? [],
		}),
		create: (next) => calloutsApi.createGroup(next),
		update: (id, next) => calloutsApi.updateGroup(id, next),
		invalidateKey: queryKeys.calloutGroups.root(),
		editPath: (id) => developerEditPath("callout-groups", id),
		onError: (err) => onMutationError(err, "Save failed"),
	});

	const calloutsQ = useQuery({
		queryKey: queryKeys.callouts.list(),
		queryFn: () => calloutsApi.list(),
	});

	if (!isAdd && !idParam) {
		return <Navigate replace to="/developer/callout-groups" />;
	}

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
		<DeveloperEditLayout
			detailQuery={detailQ}
			error={error}
			isAdd={isAdd}
			isDirty={isDirty}
			listPath="/developer/callout-groups"
			saving={saving}
			section="Callout groups"
			submitLabel={isAdd ? "Create group" : "Save group"}
			title={title}
			width="narrow"
			onSubmit={(e) =>
				handleSubmit(
					e,
					() =>
						validateRequired([
							{ field: "id", label: "ID", value: body.id },
							{ field: "name", label: "Name", value: body.name },
						]),
					() => save(body)
				)
			}
		>
			<div className="space-y-4">
				<FieldGrid>
					<TextField
						required
						disabled={!isAdd}
						error={fieldErrors.id}
						hint="Lowercase, hyphens or underscores."
						label="ID"
						value={body.id ?? ""}
						onChange={(v) => set({ id: v })}
					/>
					<TextField
						required
						error={fieldErrors.name}
						label="Name"
						value={body.name ?? ""}
						onChange={(v) => set({ name: v })}
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
												className="flex items-center gap-3 px-3 py-2 text-[12.5px]"
												key={id}
											>
												<span className="min-w-0 flex-1">
													<div className="truncate text-text-2">
														{callout?.name ?? id}
													</div>
													<MonoText as="div">{id}</MonoText>
												</span>
												<IconButton
													label={`Remove ${callout?.name ?? id} from group`}
													title="Remove from group"
													tone="danger"
													onClick={() => removeCallout(id)}
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
								ariaLabel="Add a callout to this group"
								clearable={false}
								emptyLabel={
									addOptions.length === 0
										? "All callouts are in this group."
										: "No callouts match."
								}
								options={addOptions}
								placeholder="Add a callout…"
								searchPlaceholder="Search callouts…"
								value={null}
								onChange={(option) => {
									if (option) {
										addCallout(option.value);
									}
								}}
							/>
						</div>
					)}
				</div>
			</div>
		</DeveloperEditLayout>
	);
};
