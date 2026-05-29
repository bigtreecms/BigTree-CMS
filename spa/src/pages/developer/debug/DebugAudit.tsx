import { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { keepPreviousData, useQuery } from "@tanstack/react-query";

import { DebugLayout } from "@/components/developer/DebugLayout";
import { TableSelect } from "@/components/developer/TableSelect";
import { UserSelect } from "@/components/users/UserSelect";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Pager } from "@/components/ui/Pager";
import { SlideOver } from "@/components/ui/SlideOver";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import { auditApi, type AuditEntry } from "@/api/endpoints/audit";

const PER_PAGE = 50;

const humanizeType = (type: string): string =>
	type.replace(/[-_]/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());

const formatDate = (input: string): string => {
	const d = new Date(input.replace(" ", "T"));

	if (Number.isNaN(d.getTime())) {
		return input;
	}

	return d.toLocaleString(undefined, {
		year: "numeric",
		month: "short",
		day: "numeric",
		hour: "numeric",
		minute: "2-digit",
	});
};

/**
 * Developer → Debug → Audit trail. Read-only, server-paginated log of mutating
 * actions. Filters (actor, table, date range) drive the query directly; the
 * `?user=` search param pre-fills the actor filter so other screens can deep
 * link in. Rows open a detail panel with the captured request context.
 */
