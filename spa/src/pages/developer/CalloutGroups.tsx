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
		addLabel="Add group"
		columns={columns}
		confirmDescription="Callouts in this group will continue to exist; only the grouping is removed."
		confirmLabel="Delete group"
		countNoun="group"
		deleteButtonLabel="Delete group"
		deleteSuccessMessage="Group deleted"
		emptyLabel="No callout groups yet."
		getRowKey={(row) => row.id}
		invalidateKey={queryKeys.calloutGroups.root()}
		list={() => calloutsApi.listGroups()}
		loadingLabel="Loading groups…"
		queryKey={queryKeys.calloutGroups.list()}
		remove={(id) => calloutsApi.deleteGroup(id)}
		route="/developer/callout-groups"
		rowLabel={(row) => row.name}
		title="Callout groups"
	/>
);
