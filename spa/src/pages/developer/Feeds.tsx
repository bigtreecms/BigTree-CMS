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
		cell: (row) => <NameIdCell name={row.name} id={row.id} />,
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
		title="Feeds"
		countNoun="feed"
		route="/developer/feeds"
		addLabel="Add feed"
		loadingLabel="Loading feeds…"
		emptyLabel="No feeds yet."
		queryKey={queryKeys.feeds.list()}
		invalidateKey={queryKeys.feeds.root()}
		list={() => feedsApi.list()}
		remove={(id) => feedsApi.delete(id)}
		columns={columns}
		getRowKey={(row) => row.id}
		deleteButtonLabel="Delete feed"
		deleteSuccessMessage="Feed deleted"
		rowLabel={(row) => row.name}
		confirmLabel="Delete feed"
		confirmDescription="The public URL backed by this feed will stop responding immediately."
	/>
);
