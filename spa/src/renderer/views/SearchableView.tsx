import { useEffect, useMemo, useState } from "react";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Edit, Search, Trash, X } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";

import { DataTable, type DataTableColumn, type DataTableSort } from "@/components/ui/DataTable";
import { Pager } from "@/components/ui/Pager";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";

import {
	autoModulesApi,
	type ModuleEntriesListParams,
	type ModuleEntryRow,
} from "@/api/endpoints/auto-modules";
import type { ModuleView } from "@/api/endpoints/modules";
import { toast } from "@/lib/toast";

import {
	columnWidth,
	formatCellValue,
	formatSortParam,
	iconForCustomAction,
	parseSortSetting,
	parseViewActions,
} from "./viewHelpers";
import { BuiltinToggleButtons } from "./BuiltinToggleButtons";

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
	const queryClient = useQueryClient();
	const navigate = useNavigate();
	const [page, setPage] = useState(1);
	const [query, setQuery] = useState("");
	const [debouncedQuery, setDebouncedQuery] = useState("");
	const [sort, setSort] = useState<DataTableSort | undefined>(() => parseSortSetting(view));
	const [confirmDelete, setConfirmDelete] = useState<ModuleEntryRow | null>(null);

	useEffect(() => {
		const handle = window.setTimeout(() => setDebouncedQuery(query), 200);

		return () => window.clearTimeout(handle);
	}, [query]);

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
		queryKey: ["module-entries", moduleId, view.id, params] as const,
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

	const deleteMutation = useMutation({
		mutationFn: (entryId: number) => autoModulesApi.delete(moduleId, entryId),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["module-entries", moduleId, view.id] });
			toast.success("Entry deleted");
		},
		onError: () => {
			toast.error("Could not delete entry");
		},
		onSettled: () => {
			setConfirmDelete(null);
		},
	});

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
				cell: (row) => (
					<span className={field.numeric ? "tabular-nums text-text-2" : "text-text-2"}>
						{formatCellValue(row[valueKey])}
					</span>
				),
			};
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
					const entryId = Number(row.id);
					const canMutate = Number.isFinite(entryId) && entryId > 0;

					return (
						<div className="flex w-full items-center justify-end gap-1">
							{custom.map((action) => {
								const Icon = iconForCustomAction(action.className);

								return (
									<Link
										key={action.key}
										to={`/modules/${moduleId}/view/${view.id}/${action.route}/${entryId}`}
										className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
										title={action.name}
										aria-label={action.name}
										onClick={(e) => e.stopPropagation()}
									>
										<Icon size={15} />
									</Link>
								);
							})}

							{builtins.edit && (
								<Link
									to={`/modules/${moduleId}/view/${view.id}/edit/${entryId}`}
									className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
									title="Edit"
									aria-label="Edit"
									onClick={(e) => e.stopPropagation()}
								>
									<Edit size={15} />
								</Link>
							)}

							<BuiltinToggleButtons
								moduleId={moduleId}
								viewId={view.id}
								row={row}
								builtins={builtins}
							/>

							{builtins.delete && (
								<button
									type="button"
									className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger disabled:opacity-40"
									title="Delete"
									aria-label="Delete"
									disabled={!canMutate}
									onClick={(e) => {
										e.stopPropagation();
										setConfirmDelete(row);
									}}
								>
									<Trash size={15} />
								</button>
							)}
						</div>
					);
				},
			});
		}

		return cols;
	}, [fieldColumns, hasRowActions, builtins, custom, moduleId, view.id]);

	const rows = listQuery.data?.items ?? [];
	const meta = listQuery.data?.meta;
	const totalPages = Math.max(1, meta?.pages ?? 1);
	const safePage = Math.min(page, totalPages);

	const onSortChange = (next: DataTableSort) => {
		setSort(next);
		setPage(1);
	};

	const onRowClick = (row: ModuleEntryRow) => {
		if (!builtins.edit) {
			return;
		}

		const entryId = Number(row.id);

		if (!Number.isFinite(entryId) || entryId <= 0) {
			return;
		}

		navigate(`/modules/${moduleId}/view/${view.id}/edit/${entryId}`);
	};

	return (
		<>
			<div className="mb-3 flex flex-wrap items-center gap-3">
				<div className="relative max-w-md flex-1">
					<Search
						size={14}
						className="absolute left-3 top-1/2 -translate-y-1/2 text-text-3"
					/>
					<input
						className="w-full rounded-md border border-border bg-surface py-1.5 pl-9 pr-9 text-[13.5px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring"
						placeholder={`Search ${view.title.toLowerCase()}…`}
						value={query}
						onChange={(e) => setQuery(e.target.value)}
					/>
					{query && (
						<button
							type="button"
							className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-text-3 hover:bg-hover hover:text-text"
							onClick={() => setQuery("")}
							aria-label="Clear search"
						>
							<X size={14} />
						</button>
					)}
				</div>

				<div className="flex-1" />

				<Pager page={safePage} totalPages={totalPages} onChange={setPage} />
			</div>

			<DataTable<ModuleEntryRow>
				columns={columns}
				rows={rows}
				getRowKey={(row) => row.id as string | number}
				isLoading={listQuery.isLoading && !listQuery.data}
				loadingLabel="Loading entries…"
				emptyLabel={debouncedQuery ? `No entries match “${debouncedQuery}”.` : "No entries yet."}
				sort={sort}
				onSortChange={onSortChange}
				onRowClick={builtins.edit ? onRowClick : undefined}
			/>

			{confirmDelete && (
				<ConfirmDialog
					open={true}
					onOpenChange={(open) => {
						if (!open) {
							setConfirmDelete(null);
						}
					}}
					title="Delete entry?"
					description="This action cannot be undone."
					confirmLabel="Delete"
					variant="danger"
					onConfirm={() => {
						const entryId = Number(confirmDelete.id);

						if (Number.isFinite(entryId) && entryId > 0) {
							deleteMutation.mutate(entryId);
						}
					}}
				/>
			)}
		</>
	);
};
