import type { DataTableColumn } from "@/components/ui/DataTable";

import { DeveloperListPage } from "@/components/developer/DeveloperListPage";

import { calloutsApi, type CalloutGroup } from "@/api/endpoints/callouts";

import { queryKeys } from "@/lib/queryKeys";

const columns: DataTableColumn<CalloutGroup>[] = [
	{
		key: "name",
		header: "Name",
		width: "minmax(0,1.5fr)",
		cell: (row) => (
			<div className="min-w-0">
				<div className="truncate font-medium text-text">{row.name}</div>
			</div>
		),
	},
	{
		key: "count",
		header: "Callouts",
		width: "120px",
		hideOnMobile: true,
		align: "left",
		cell: (row) => (
			<span className="tabular-nums text-[12px] text-text-3">
				{(row.callouts ?? []).length}
			</span>
		),
	},
];

export const CalloutGroups = () => (
	<DeveloperListPage<CalloutGroup>
		title="Callout groups"
		countNoun="group"
		route="/developer/callout-groups"
		addLabel="Add group"
		loadingLabel="Loading groups…"
		emptyLabel="No callout groups yet."
		queryKey={queryKeys.calloutGroups.list()}
		invalidateKey={queryKeys.calloutGroups.root()}
		list={() => calloutsApi.listGroups()}
		remove={(id) => calloutsApi.deleteGroup(id)}
		columns={columns}
		getRowKey={(row) => row.id}
		deleteButtonLabel="Delete group"
		deleteSuccessMessage="Group deleted"
		rowLabel={(row) => row.name}
		confirmLabel="Delete group"
		confirmDescription="Callouts in this group will continue to exist; only the grouping is removed."
	/>
);