export const DebugAudit = () => {
	const [searchParams] = useSearchParams();

	const initialUser = searchParams.get("user");
	const [userFilter, setUserFilter] = useState<number | null>(
		initialUser ? Number(initialUser) : null
	);
	const [tableFilter, setTableFilter] = useState<string | null>(searchParams.get("table"));
	const [start, setStart] = useState("");
	const [end, setEnd] = useState("");
	const [page, setPage] = useState(1);
	const [detail, setDetail] = useState<AuditEntry | null>(null);

	useEffect(() => {
		setPage(1);
	}, [userFilter, tableFilter, start, end]);

	const listQ = useQuery({
		queryKey: ["audit", { userFilter, tableFilter, start, end, page }],
		queryFn: () =>
			auditApi.list({
				user: userFilter ?? undefined,
				table: tableFilter || undefined,
				start: start || undefined,
				end: end || undefined,
				include: "context",
				page,
				per_page: PER_PAGE,
			}),
		placeholderData: keepPreviousData,
	});

	const rows = listQ.data?.items ?? [];
	const meta = listQ.data?.meta ?? {};
	const total = meta.total ?? rows.length;
	const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));

	const columns: DataTableColumn<AuditEntry>[] = [
		{
			key: "date",
			header: "Date",
			width: "190px",
			cell: (row) => <span className="text-text-2">{formatDate(row.date)}</span>,
		},
		{
			key: "user",
			header: "User",
			width: "minmax(0,1.4fr)",
			cell: (row) => (
				<div className="min-w-0">
					<div className="truncate text-text">{row.user_name ?? `User #${row.user}`}</div>
					{row.user_email && (
						<div className="truncate font-mono text-[11px] text-text-3">
							{row.user_email}
						</div>
					)}
				</div>
			),
		},
		{
			key: "table",
			header: "Table",
			width: "minmax(0,1fr)",
			hideOnMobile: true,
			cell: (row) => <span className="font-mono text-[12px] text-text-2">{row.table}</span>,
		},
		{
			key: "entry",
			header: "Entry",
			width: "120px",
			hideOnMobile: true,
			cell: (row) => <span className="font-mono text-[12px] text-text-3">{row.entry}</span>,
		},
		{
			key: "type",
			header: "Action",
			width: "150px",
			cell: (row) => (
				<span className="inline-flex items-center rounded-full border border-border bg-surface-2 px-2 py-px text-[11px] font-medium text-text-2">
					{humanizeType(row.type)}
				</span>
			),
		},
	];

	return (
		<DebugLayout
			title="Audit trail"
			sub="Read-only history of mutating actions, attributed to the acting user."
		>
			<div className="mb-3 flex flex-wrap items-end gap-3">
				<div>
					<label className="mb-1 block text-[11px] font-medium text-text-3">User</label>
					<UserSelect
						value={userFilter}
						onChange={setUserFilter}
						ariaLabel="Filter by user"
						className="w-56"
					/>
				</div>

				<div>
					<label className="mb-1 block text-[11px] font-medium text-text-3">Table</label>
					<TableSelect
						value={tableFilter}
						onChange={setTableFilter}
						ariaLabel="Filter by table"
						className="w-56"
					/>
				</div>

				<div>
					<label className="mb-1 block text-[11px] font-medium text-text-3">From</label>
					<input
						type="date"
						className="rounded-md border border-border bg-surface px-3 py-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
						value={start}
						onChange={(e) => setStart(e.target.value)}
					/>
				</div>

				<div>
					<label className="mb-1 block text-[11px] font-medium text-text-3">To</label>
					<input
						type="date"
						className="rounded-md border border-border bg-surface px-3 py-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
						value={end}
						onChange={(e) => setEnd(e.target.value)}
					/>
				</div>

				<div className="flex-1" />

				<span className="text-[12px] text-text-3 tabular-nums">{total} entries</span>
			</div>

			{listQ.error ? (
				<ErrorPanel error={listQ.error} />
			) : (
				<DataTable
					columns={columns}
					rows={rows}
					getRowKey={(row) => row.id}
					isLoading={listQ.isLoading}
					loadingLabel="Loading audit trail…"
					emptyLabel="No audit entries match these filters."
					onRowClick={(row) => setDetail(row)}
				/>
			)}

			{totalPages > 1 && (
				<div className="mt-3 flex justify-end">
					<Pager page={page} totalPages={totalPages} onChange={setPage} />
				</div>
			)}

			<SlideOver
				open={detail !== null}
				onOpenChange={(open) => {
					if (!open) {
						setDetail(null);
					}
				}}
				title={detail ? humanizeType(detail.type) : "Audit entry"}
				description={detail ? `${detail.table} · ${detail.entry}` : undefined}
				width="lg"
			>
				{detail && (
					<dl className="grid grid-cols-[120px_1fr] gap-y-2 text-[12.5px]">
						<dt className="text-text-3">Date</dt>
						<dd className="text-text-2">{formatDate(detail.date)}</dd>
						<dt className="text-text-3">User</dt>
						<dd className="text-text-2">
							{detail.user_name ?? `User #${detail.user}`}
							{detail.user_email ? ` (${detail.user_email})` : ""}
						</dd>
						<dt className="text-text-3">Table</dt>
						<dd className="font-mono text-text-2">{detail.table}</dd>
						<dt className="text-text-3">Entry</dt>
						<dd className="font-mono text-text-2">{detail.entry}</dd>
						<dt className="text-text-3">Action</dt>
						<dd className="text-text-2">{humanizeType(detail.type)}</dd>

						{detail.context && (
							<>
								<dt className="text-text-3">IP</dt>
								<dd className="font-mono text-text-2">
									{detail.context.ip ?? "—"}
								</dd>
								<dt className="text-text-3">Method</dt>
								<dd className="font-mono text-text-2">
									{detail.context.method ?? "—"}
								</dd>
								<dt className="text-text-3">Path</dt>
								<dd className="break-all font-mono text-text-2">
									{detail.context.path ?? "—"}
								</dd>
								<dt className="text-text-3">Request ID</dt>
								<dd className="break-all font-mono text-text-2">
									{detail.context.request_id ?? "—"}
								</dd>
								<dt className="text-text-3">User agent</dt>
								<dd className="break-all text-text-2">
									{detail.context.user_agent ?? "—"}
								</dd>
							</>
						)}
					</dl>
				)}
			</SlideOver>
		</DebugLayout>
	);
};
