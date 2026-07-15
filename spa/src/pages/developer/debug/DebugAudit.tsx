import { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { keepPreviousData, useQuery } from "@tanstack/react-query";

import { DebugLayout } from "@/components/developer/DebugLayout";
import { TableSelect } from "@/components/developer/TableSelect";
import { UserSelect } from "@/components/users/UserSelect";
import { Badge } from "@/components/ui/Badge";
import { Sparkles } from "lucide-react";
import { DescriptionList } from "@/components/ui/DescriptionList";
import { MonoText } from "@/components/ui/MonoText";
import { FieldLabel } from "@/components/ui/Field";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Pager } from "@/components/ui/Pager";
import { SlideOver } from "@/components/ui/SlideOver";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { TextInput } from "@/components/ui/TextInput";

import { auditApi, type AuditEntry } from "@/api/endpoints/audit";
import { formatDateTime } from "@/lib/time";
import { derivePagination } from "@/lib/pagination";
import { queryKeys } from "@/lib/queryKeys";

const PER_PAGE = 50;

const humanizeType = (type: string): string =>
	type.replace(/[-_]/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());

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
	const [via, setVia] = useState<"" | "ai_assistant">(
		searchParams.get("via") === "ai_assistant" ? "ai_assistant" : ""
	);
	const [page, setPage] = useState(1);
	const [detail, setDetail] = useState<AuditEntry | null>(null);

	useEffect(() => {
		setPage(1);
	}, [userFilter, tableFilter, start, end, via]);

	const listQ = useQuery({
		queryKey: queryKeys.audit.list({ userFilter, tableFilter, start, end, via, page }),
		queryFn: () =>
			auditApi.list({
				user: userFilter ?? undefined,
				table: tableFilter || undefined,
				start: start || undefined,
				end: end || undefined,
				via: via || undefined,
				include: "context",
				page,
				per_page: PER_PAGE,
			}),
		placeholderData: keepPreviousData,
	});

	const { rows, total, totalPages } = derivePagination({
		rows: listQ.data?.items,
		meta: listQ.data?.meta,
		page,
		perPage: PER_PAGE,
	});

	const columns: DataTableColumn<AuditEntry>[] = [
		{
			key: "date",
			header: "Date",
			width: "190px",
			cell: (row) => <span className="text-text-2">{formatDateTime(row.date)}</span>,
		},
		{
			key: "user",
			header: "User",
			width: "minmax(0,1.4fr)",
			cell: (row) => (
				<div className="min-w-0">
					<div className="truncate text-text">{row.user_name ?? `User #${row.user}`}</div>
					{row.user_email && <MonoText as="div">{row.user_email}</MonoText>}
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
			width: "190px",
			cell: (row) => (
				<div className="flex items-center gap-1.5">
					<Badge bordered>{humanizeType(row.type)}</Badge>
					{row.context?.via === "ai_assistant" && (
						<Badge
							icon={<Sparkles size={9} />}
							size="sm"
							title="Approved via the AI assistant"
							tone="accent"
						>
							AI
						</Badge>
					)}
				</div>
			),
		},
	];

	return (
		<DebugLayout
			sub="Read-only history of mutating actions, attributed to the acting user."
			title="Audit trail"
		>
			<div className="mb-3 flex flex-wrap items-end gap-3">
				<div>
					<FieldLabel as="label" htmlFor="audit-user" size="sm" tone="muted">
						User
					</FieldLabel>
					<UserSelect
						ariaLabel="Filter by user"
						className="w-56"
						id="audit-user"
						value={userFilter}
						onChange={setUserFilter}
					/>
				</div>

				<div>
					<FieldLabel as="label" htmlFor="audit-table" size="sm" tone="muted">
						Table
					</FieldLabel>
					<TableSelect
						ariaLabel="Filter by table"
						className="w-56"
						id="audit-table"
						value={tableFilter}
						onChange={setTableFilter}
					/>
				</div>

				<div>
					<FieldLabel as="label" htmlFor="audit-start" size="sm" tone="muted">
						From
					</FieldLabel>
					<TextInput
						dense
						id="audit-start"
						type="date"
						value={start}
						onChange={(e) => setStart(e.target.value)}
					/>
				</div>

				<div>
					<FieldLabel as="label" htmlFor="audit-end" size="sm" tone="muted">
						To
					</FieldLabel>
					<TextInput
						dense
						id="audit-end"
						type="date"
						value={end}
						onChange={(e) => setEnd(e.target.value)}
					/>
				</div>

				<div>
					<FieldLabel as="label" htmlFor="audit-via" size="sm" tone="muted">
						Source
					</FieldLabel>
					<select
						aria-label="Filter by change source"
						className="h-8 rounded-md border border-border bg-surface px-2 text-[13px] text-text focus:border-border-strong focus:outline-none"
						id="audit-via"
						value={via}
						onChange={(e) =>
							setVia(e.target.value === "ai_assistant" ? "ai_assistant" : "")
						}
					>
						<option value="">Any source</option>
						<option value="ai_assistant">AI assistant</option>
					</select>
				</div>

				<div className="flex-1" />

				<span className="text-[12px] text-text-3 tabular-nums">{total} entries</span>
			</div>

			{listQ.error ? (
				<ErrorPanel error={listQ.error} />
			) : (
				<DataTable
					columns={columns}
					emptyLabel="No audit entries match these filters."
					getRowKey={(row) => row.id}
					isLoading={listQ.isLoading}
					loadingLabel="Loading audit trail…"
					rows={rows}
					onRowClick={(row) => setDetail(row)}
				/>
			)}

			{totalPages > 1 && (
				<div className="mt-3 flex justify-end">
					<Pager page={page} totalPages={totalPages} onChange={setPage} />
				</div>
			)}

			<SlideOver
				description={detail ? `${detail.table} · ${detail.entry}` : undefined}
				open={detail !== null}
				title={detail ? humanizeType(detail.type) : "Audit entry"}
				width="lg"
				onOpenChange={(open) => {
					if (!open) {
						setDetail(null);
					}
				}}
			>
				{detail && (
					<DescriptionList
						items={[
							{ label: "Date", value: formatDateTime(detail.date) },
							{
								label: "User",
								value: `${detail.user_name ?? `User #${detail.user}`}${detail.user_email ? ` (${detail.user_email})` : ""}`,
							},
							{ label: "Table", value: detail.table, valueClassName: "font-mono" },
							{ label: "Entry", value: detail.entry, valueClassName: "font-mono" },
							{ label: "Action", value: humanizeType(detail.type) },
							...(detail.context?.via === "ai_assistant"
								? [{ label: "Source", value: "AI assistant (approved proposal)" }]
								: []),
							...(detail.context
								? [
										{
											label: "IP",
											value: detail.context.ip ?? "—",
											valueClassName: "font-mono",
										},
										{
											label: "Method",
											value: detail.context.method ?? "—",
											valueClassName: "font-mono",
										},
										{
											label: "Path",
											value: detail.context.path ?? "—",
											valueClassName: "break-all font-mono",
										},
										{
											label: "Request ID",
											value: detail.context.request_id ?? "—",
											valueClassName: "break-all font-mono",
										},
										{
											label: "User agent",
											value: detail.context.user_agent ?? "—",
											valueClassName: "break-all",
										},
									]
								: []),
						]}
					/>
				)}
			</SlideOver>
		</DebugLayout>
	);
};
