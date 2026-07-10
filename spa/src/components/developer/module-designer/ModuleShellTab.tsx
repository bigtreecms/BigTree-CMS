import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useToastMutation } from "@/hooks/useToastMutation";
import { Save } from "lucide-react";

import { Alert } from "@/components/ui/Alert";
import { FieldGrid } from "@/components/ui/FieldGrid";
import { Button } from "@/components/ui/Button";
import {
	modulesApi,
	type ModuleCreateBody,
	type ModuleGbpConfig,
	type ModuleSummary,
} from "@/api/endpoints/modules";

import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";
import { useInlineForm } from "@/hooks/useInlineForm";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { Card } from "@/components/ui/Card";
import { applyApiFieldErrors } from "@/lib/errorHandling";
import { toast } from "@/lib/toast";
import { validateRequired } from "@/lib/formValidation";
import { queryKeys } from "@/lib/queryKeys";
import { moduleDetailPath } from "@/lib/routes";

import { DataTableSelect } from "@/components/developer/DataTableSelect";
import { IconPicker } from "@/components/developer/IconPicker";

import { CheckboxInput, SelectInput, TextInput } from "./inputs";

/** Sentinel option value that switches the Group select into "create new" mode. */
const NEW_GROUP_OPTION = "__create_new_group__";

interface ModuleShellTabProps {
	/** null in add mode. */
	moduleId: string | null;
	module: ModuleSummary | null;
}

type ShellState = {
	name: string;
	group: string;
	route: string;
	icon: string;
	class: string;
	table: string;
	gbp: ModuleGbpConfig;
};

const emptyState = (): ShellState => ({
	name: "",
	group: "",
	route: "",
	icon: "",
	class: "",
	table: "",
	gbp: { enabled: false },
});

const fromModule = (m: ModuleSummary): ShellState => ({
	name: m.name ?? "",
	group: m.group ?? "",
	route: m.route ?? "",
	icon: m.icon ?? "",
	class: m.class ?? "",
	table: m.table ?? "",
	gbp: m.gbp ?? { enabled: false },
});

const toBody = (s: ShellState): ModuleCreateBody => ({
	name: s.name,
	group: s.group || null,
	route: s.route || undefined,
	icon: s.icon || undefined,
	class: s.class || undefined,
	table: s.table || undefined,
	gbp: s.gbp.enabled
		? {
				enabled: true,
				other_table: s.gbp.other_table || "",
				title_field: s.gbp.title_field || "",
				name: s.gbp.name || "",
				item_parser: s.gbp.item_parser || "",
			}
		: { enabled: false },
});

