import type { DataTableColumn } from "@/components/ui/DataTable";
import { NameIdCell } from "@/components/ui/NameIdCell";

import { DeveloperListPage } from "@/components/developer/DeveloperListPage";

import { calloutsApi, type CalloutSummary } from "@/api/endpoints/callouts";

import { queryKeys } from "@/lib/queryKeys";

const LEVEL_LABEL = ["Editor", "Admin", "Developer"];

const columns: DataTableColumn<CalloutSummary>[] = [
	{
		key: "name",
		header: "Name",
		width: "minmax(0,1.5fr)",
		cell: (row) => <NameIdCell id={row.id} name={row.name} />,
	},
	{
		key: "description",
		header: "Description",
		width: "minmax(0,1.5fr)",
		hideOnMobile: true,
		cell: (row) => (
			<span className="truncate text-[12px] text-text-3">{row.description || "—"}</span>
		),
	},
	{
		key: "fields",
		header: "Fields",
		width: "80px",
		hideOnMobile: true,
		align: "right",
		cell: (row) => (
			<span className="tabular-nums text-[12px] text-text-3">{row.resources.length}</span>
		),
	},
	{
		key: "level",
		header: "Level",
		width: "120px",
		hideOnMobile: true,
		cell: (row) => (
			<span className="text-[12px] text-text-3">{LEVEL_LABEL[row.level] ?? row.level}</span>
		),
	},
];

export const Callouts = () => (
	<DeveloperListPage<CalloutSummary>
		addLabel="Add callout"
		columns={columns}
		confirmDescription="Removing a callout type can break existing Callouts-field entries that reference it."
		confirmLabel="Delete callout"
		countNoun="callout"
		deleteButtonLabel="Delete callout"
		deleteSuccessMessage="Callout deleted"
		emptyLabel="No callouts yet."
		getRowKey={(row) => row.id}
		invalidateKey={queryKeys.callouts.root()}
		list={() => calloutsApi.list()}
		loadingLabel="Loading callouts…"
		queryKey={queryKeys.callouts.list()}
		remove={(id) => calloutsApi.delete(id)}
		route="/developer/callouts"
		rowLabel={(row) => row.name}
		title="Callouts"
	/>
);
