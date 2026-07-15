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
		cell: (row) => <NameIdCell id={row.id} name={row.name} />,
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
		addLabel="Add group"
		columns={columns}
		confirmDescription="Modules in this group keep their definitions but lose their grouping on the Modules tab."
		confirmLabel="Delete group"
		countNoun="group"
		deleteButtonLabel="Delete group"
		deleteSuccessMessage="Module group deleted"
		emptyLabel="No module groups yet."
		getRowKey={(row) => row.id}
		invalidateKey={queryKeys.moduleGroups.root()}
		list={() => modulesApi.listGroups()}
		loadingLabel="Loading groups…"
		queryKey={queryKeys.moduleGroups.list()}
		remove={(id) => modulesApi.deleteGroup(id)}
		route="/developer/module-groups"
		rowLabel={(row) => row.name}
		title="Module groups"
	/>
);
