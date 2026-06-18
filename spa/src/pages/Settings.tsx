import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { Pencil, Search, X } from "lucide-react";

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
			width: "minmax(0,1.3fr)",
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
			key: "value",
			header: "Value",
			width: "minmax(0,1.7fr)",
			cell: (row) => {
				const { text, muted } = formatSettingValue(row);

				return (
					<span
						className={`truncate ${muted ? "italic text-text-3" : "text-text-2"}`}
						title={muted ? undefined : text}
					>
						{text}
					</span>
				);
			},
		},
		{
			key: "edit",
			header: "Edit",
			width: "60px",
			headerAlign: "right",
			align: "right",
			cell: (row) => (
				<button
					type="button"
					className="inline-grid h-7 w-7 place-items-center rounded text-text-3 hover:bg-hover hover:text-text"
					title="Edit setting"
					aria-label={`Edit ${row.name}`}
					onClick={(e) => {
						e.stopPropagation();
						navigate(`/settings/${encodeURIComponent(row.id)}/edit`);
					}}
				>
					<Pencil size={14} />
				</button>
			),
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

/**
 * Mirror the legacy admin's settings-list Value column (see
 * core/admin/ajax/settings/get-page.php):
 *
 *   - encrypted             → "— Encrypted Value —"
 *   - array / object value  → "— Click Edit To View —"
 *   - HTML-only string      → "— Click Edit To View —" (nothing left after strip)
 *   - plain string          → stripped & trimmed to 100 chars
 *   - empty / null          → blank
 *
 * `muted` flags the placeholder strings so they render in italic muted text.
 */
const formatSettingValue = (setting: SettingDetail): { text: string; muted: boolean } => {
	if (setting.encrypted) {
		return { text: "— Encrypted Value —", muted: true };
	}

	const value = setting.value;

	if (value === null || value === undefined || value === "") {
		return { text: "", muted: false };
	}

	if (typeof value === "object") {
		return { text: "— Click Edit To View —", muted: true };
	}

	if (typeof value === "string") {
		const stripped = stripHtml(value);

		if (stripped.length === 0) {
			return { text: "— Click Edit To View —", muted: true };
		}

		const trimmed = stripped.length > 100 ? `${stripped.slice(0, 100)}…` : stripped;

		return { text: trimmed, muted: false };
	}

	return { text: String(value), muted: false };
};
