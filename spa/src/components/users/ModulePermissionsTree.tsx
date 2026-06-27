import { useMemo } from "react";
import { useQuery } from "@tanstack/react-query";

import { modulesApi, type ModuleGroup, type ModuleSummary } from "@/api/endpoints/modules";
import { queryKeys } from "@/lib/queryKeys";
import type { PermissionCode, UserPermissions } from "@/api/endpoints/users";

import { SectionLabel } from "@/components/ui/SectionLabel";

import { PermissionRadios } from "./PermissionRadios";
import { PermissionRow } from "./PermissionRow";
import { PermissionTreeHeader } from "./PermissionTreeHeader";
import { MODULE_PERMISSION_OPTIONS } from "./permissionOptions";

const GRID_COLUMNS = "minmax(0,1fr) repeat(3, 80px)";

interface ModulePermissionsTreeProps {
	value: UserPermissions["module"];
	onChange: (next: NonNullable<UserPermissions["module"]>) => void;
	gbpValue: UserPermissions["module_gbp"];
	onGbpChange: (next: NonNullable<UserPermissions["module_gbp"]>) => void;
}

interface GbpCategoryRowsProps {
	module: ModuleSummary;
	value: Record<string, PermissionCode> | undefined;
	onChange: (categoryId: string, perm: PermissionCode) => void;
}

/**
 * Nested per-category permission rows for a GBP-enabled module. Categories are
 * the rows of the module's configured "other table", fetched from
 * `/modules/{id}/gbp-categories`. Each gets the same Publisher / Editor / No
 * Access radio columns as a module row, mirroring the PHP admin.
 */
const GbpCategoryRows = ({ module, value, onChange }: GbpCategoryRowsProps) => {
	const categoriesQ = useQuery({
		queryKey: queryKeys.modules.gbpCategories(module.id),
		queryFn: () => modulesApi.gbpCategories(module.id),
	});

	const label = module.gbp?.name || "Categories";

	if (categoriesQ.isLoading) {
		return (
			<div className="border-t border-border bg-surface-2/40 px-3 py-2 pl-8 text-[11.5px] text-text-3">
				Loading {label.toLowerCase()}…
			</div>
		);
	}

	const categories = categoriesQ.data ?? [];

	if (categories.length === 0) {
		return (
			<div className="border-t border-border bg-surface-2/40 px-3 py-2 pl-8 text-[11.5px] text-text-3">
				No {label.toLowerCase()} found.
			</div>
		);
	}

	return (
		<>
			{categories.map((category) => {
				const current = value?.[category.id] ?? "";

				return (
					<PermissionRow key={category.id} columns={GRID_COLUMNS} nested>
						<span className="truncate text-text-2">
							<span className="text-text-3">{label}:</span> {category.title}
						</span>
						<PermissionRadios
							name={`gbp-${module.id}-${category.id}`}
							value={current}
							onChange={(next) => onChange(category.id, next)}
							options={MODULE_PERMISSION_OPTIONS}
						/>
					</PermissionRow>
				);
			})}
		</>
	);
};

/**
 * Module permissions list. Modules are grouped by module group (with an
 * "Ungrouped" bucket for those without one). Each module gets a row of
 * Publisher / Editor / No Access radios — modules don't have an "Inherit"
 * option, mirroring the PHP admin.
 *
 * Group-based permissions (GBP): when a module has `gbp.enabled`, a nested
 * list of categories (rows from the module's configured other table) renders
 * below it with the same radio columns, each bound to the user's
 * `module_gbp[moduleId][categoryId]` grant.
 */
export const ModulePermissionsTree = ({
	value,
	onChange,
	gbpValue,
	onGbpChange,
}: ModulePermissionsTreeProps) => {
	const modulesQ = useQuery({ queryKey: queryKeys.modules.list(), queryFn: modulesApi.list });
	const groupsQ = useQuery({
		queryKey: queryKeys.moduleGroups.list(),
		queryFn: modulesApi.listGroups,
	});

	const grouped = useMemo(() => {
		const modules = modulesQ.data ?? [];
		const groups = groupsQ.data ?? [];
		const byGroup = new Map<string, ModuleSummary[]>();
		const UNGROUPED_KEY = "__ungrouped__";

		for (const m of modules) {
			const key = m.group ?? UNGROUPED_KEY;
			const list = byGroup.get(key) ?? [];
			list.push(m);
			byGroup.set(key, list);
		}

		for (const list of byGroup.values()) {
			list.sort((a, b) => a.name.localeCompare(b.name));
		}

		const ordered: Array<{ group: ModuleGroup; modules: ModuleSummary[] }> = [];

		for (const g of [...groups].sort((a, b) => a.name.localeCompare(b.name))) {
			const list = byGroup.get(g.id);

			if (list && list.length) {
				ordered.push({ group: g, modules: list });
			}
		}

		const ungrouped = byGroup.get(UNGROUPED_KEY);

		if (ungrouped && ungrouped.length) {
			ordered.push({
				group: { id: UNGROUPED_KEY, name: "— Ungrouped —" },
				modules: ungrouped,
			});
		}

		return ordered;
	}, [modulesQ.data, groupsQ.data]);

	const setPerm = (id: string, perm: PermissionCode) => {
		const next = { ...(value ?? {}) };

		if (perm === "" || perm === "i") {
			delete next[id];
		} else {
			next[id] = perm;
		}

		onChange(next);
	};

	const setGbpPerm = (moduleId: string, categoryId: string, perm: PermissionCode) => {
		const next = { ...(gbpValue ?? {}) };
		const moduleMap = { ...(next[moduleId] ?? {}) };

		moduleMap[categoryId] = perm;
		next[moduleId] = moduleMap;

		onGbpChange(next);
	};

	if (modulesQ.isLoading || groupsQ.isLoading) {
		return (
			<div className="rounded-md border border-border bg-surface px-3 py-6 text-center text-[12.5px] text-text-3">
				Loading modules…
			</div>
		);
	}

	if (grouped.length === 0) {
		return (
			<div className="rounded-md border border-border bg-surface px-3 py-6 text-center text-[12.5px] text-text-3">
				No modules installed.
			</div>
		);
	}

	return (
		<div>
			<PermissionTreeHeader columns={GRID_COLUMNS}>
				<div>Module</div>
				{MODULE_PERMISSION_OPTIONS.map((opt) => (
					<div key={opt.value} className="text-center">
						{opt.label}
					</div>
				))}
			</PermissionTreeHeader>

			<div className="border-x border-b border-border">
				{grouped.map(({ group, modules }) => (
					<div key={group.id}>
						<SectionLabel
							size="sm"
							className="border-t border-border bg-surface-2 px-3 py-1.5 first:border-t-0"
						>
							{group.name}
						</SectionLabel>

						{modules.map((m) => {
							const current = value?.[m.id] ?? "";
							const gbpEnabled = !!m.gbp?.enabled;

							return (
								<div key={m.id}>
									<PermissionRow columns={GRID_COLUMNS}>
										<span className="truncate text-text">{m.name}</span>
										<PermissionRadios
											name={`module-perm-${m.id}`}
											value={current}
											onChange={(next) => setPerm(m.id, next)}
											options={MODULE_PERMISSION_OPTIONS}
										/>
									</PermissionRow>

									{gbpEnabled && (
										<GbpCategoryRows
											module={m}
											value={gbpValue?.[m.id]}
											onChange={(categoryId, perm) =>
												setGbpPerm(m.id, categoryId, perm)
											}
										/>
									)}
								</div>
							);
						})}
					</div>
				))}
			</div>
		</div>
	);
};
