import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { Lock, Search, ShieldAlert, X } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Pager } from "@/components/ui/Pager";

import { settingsApi, type SettingDetail } from "@/api/endpoints/settings";

import { stripHtml } from "@/lib/html";

/**
 * /settings — global settings list.
 *
 *   - Server-paginated (per_page = 25) with a 200 ms debounced ?q= search.
 *   - Row click → /settings/:id/edit.
 *   - Locked / system / encrypted flags surface as tiny chips on the row.
 *
 * Admin-only create / delete are deferred to Phase 8 under
 * `/developer/settings/...` (matches the legacy PHP admin's organisation).
 */

const PER_PAGE = 25;

export const Settings = () => {
	const navigate = useNavigate();
	const [search, setSearch] = useState("");
	const [debounced, setDebounced] = useState("");
	const [page, setPage] = useState(1);

	useEffect(() => {
		const handle = setTimeout(() => {
			setDebounced(search.trim());
			setPage(1);
		}, 200);

		return () => clearTimeout(handle);
	}, [search]);

	const query = useQuery({
		queryKey: ["settings", "list", { page, per_page: PER_PAGE, q: debounced }],
		queryFn: () =>
			settingsApi.list({
				page,
				per_page: PER_PAGE,
				q: debounced || undefined,
			}),
		placeholderData: keepPreviousData,
	});

	const rows = query.data?.data ?? [];
	const total = (query.data?.meta?.total as number | undefined) ?? rows.length;
	const totalPages = (query.data?.meta?.pages as number | undefined) ?? 1;

	const columns: DataTableColumn<SettingDetail>[] = [
		{
			key: "name",
			header: "Name",
			width: "minmax(0,1.6fr)",
			cell: (row) => (
				<div className="min-w-0">
					<div className="truncate font-medium text-text" title={row.name}>
						{row.name}
					</div>
					{row.description && (
						<div
							className="truncate text-[11px] text-text-3"
							title={stripHtml(row.description)}
						>
							{stripHtml(row.description)}
						</div>
					)}
				</div>
			),
		},
		{
			key: "id",
			header: "ID",
			width: "minmax(0,1fr)",
			hideOnMobile: true,
			cell: (row) => (
				<span className="truncate font-mono text-[11.5px] text-text-3" title={row.id}>
					{row.id}
				</span>
			),
		},
		{
			key: "type",
			header: "Type",
			width: "120px",
			hideOnMobile: true,
			cell: (row) => (
				<span className="rounded bg-surface-2 px-1.5 py-0.5 text-[11px] font-medium text-text-3">
					{row.type || "text"}
				</span>
			),
		},
		{
			key: "flags",
			header: "Flags",
			width: "150px",
			hideOnMobile: true,
			cell: (row) => <FlagPills setting={row} />,
		},
	];

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb items={[{ label: "Settings" }]} />

			<PageHead
				title="Settings"
				sub={total === 1 ? "1 setting" : `${total.toLocaleString()} settings`}
			/>

			<div className="mb-3 flex flex-wrap items-center gap-3">
				<div className="relative w-full sm:w-auto sm:max-w-md sm:flex-1">
					<Search
						size={14}
						className="absolute left-3 top-1/2 -translate-y-1/2 text-text-3"
					/>
					<input
						className="w-full rounded-md border border-border bg-surface py-1.5 pl-9 pr-9 text-[13.5px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring"
						placeholder="Search settings by name, id, or description…"
						value={search}
						onChange={(e) => setSearch(e.target.value)}
					/>
					{search && (
						<button
							type="button"
							className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-text-3 hover:bg-hover hover:text-text"
							onClick={() => setSearch("")}
							aria-label="Clear search"
						>
							<X size={14} />
						</button>
					)}
				</div>
				<div className="flex-1" />
			</div>

			<DataTable<SettingDetail>
				columns={columns}
				rows={rows}
				getRowKey={(row) => row.id}
				isLoading={query.isLoading || (query.isFetching && !query.data)}
				loadingLabel="Loading settings…"
				emptyLabel={
					debounced
						? `No settings match “${debounced}”.`
						: "No settings yet. (Create them under Developer → Settings.)"
				}
				onRowClick={(row) => navigate(`/settings/${encodeURIComponent(row.id)}/edit`)}
			/>

			{totalPages > 1 && (
				<div className="mt-3 flex justify-end">
					<Pager page={page} totalPages={totalPages} onChange={setPage} />
				</div>
			)}
		</div>
	);
};

const FlagPills = ({ setting }: { setting: SettingDetail }) => (
	<div className="flex flex-wrap items-center gap-1">
		{setting.encrypted && (
			<span
				className="inline-flex items-center gap-1 rounded bg-info-bg px-1.5 py-0.5 text-[10.5px] font-medium text-info"
				title="Stored encrypted at rest"
			>
				<ShieldAlert size={10} />
				Encrypted
			</span>
		)}
		{setting.locked && (
			<span
				className="inline-flex items-center gap-1 rounded bg-warn-bg px-1.5 py-0.5 text-[10.5px] font-medium text-warn"
				title="Marked as locked in the definition (cannot delete via API)"
			>
				<Lock size={10} />
				Locked
			</span>
		)}
		{setting.system && (
			<span
				className="rounded bg-surface-2 px-1.5 py-0.5 text-[10.5px] font-medium text-text-3"
				title="System setting"
			>
				System
			</span>
		)}
	</div>
);
