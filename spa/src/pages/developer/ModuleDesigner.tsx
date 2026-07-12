import { NameIdCell } from "@/components/ui/NameIdCell";
import type { DataTableColumn } from "@/components/ui/DataTable";

import { DeveloperListPage } from "@/components/developer/DeveloperListPage";

import { modulesApi, type ModuleSummary } from "@/api/endpoints/modules";

import { queryKeys } from "@/lib/queryKeys";
import { moduleDetailPath } from "@/lib/routes";

/**
 * /developer/modules — the module designer landing. Lists every installed
 * module with delete and a link into the per-module tabbed editor. Module
 * ordering lives on the module group editor (modules are ordered within their
 * group), so this list has no reorder affordance. Distinct from the consumer
 * /modules tab (Modules.tsx) which groups modules for navigation; this is the
 * developer-facing CRUD list.
 */
export const ModuleDesigner = () => {
	const columns: DataTableColumn<ModuleSummary>[] = [
		{
			key: "name",
			header: "Name",
			width: "minmax(0,2fr)",
			cell: (row) => <NameIdCell name={row.name} id={row.id} />,
		},
		{
			key: "group",
			header: "Group",
			width: "minmax(0,1fr)",
			hideOnMobile: true,
			cell: (row) =>
				row.group_name ? (
					<span className="text-[12.5px] text-text-2">{row.group_name}</span>
				) : (
					<span className="text-text-3">—</span>
				),
		},
		{
			key: "route",
			header: "Route",
			width: "minmax(0,1fr)",
			hideOnMobile: true,
			cell: (row) =>
				row.route ? (
					<span className="font-mono text-[11.5px] text-text-3">{row.route}</span>
				) : (
					<span className="text-text-3">—</span>
				),
		},
	];

	return (
		<DeveloperListPage<ModuleSummary>
			title="Modules"
			countNoun="module"
			route="/developer/modules"
			addLabel="New module"
			loadingLabel="Loading modules…"
			emptyLabel="No modules defined yet."
			queryKey={queryKeys.modules.list()}
			invalidateKey={["modules"]}
			list={() => modulesApi.list()}
			remove={(id) => modulesApi.delete(id)}
			deriveRows={(data) =>
				[...data].sort((a, b) =>
					a.name.localeCompare(b.name, undefined, { sensitivity: "base" })
				)
			}
			columns={columns}
			getRowKey={(row) => row.id}
			deleteButtonLabel="Delete module"
			deleteSuccessMessage="Module deleted"
			rowLabel={(row) => row.name}
			confirmLabel="Delete module"
			confirmDescription="This removes the module and all of its actions, forms, views, reports and embed forms. Content rows in the module's table are left intact."
			rowPath={(row) => moduleDetailPath(row.id)}
		/>
	);
};