export const ModuleShellTab = ({ moduleId, module }: ModuleShellTabProps) => {
	const isAdd = !moduleId;
	const navigate = useNavigate();
	const queryClient = useQueryClient();

	const [state, setState] = useState<ShellState>(() =>
		module ? fromModule(module) : emptyState()
	);
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

	useScrollToFirstError(fieldErrors);
	const [generalError, setGeneralError] = useState<string | null>(null);
	const newGroup = useInlineForm();
	const [newGroupName, setNewGroupName] = useState("");

	useEffect(() => {
		if (module) {
			setState(fromModule(module));
		}
	}, [module]);

	const groupsQ = useQuery({
		queryKey: queryKeys.moduleGroups.list(),
		queryFn: () => modulesApi.listGroups(),
	});

	const createGroupMutation = useToastMutation({
		mutationFn: (name: string) => modulesApi.createGroup({ name }),
		invalidate: [queryKeys.moduleGroups.root()],
		errorMessage: "Could not create group",
		onSuccess: (group) => {
			setState((prev) => ({ ...prev, group: group.id }));
			newGroup.hide();
			setNewGroupName("");
		},
	});

	const onGroupChange = (value: string) => {
		if (value === NEW_GROUP_OPTION) {
			newGroup.show();

			return;
		}

		newGroup.hide();
		setState((prev) => ({ ...prev, group: value }));
	};

	const saveMutation = useMutation({
		mutationFn: (s: ShellState) =>
			isAdd ? modulesApi.create(toBody(s)) : modulesApi.update(moduleId as string, toBody(s)),
		onSuccess: (fresh) => {
			queryClient.invalidateQueries({ queryKey: queryKeys.modules.root() });
			toast.success(isAdd ? "Module created" : "Module saved");

			if (isAdd) {
				navigate(moduleDetailPath(fresh.id), { replace: true });
			}
		},
		onError: (err) => {
			applyApiFieldErrors(err, {
				setFieldErrors,
				setError: setGeneralError,
				fallback: "Save failed",
			});
		},
	});

	const set = (patch: Partial<ShellState>) => setState((prev) => ({ ...prev, ...patch }));
	const setGbp = (patch: Partial<ModuleGbpConfig>) =>
		setState((prev) => ({ ...prev, gbp: { ...prev.gbp, ...patch } }));

	// The parent only mounts this tab once the module has loaded, so `state` is
	// already seeded on first render — no separate "ready" gate needed here.
	const isDirty = useDirtyTracker(state) && !saveMutation.isPending;

	const handleSubmit = (event: React.FormEvent) => {
		event.preventDefault();

		if (saveMutation.isPending) {
			return;
		}

		const errors = validateRequired([{ field: "name", label: "Name", value: state.name }]);

		if (Object.keys(errors).length > 0) {
			setFieldErrors(errors);
			setGeneralError("Please fill in the required fields.");

			return;
		}

		setGeneralError(null);
		setFieldErrors({});
		saveMutation.mutate(state);
	};

	const groupOptions = [
		{ value: "", label: "— No group —" },
		...(groupsQ.data ?? []).map((g) => ({ value: g.id, label: g.name })),
		{ value: NEW_GROUP_OPTION, label: "+ Create new group…" },
	];

	return (
		<>
			<form onSubmit={handleSubmit} className="space-y-4">
				{generalError && <Alert tone="danger">{generalError}</Alert>}

				<Card className="space-y-4 p-4">
					<FieldGrid>
						<TextInput
							label="Name"
							value={state.name}
							onChange={(v) => set({ name: v })}
							error={fieldErrors.name}
							required
						/>
						<SelectInput
							label="Group"
							value={newGroup.open ? NEW_GROUP_OPTION : state.group}
							onChange={onGroupChange}
							options={groupOptions}
						/>
						<TextInput
							label="Route"
							value={state.route}
							onChange={(v) => set({ route: v })}
							hint="URL slug. Auto-generated from the name when left blank."
							error={fieldErrors.route}
							mono
						/>
						<DataTableSelect
							label="Data table"
							value={state.table}
							onChange={(v) => set({ table: v })}
							hint="MySQL table backing this module's entries."
						/>
						<TextInput
							label="Handler class"
							value={state.class}
							onChange={(v) => set({ class: v })}
							hint="Optional custom module class."
							mono
						/>
					</FieldGrid>
					{newGroup.open && (
						<div className="flex flex-wrap items-end gap-2 rounded-md border border-border bg-surface-2 p-3">
							<div className="min-w-[200px] flex-1">
								<TextInput
									label="New group name"
									value={newGroupName}
									onChange={setNewGroupName}
								/>
							</div>
							<Button
								variant="primary"
								disabled={!newGroupName.trim()}
								onClick={() => createGroupMutation.mutate(newGroupName.trim())}
								loading={createGroupMutation.isPending}
								loadingLabel="Creating…"
							>
								Create group
							</Button>
							<Button
								variant="secondary"
								onClick={() => {
									newGroup.hide();
									setNewGroupName("");
								}}
							>
								Cancel
							</Button>
						</div>
					)}

					<IconPicker
						value={state.icon}
						onChange={(v) => set({ icon: v })}
						hint="Shown beside the module in the admin navigation."
					/>
				</Card>

				<Card className="space-y-3 p-4">
					<CheckboxInput
						label="Group-based permissions (per-category access)"
						checked={Boolean(state.gbp.enabled)}
						onChange={(v) => setGbp({ enabled: v })}
					/>
					{state.gbp.enabled && (
						<FieldGrid>
							<DataTableSelect
								label="Category table"
								value={state.gbp.other_table ?? ""}
								onChange={(v) => setGbp({ other_table: v })}
								hint="Table whose rows act as permission categories."
							/>
							<TextInput
								label="Title field"
								value={state.gbp.title_field ?? ""}
								onChange={(v) => setGbp({ title_field: v })}
								hint="Column on the category table used as its label."
								mono
							/>
							<TextInput
								label="Permission group name"
								value={state.gbp.name ?? ""}
								onChange={(v) => setGbp({ name: v })}
								hint="Shown in the user permission tree."
							/>
							<TextInput
								label="Item parser"
								value={state.gbp.item_parser ?? ""}
								onChange={(v) => setGbp({ item_parser: v })}
								hint="Optional PHP parser for category labels."
								mono
							/>
						</FieldGrid>
					)}
				</Card>

				<div className="flex justify-end">
					<Button
						variant="primary"
						type="submit"
						icon={<Save size={13} />}
						loading={saveMutation.isPending}
						loadingLabel="Saving…"
					>
						{isAdd ? "Create module" : "Save module"}
					</Button>
				</div>
			</form>

			<UnsavedChangesGuard isDirty={isDirty} />
		</>
	);
};
