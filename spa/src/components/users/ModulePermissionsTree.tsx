import { useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import { Info } from "lucide-react";

import { modulesApi, type ModuleGroup, type ModuleSummary } from "@/api/endpoints/modules";
import type { PermissionCode, UserPermissions } from "@/api/endpoints/users";

import { MODULE_PERMISSION_OPTIONS, PermissionRadios } from "./PermissionRadios";

interface ModulePermissionsTreeProps {
	value: UserPermissions["module"];
	onChange: (next: UserPermissions["module"]) => void;
}

/**
 * Module permissions list. Modules are grouped by module group (with an
 * "Ungrouped" bucket for those without one). Each module gets a row of
 * Publisher / Editor / No Access radios — modules don't have an "Inherit"
 * option, mirroring the PHP admin.
 *
 * Group-based permissions (GBP): when a module has `gbp.enabled`, the PHP
 * admin shows a nested list of categories (rows from another table) with the
 * same radio columns. The API doesn't expose that "other table" today, so we
 * render an info row directing the admin to the legacy admin to manage per-
 * category permissions — and we *preserve* any existing module_gbp values in
 * the form state (they're round-tripped untouched).
 */
export const ModulePermissionsTree = ({ value, onChange }: ModulePermissionsTreeProps) => {
	const modulesQ = useQuery({ queryKey: ["modules", "list"], queryFn: modulesApi.list });
	const groupsQ = useQuery({
		queryKey: ["module-groups", "list"],
		queryFn: modulesApi.listGroups,
	});

	const grouped = useMemo(() => {
		const modules = modulesQ.data ?? [];
		const groups = groupsQ.data ?? [];
		const byGroup = new Map<number, ModuleSummary[]>();

		for (const m of modules) {
			const key = m.group ?? 0;
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

		const ungrouped = byGroup.get(0);

		if (ungrouped && ungrouped.length) {
			ordered.push({ group: { id: 0, name: "— Ungrouped —" }, modules: ungrouped });
		}

		return ordered;
	}, [modulesQ.data, groupsQ.data]);

	const setPerm = (id: number, perm: PermissionCode) => {
		const next = { ...(value ?? {}) };

		if (perm === "" || perm === "i") {
			delete next[String(id)];
		} else {
			next[String(id)] = perm;
		}

		onChange(next);
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
			<div
				className="grid items-center gap-2 rounded-t-md border border-border bg-surface-2 px-3 py-2 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-text-3"
				style={{ gridTemplateColumns: "minmax(0,1fr) repeat(3, 80px)" }}
			>
				<div>Module</div>
				{MODULE_PERMISSION_OPTIONS.map((opt) => (
					<div key={opt.value} className="text-center">
						{opt.label}
					</div>
				))}
			</div>

			<div className="border-x border-b border-border">
				{grouped.map(({ group, modules }) => (
					<div key={group.id}>
						<div className="border-t border-border bg-surface-2 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.06em] text-text-3 first:border-t-0">
							{group.name}
						</div>

						{modules.map((m) => {
							const idKey = String(m.id);
							const current = value?.[idKey] ?? "";
							const gbpEnabled = !!m.gbp?.enabled;

							return (
								<div key={m.id}>
									<div
										className="grid items-center gap-2 border-t border-border bg-surface px-3 py-1.5 text-[12.5px]"
										style={{
											gridTemplateColumns: "minmax(0,1fr) repeat(3, 80px)",
										}}
									>
										<span className="truncate text-text">{m.name}</span>
										<PermissionRadios
											name={`module-perm-${m.id}`}
											value={current}
											onChange={(next) => setPerm(m.id, next)}
											options={MODULE_PERMISSION_OPTIONS}
										/>
									</div>

									{gbpEnabled && (
										<div className="border-t border-border bg-info-bg/40 px-3 py-2 text-[11.5px] text-text-2">
											<div className="flex items-start gap-2">
												<Info
													size={13}
													className="mt-0.5 shrink-0 text-info"
												/>
												<div>
													<strong>Group-based permissions:</strong>{" "}
													{`This module has group-based access (${m.gbp?.name || "categories"}). Existing grants are preserved on save, but editing them is still done from the legacy admin until the categories endpoint ships.`}
												</div>
											</div>
										</div>
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
