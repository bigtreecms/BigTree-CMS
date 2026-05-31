import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Save } from "lucide-react";

import {
	modulesApi,
	type ModuleCreateBody,
	type ModuleGbpConfig,
	type ModuleSummary,
} from "@/api/endpoints/modules";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

import { DataTableSelect } from "@/components/developer/DataTableSelect";

import { CheckboxInput, SelectInput, TextInput } from "./inputs";

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
	graphql: boolean;
	graphql_type: string;
	gbp: ModuleGbpConfig;
};

const emptyState = (): ShellState => ({
	name: "",
	group: "",
	route: "",
	icon: "",
	class: "",
	table: "",
	graphql: false,
	graphql_type: "",
	gbp: { enabled: false },
});

const fromModule = (m: ModuleSummary): ShellState => ({
	name: m.name ?? "",
	group: m.group ?? "",
	route: m.route ?? "",
	icon: m.icon ?? "",
	class: m.class ?? "",
	table: m.table ?? "",
	graphql: Boolean(m.graphql),
	graphql_type: m.graphql_type ?? "",
	gbp: m.gbp ?? { enabled: false },
});

export const ModuleShellTab = ({ moduleId, module }: ModuleShellTabProps) => {
	const isAdd = !moduleId;
	const navigate = useNavigate();
	const queryClient = useQueryClient();

	const [state, setState] = useState<ShellState>(() =>
		module ? fromModule(module) : emptyState()
	);
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
	const [generalError, setGeneralError] = useState<string | null>(null);

	useEffect(() => {
		if (module) {
			setState(fromModule(module));
		}
	}, [module]);

	const groupsQ = useQuery({
		queryKey: ["module-groups", "list"],
		queryFn: () => modulesApi.listGroups(),
	});

	const toBody = (s: ShellState): ModuleCreateBody => ({
		name: s.name,
		group: s.group || null,
		route: s.route || undefined,
		icon: s.icon || undefined,
		class: s.class || undefined,
		table: s.table || undefined,
		graphql: s.graphql,
		graphql_type: s.graphql_type || undefined,
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

	const saveMutation = useMutation({
		mutationFn: (s: ShellState) =>
			isAdd ? modulesApi.create(toBody(s)) : modulesApi.update(moduleId as string, toBody(s)),
		onSuccess: (fresh) => {
			queryClient.invalidateQueries({ queryKey: ["modules"] });
			toast.success(isAdd ? "Module created" : "Module saved");

			if (isAdd) {
				navigate(`/developer/modules/${encodeURIComponent(fresh.id)}`, { replace: true });
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

	const set = (patch: Partial<ShellState>) => setState((prev) => ({ ...prev, ...patch }));
	const setGbp = (patch: Partial<ModuleGbpConfig>) =>
		setState((prev) => ({ ...prev, gbp: { ...prev.gbp, ...patch } }));

	const handleSubmit = (event: React.FormEvent) => {
		event.preventDefault();

		if (saveMutation.isPending) {
			return;
		}

		setGeneralError(null);
		setFieldErrors({});
		saveMutation.mutate(state);
	};

	const groupOptions = [
		{ value: "", label: "— No group —" },
		...(groupsQ.data ?? []).map((g) => ({ value: g.id, label: g.name })),
	];

	return (
		<form onSubmit={handleSubmit} className="space-y-4">
			{generalError && (
				<div className="rounded-md border border-danger/40 bg-danger/5 px-3 py-2 text-[12.5px] text-danger">
					{generalError}
				</div>
			)}

			<div className="grid grid-cols-1 gap-4 rounded-xl border border-border bg-surface p-4 md:grid-cols-2">
				<TextInput
					label="Name"
					value={state.name}
					onChange={(v) => set({ name: v })}
					error={fieldErrors.name}
					required
				/>
				<SelectInput
					label="Group"
					value={state.group}
					onChange={(v) => set({ group: v })}
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
				<TextInput
					label="Icon"
					value={state.icon}
					onChange={(v) => set({ icon: v })}
					hint="BigTree icon glyph name (e.g. list, news, calendar)."
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
			</div>

			<div className="space-y-3 rounded-xl border border-border bg-surface p-4">
				<CheckboxInput
					label="Expose this module via GraphQL"
					checked={state.graphql}
					onChange={(v) => set({ graphql: v })}
				/>
				{state.graphql && (
					<TextInput
						label="GraphQL type"
						value={state.graphql_type}
						onChange={(v) => set({ graphql_type: v })}
						mono
					/>
				)}
			</div>

			<div className="space-y-3 rounded-xl border border-border bg-surface p-4">
				<CheckboxInput
					label="Group-based permissions (per-category access)"
					checked={Boolean(state.gbp.enabled)}
					onChange={(v) => setGbp({ enabled: v })}
				/>
				{state.gbp.enabled && (
					<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
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
					</div>
				)}
			</div>

			<div className="flex justify-end">
				<button
					type="submit"
					className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-50 hover:bg-accent-hover"
					disabled={saveMutation.isPending}
				>
					<Save size={13} />
					{saveMutation.isPending ? "Saving…" : isAdd ? "Create module" : "Save module"}
				</button>
			</div>
		</form>
	);
};
