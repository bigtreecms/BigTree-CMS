import { useEffect, useMemo, useState } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";

import { DataTable, type DataTableColumn, type DataTableSort } from "@/components/ui/DataTable";
import { SearchInput } from "@/components/ui/SearchInput";
import { Toolbar } from "@/components/ui/Toolbar";
import { Pager } from "@/components/ui/Pager";

import {
	autoModulesApi,
	type ModuleEntriesListParams,
	type ModuleEntryRow,
} from "@/api/endpoints/auto-modules";
import { queryKeys } from "@/lib/queryKeys";
import type { ModuleView } from "@/api/endpoints/modules";

import {
	columnWidth,
	formatCellValue,
	formatSortParam,
	isPersistedEntryId,
	parseSortSetting,
	parseViewActions,
	statusDimClass,
	statusFromRow,
	statusRowClass,
} from "./viewHelpers";
import { ViewStatusBadge } from "./ViewStatusBadge";
import { RowActions } from "./RowActions";
import { useEntryDelete } from "./useEntryDelete";
import { useModuleEntryLinks } from "@/pages/ModuleLayout";
import { useDebouncedValue } from "@/hooks/useDebouncedValue";

/**
 * Runtime for the `searchable` view type — the most common module view. Reads
 * column config from `view.fields` (a dict keyed by column name) and rows from
 * `/modules/{id}/entries`.
 *
 *   - Server-side pagination + search (q param)
 *   - Sort: column header click toggles sort dir; sort is passed to the
 *     server as `"<col> ASC|DESC"`. The PHP service falls back to id DESC
 *     when none is given.
 *   - Per-row actions: edit / delete are always available; custom actions
 *     (defined in view.actions with a JSON-encoded value) are rendered as
 *     a link if a route is set. Builtin "on" toggles (edit, delete) gate
 *     visibility of those default actions.
 *
 * The legacy per-column `parser` is a PHP eval string. We display raw values
 * for now; the form runtime in a later commit will add display-side parsers
 * for the few legacy parsers we want to honor (dates, JSON arrays, etc.).
 */

interface SearchableViewProps {
	moduleId: string;
	view: ModuleView;
}

export const SearchableView = ({ moduleId, view }: SearchableViewProps) => {
	const navigate = useNavigate();
	const { editPath, actionPath } = useModuleEntryLinks();
	const [page, setPage] = useState(1);
	const [query, setQuery] = useState("");
	const debouncedQuery = useDebouncedValue(query, 200);
	const [sort, setSort] = useState<DataTableSort | undefined>(() => parseSortSetting(view));
	const { requestDelete, dialog: deleteDialog } = useEntryDelete(moduleId, view.id);

	useEffect(() => {
		setPage(1);
	}, [debouncedQuery]);

	const params: ModuleEntriesListParams = {
		page,
		q: debouncedQuery || undefined,
		sort: formatSortParam(sort),
		view: view.id,
	};

	const listQuery = useQuery({
		queryKey: queryKeys.moduleEntries.viewQuery(moduleId, view.id, params),
		queryFn: () => autoModulesApi.list(moduleId, params),
		placeholderData: keepPreviousData,
	});

	const { builtins, custom } = useMemo(() => parseViewActions(view.actions), [view.actions]);
	const fieldColumns = useMemo(() => Object.entries(view.fields ?? {}), [view.fields]);
	const builtinCount =
		(builtins.edit ? 1 : 0) +
		(builtins.delete ? 1 : 0) +
		(builtins.archive ? 1 : 0) +
		(builtins.approve ? 1 : 0) +
		(builtins.feature ? 1 : 0);
	const hasRowActions = builtinCount > 0 || custom.length > 0;

	const columns: DataTableColumn<ModuleEntryRow>[] = useMemo(() => {
		// The view-cache rows returned by /modules/{id}/entries use positional
		// keys (`column1`, `column2`, …) rather than the field name. See
		// BigTreeAutoModule::getSearchResults — values are written in field
		// definition order. We sort by the original field key so the server
		// still understands the sort param.
		const cols: DataTableColumn<ModuleEntryRow>[] = fieldColumns.map(([key, field], index) => {
			const valueKey = `column${index + 1}`;

			return {
				key,
				header: field.title,
				width: columnWidth(field),
				sortable: true,
				align: field.numeric ? "right" : "left",
				headerAlign: field.numeric ? "right" : "left",
				cell: (row) => {
					const dim = statusDimClass(statusFromRow(row).key);

					return (
						<span
							className={`${field.numeric ? "tabular-nums text-text-2" : "text-text-2"} ${dim}`}
						>
							{formatCellValue(row[valueKey])}
						</span>
					);
				},
			};
		});

		// Status column — mirrors the legacy admin's sortable "Status" header
		// (sort key `_status_`, which BigTreeAutoModule::getSearchResults orders
		// alphabetically by status label). Always shown, like the legacy view.
		cols.push({
			key: "_status_",
			header: "Status",
			width: "92px",
			sortable: true,
			align: "left",
			headerAlign: "left",
			cell: (row) => <ViewStatusBadge row={row} plainOnMobile />,
		});

		if (hasRowActions) {
			const actionsCount = builtinCount + custom.length;

			cols.push({
				key: "__actions__",
				header: "Actions",
				width: `${Math.max(64, actionsCount * 36)}px`,
				align: "right",
				headerAlign: "right",
				cell: (row) => {
					// Edit and delete also accept a "p"-prefixed pending id; an
					// unpersisted row hides edit and disables delete.
					const canEditOrDelete = isPersistedEntryId(row.id);
					const dim = statusDimClass(statusFromRow(row).key);

					return (
						<RowActions
							moduleId={moduleId}
							viewId={view.id}
							row={row}
							builtins={builtins}
							custom={custom}
							editPath={editPath}
							actionPath={actionPath}
							onDelete={requestDelete}
							canEditOrDelete={canEditOrDelete}
							className={`w-full ${dim}`}
						/>
					);
				},
			});
		}

		return cols;
	}, [
		fieldColumns,
		hasRowActions,
		builtins,
		builtinCount,
		custom,
		moduleId,
		view.id,
		requestDelete,
		editPath,
		actionPath,
	]);

	const rows = listQuery.data?.items ?? [];
	const meta = listQuery.data?.meta;
	const totalPages = Math.max(1, meta?.pages ?? 1);
	const safePage = Math.min(page, totalPages);

	const onSortChange = (next: DataTableSort) => {
		setSort(next);
		setPage(1);
	};

	const onRowClick = (row: ModuleEntryRow) => {
		if (!builtins.edit || !isPersistedEntryId(row.id)) {
			return;
		}

		navigate(editPath(row.id));
	};

	return (
		<>
			<Toolbar
				search={
					<SearchInput
						value={query}
						onChange={setQuery}
						placeholder={`Search ${view.title.toLowerCase()}…`}
						aria-label={`Search ${view.title.toLowerCase()}`}
					/>
				}
			>
				<Pager page={safePage} totalPages={totalPages} onChange={setPage} />
			</Toolbar>

			<DataTable<ModuleEntryRow>
				columns={columns}
				rows={rows}
				getRowKey={(row) => row.id as string | number}
				isLoading={listQuery.isLoading && !listQuery.data}
				loadingLabel="Loading entries…"
				emptyLabel={
					debouncedQuery ? `No entries match “${debouncedQuery}”.` : "No entries yet."
				}
				sort={sort}
				onSortChange={onSortChange}
				onRowClick={builtins.edit ? onRowClick : undefined}
				rowClassName={(row) => statusRowClass(statusFromRow(row).key)}
			/>

			{deleteDialog}
		</>
	);
};
