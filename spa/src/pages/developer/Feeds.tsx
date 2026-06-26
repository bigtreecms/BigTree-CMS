import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Plus, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { MonoText } from "@/components/ui/MonoText";
import { Button } from "@/components/ui/Button";
import { IconButton } from "@/components/ui/IconButton";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import { feedsApi, type FeedSummary } from "@/api/endpoints/feeds";

import { useToastMutation } from "@/hooks/useToastMutation";

export const Feeds = () => {
	const navigate = useNavigate();
	const [confirmDelete, setConfirmDelete] = useState<FeedSummary | null>(null);

	const query = useQuery({
		queryKey: ["feeds", "list"],
		queryFn: () => feedsApi.list(),
	});

	const deleteMutation = useToastMutation({
		mutationFn: (id: string) => feedsApi.delete(id),
		invalidate: [["feeds"]],
		successMessage: "Feed deleted",
		errorMessage: "Delete failed",
		onSuccess: () => {
			setConfirmDelete(null);
		},
	});

	const rows = query.data ?? [];

	const columns: DataTableColumn<FeedSummary>[] = [
		{
			key: "name",
			header: "Name",
			width: "minmax(0,1.5fr)",
			cell: (row) => (
				<div className="min-w-0">
					<div className="truncate font-medium text-text">{row.name}</div>
					<MonoText as="div">{row.id}</MonoText>
				</div>
			),
		},
		{
			key: "type",
			header: "Type",
			width: "120px",
			hideOnMobile: true,
			cell: (row) => (
				<span className="rounded bg-surface-2 px-1.5 py-0.5 text-[11px] font-medium text-text-3">
					{row.type || "?"}
				</span>
			),
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
		{
			key: "actions",
			header: "",
			width: "56px",
			align: "right",
			cell: (row) => (
				<IconButton
					tone="danger"
					onClick={(e) => {
						e.stopPropagation();
						setConfirmDelete(row);
					}}
					title="Delete feed"
					label="Delete feed"
				>
					<Trash size={13} />
				</IconButton>
			),
		},
	];

	return (
		<PageContainer width="wide">
			<Breadcrumb items={[{ label: "Developer", to: "/developer" }, { label: "Feeds" }]} />

			<PageHead
				title="Feeds"
				sub={rows.length === 1 ? "1 feed" : `${rows.length} feeds`}
				actions={
					<Button variant="primary" icon={<Plus size={13} />} to="/developer/feeds/add">
						Add feed
					</Button>
				}
			/>

			<DeveloperSectionNav />

			<DataTable<FeedSummary>
				columns={columns}
				rows={rows}
				getRowKey={(row) => row.id}
				isLoading={query.isLoading}
				loadingLabel="Loading feeds…"
				emptyLabel="No feeds yet."
				onRowClick={(row) =>
					navigate(`/developer/feeds/${encodeURIComponent(row.id)}/edit`)
				}
			/>

			{confirmDelete && (
				<ConfirmDialog
					open
					onOpenChange={(open) => {
						if (!open) {
							setConfirmDelete(null);
						}
					}}
					title={`Delete "${confirmDelete.name}"?`}
					description="The public URL backed by this feed will stop responding immediately."
					confirmLabel="Delete feed"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(confirmDelete.id)}
				/>
			)}
		</PageContainer>
	);
};
