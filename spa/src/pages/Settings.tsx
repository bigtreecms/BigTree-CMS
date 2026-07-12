import { useNavigate } from "react-router-dom";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { Pencil } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { NameIdCell } from "@/components/ui/NameIdCell";
import { Pager } from "@/components/ui/Pager";
import { SearchInput } from "@/components/ui/SearchInput";
import { Toolbar } from "@/components/ui/Toolbar";

import { settingsApi, type SettingDetail } from "@/api/endpoints/settings";

import { usePaginatedSearch } from "@/hooks/usePaginatedSearch";
import { stripHtml } from "@/lib/html";
import { derivePagination } from "@/lib/pagination";
import { queryKeys } from "@/lib/queryKeys";
import { formatNumber } from "@/lib/number";
import { settingEditPath } from "@/lib/routes";

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
	const {
		query: search,
		setQuery: setSearch,
		page,
		setPage,
		debouncedQuery: debounced,
	} = usePaginatedSearch();

	const query = useQuery({
		queryKey: queryKeys.settings.list({ page, per_page: PER_PAGE, q: debounced.trim() }),
		queryFn: () =>
			settingsApi.list({
				page,
				per_page: PER_PAGE,
				q: debounced.trim() || undefined,
			}),
		placeholderData: keepPreviousData,
	});

	const { rows, total, totalPages } = derivePagination({
		rows: query.data?.data,
		meta: query.data?.meta,
		page,
	});

	const columns: DataTableColumn<SettingDetail>[] = [
		{
			key: "name",
			header: "Name",
			width: "minmax(0,1.3fr)",
			cell: (row) => (
				<NameIdCell
					name={row.name}
					nameTitle={row.name}
					subtitle={row.description ? stripHtml(row.description) : undefined}
					subtitleTitle={row.description ? stripHtml(row.description) : undefined}
				/>
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
					className="inline-grid size-7 place-items-center rounded text-text-3 hover:bg-hover hover:text-text"
					title="Edit setting"
					aria-label={`Edit ${row.name}`}
					onClick={(e) => {
						e.stopPropagation();
						navigate(settingEditPath(row.id));
					}}
				>
					<Pencil size={14} />
				</button>
			),
		},
	];

	return (
		<PageContainer width="wide">
			<Breadcrumb items={[{ label: "Settings" }]} />

			<PageHead
				title="Settings"
				sub={total === 1 ? "1 setting" : `${formatNumber(total)} settings`}
			/>

			<Toolbar
				search={
					<SearchInput
						value={search}
						onChange={setSearch}
						placeholder="Search settings by name, id, or description…"
					/>
				}
			/>

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
				onRowClick={(row) => navigate(settingEditPath(row.id))}
			/>

			<div className="mt-3 flex justify-end">
				<Pager page={page} totalPages={totalPages} onChange={setPage} />
			</div>
		</PageContainer>
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
