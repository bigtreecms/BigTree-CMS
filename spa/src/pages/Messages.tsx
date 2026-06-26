import { useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { Inbox, Plus, Send } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Button } from "@/components/ui/Button";
import { Pager } from "@/components/ui/Pager";
import { SubNav } from "@/components/ui/SubNav";

import { ComposeMessage } from "@/components/messages/ComposeMessage";

import { messagesApi, type Message } from "@/api/endpoints/dashboard";
import { useAuthStore } from "@/auth/store";
import { formatNumber } from "@/lib/number";
import { queryKeys } from "@/lib/queryKeys";

/**
 * /messages and /messages/sent — paginated inbox / sent list with a Compose
 * button and a SubNav for switching folders.
 */

const PER_PAGE = 25;

export const Messages = () => {
	const location = useLocation();
	const folder: "in" | "sent" = location.pathname.endsWith("/sent") ? "sent" : "in";
	const navigate = useNavigate();
	const currentUserId = useAuthStore((s) => s.user?.id ?? 0);

	const [page, setPage] = useState(1);
	const [composeOpen, setComposeOpen] = useState(false);

	const query = useQuery({
		queryKey: queryKeys.messages.list({ folder, page, per_page: PER_PAGE }),
		queryFn: () => messagesApi.list({ folder, page, per_page: PER_PAGE }),
		placeholderData: keepPreviousData,
	});

	const rows = query.data?.data ?? [];
	const total = (query.data?.meta?.total as number | undefined) ?? rows.length;
	const totalPages = (query.data?.meta?.pages as number | undefined) ?? 1;

	const columns: DataTableColumn<Message>[] = [
		{
			key: "subject",
			header: folder === "in" ? "From / Subject" : "To / Subject",
			width: "minmax(0,2fr)",
			cell: (row) => {
				const unread =
					folder === "in" && currentUserId && !row.read_by.includes(currentUserId);

				return (
					<div className="min-w-0">
						<div
							className={`truncate ${unread ? "font-semibold text-text" : "text-text-2"}`}
							title={row.subject}
						>
							{row.subject || "(no subject)"}
						</div>
						<div className="truncate text-[11px] text-text-3">
							{folder === "in"
								? `From ${row.sender_name ?? `#${row.sender}`}`
								: `To ${row.recipient_names
										.map((r) => r.name ?? `#${r.id}`)
										.join(", ")}`}
						</div>
					</div>
				);
			},
		},
		{
			key: "date",
			header: "Date",
			width: "180px",
			hideOnMobile: true,
			cell: (row) => (
				<span className="text-[11.5px] tabular-nums text-text-3">{row.date}</span>
			),
		},
	];

	return (
		<PageContainer width="xwide">
			<Breadcrumb items={[{ label: "Messages" }]} />

			<PageHead
				title="Messages"
				sub={total === 1 ? "1 message" : `${formatNumber(total)} messages`}
				actions={
					<Button
						variant="primary"
						icon={<Plus size={13} />}
						onClick={() => setComposeOpen(true)}
					>
						New message
					</Button>
				}
			/>

			<div className="mb-3">
				<SubNav<"in" | "sent">
					items={[
						{ value: "in", label: "Inbox", icon: <Inbox size={13} /> },
						{ value: "sent", label: "Sent", icon: <Send size={13} /> },
					]}
					value={folder}
					onChange={(v) => navigate(v === "sent" ? "/messages/sent" : "/messages")}
				/>
			</div>

			<DataTable<Message>
				columns={columns}
				rows={rows}
				getRowKey={(row) => row.id}
				isLoading={query.isLoading || (query.isFetching && !query.data)}
				loadingLabel="Loading messages…"
				emptyLabel={folder === "in" ? "No messages in your inbox." : "No sent messages."}
				onRowClick={(row) => navigate(`/messages/${row.id}`)}
			/>

			{totalPages > 1 && (
				<div className="mt-3 flex justify-end">
					<Pager page={page} totalPages={totalPages} onChange={setPage} />
				</div>
			)}

			<ComposeMessage
				open={composeOpen}
				onOpenChange={setComposeOpen}
				currentUserId={currentUserId}
			/>
		</PageContainer>
	);
};
