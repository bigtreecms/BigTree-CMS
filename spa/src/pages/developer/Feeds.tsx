import type { DataTableColumn } from "@/components/ui/DataTable";
import { NameIdCell } from "@/components/ui/NameIdCell";
import { Badge } from "@/components/ui/Badge";

import { DeveloperListPage } from "@/components/developer/DeveloperListPage";

import { feedsApi, type FeedSummary } from "@/api/endpoints/feeds";

import { queryKeys } from "@/lib/queryKeys";

const columns: DataTableColumn<FeedSummary>[] = [
	{
		key: "name",
		header: "Name",
		width: "minmax(0,1.5fr)",
		cell: (row) => <NameIdCell id={row.id} name={row.name} />,
	},
	{
		key: "type",
		header: "Type",
		width: "120px",
		hideOnMobile: true,
		cell: (row) => <Badge size="sm">{row.type || "?"}</Badge>,
	},
	{
		key: "table",
		header: "Table",
		width: "minmax(0,1fr)",
		hideOnMobile: true,
		cell: (row) => (
			<span className="font-mono text-[11.5px] text-text-3">{row.table || "—"}</span>
		),
	},
];

export const Feeds = () => (
	<DeveloperListPage<FeedSummary>
		addLabel="Add feed"
		columns={columns}
		confirmDescription="The public URL backed by this feed will stop responding immediately."
		confirmLabel="Delete feed"
		countNoun="feed"
		deleteButtonLabel="Delete feed"
		deleteSuccessMessage="Feed deleted"
		emptyLabel="No feeds yet."
		getRowKey={(row) => row.id}
		invalidateKey={queryKeys.feeds.root()}
		list={() => feedsApi.list()}
		loadingLabel="Loading feeds…"
		queryKey={queryKeys.feeds.list()}
		remove={(id) => feedsApi.delete(id)}
		route="/developer/feeds"
		rowLabel={(row) => row.name}
		title="Feeds"
	/>
);
