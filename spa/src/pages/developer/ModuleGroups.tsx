import type { DataTableColumn } from "@/components/ui/DataTable";
import { NameIdCell } from "@/components/ui/NameIdCell";

import { DeveloperListPage } from "@/components/developer/DeveloperListPage";

import { modulesApi, type ModuleGroup } from "@/api/endpoints/modules";

import { queryKeys } from "@/lib/queryKeys";

const columns: DataTableColumn<ModuleGroup>[] = [
	{
		key: "name",
		header: "Name",
		width: "minmax(0,2fr)",
		cell: (row) => <NameIdCell name={row.name} id={row.id} />,
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
	{
		key: "position",
		header: "Pos",
		width: "60px",
		hideOnMobile: true,
		align: "right",
		cell: (row) => (
			<span className="tabular-nums text-[12px] text-text-3">{row.position ?? 0}</span>
		),
	},
];

export const ModuleGroups = () => (
	<DeveloperListPage<ModuleGroup>
		title="Module groups"
		countNoun="group"
		route="/developer/module-groups"
		addLabel="Add group"
		loadingLabel="Loading groups…"
		emptyLabel="No module groups yet."
		queryKey={queryKeys.moduleGroups.list()}
		invalidateKey={queryKeys.moduleGroups.root()}
		list={() => modulesApi.listGroups()}
		remove={(id) => modulesApi.deleteGroup(id)}
		columns={columns}
		getRowKey={(row) => row.id}
		deleteButtonLabel="Delete group"
		deleteSuccessMessage="Module group deleted"
		rowLabel={(row) => row.name}
		confirmLabel="Delete group"
		confirmDescription="Modules in this group keep their definitions but lose their grouping on the Modules tab."
	/>
);
