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
import type { ModuleView, ModuleViewFieldConfig } from "@/api/endpoints/modules";
import { toast } from "@/lib/toast";

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
	moduleId: number;
	view: ModuleView;
}

interface CustomAction {
	key: string;
	name: string;
	route: string;
	className?: string;
}

type BuiltinActionFlags = {
	edit: boolean;
	delete: boolean;
};

const parseViewActions = (
	actions: Record<string, string> | undefined
): { builtins: BuiltinActionFlags; custom: CustomAction[] } => {
	const builtins: BuiltinActionFlags = { edit: false, delete: false };
	const custom: CustomAction[] = [];

	if (!actions) {
		return { builtins, custom };
	}

	for (const [key, raw] of Object.entries(actions)) {
		if (raw === "on") {
			if (key === "edit") {
				builtins.edit = true;
			} else if (key === "delete") {
				builtins.delete = true;
			}

			continue;
		}

		if (typeof raw === "string" && raw.startsWith("{")) {
			try {
				const parsed = JSON.parse(raw) as {
					name?: string;
					route?: string;
					class?: string;
				};

				if (parsed.route) {
					custom.push({
						key,
						name: parsed.name ?? key,
						route: parsed.route,
						className: parsed.class,
					});
				}
			} catch {
				// Malformed action JSON — ignore.
			}
		}
	}

	return { builtins, custom };
};

const parseSortSetting = (view: ModuleView): DataTableSort | undefined => {
	const col = view.settings?.sort_column;

	if (!col) {
		return undefined;
	}

	const dir = (view.settings?.sort_direction ?? "ASC").toString().toUpperCase();

	return { key: col, dir: dir === "DESC" ? "desc" : "asc" };
};

const formatSortParam = (sort: DataTableSort | undefined): string | undefined => {
	if (!sort) {
		return undefined;
	}

	return `${sort.key} ${sort.dir.toUpperCase()}`;
};

const columnWidth = (field: ModuleViewFieldConfig): string => {
	const raw = field.width;

	if (raw === undefined || raw === null || raw === "" || raw === "0") {
		return "minmax(0,1fr)";
	}

	const px = typeof raw === "number" ? raw : Number.parseInt(raw, 10);

	if (!Number.isFinite(px) || px <= 0) {
		return "minmax(0,1fr)";
	}

	return `minmax(0, ${px}px)`;
};

const formatCellValue = (value: unknown): string => {
	if (value === null || value === undefined) {
		return "";
	}

	if (typeof value === "string" || typeof value === "number" || typeof value === "boolean") {
		return String(value);
	}

	if (Array.isArray(value)) {
		return value.length === 0 ? "" : `${value.length} item${value.length === 1 ? "" : "s"}`;
	}

	try {
		return JSON.stringify(value);
	} catch {
		return "";
	}
};

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
	const hasRowActions = builtins.edit || builtins.delete || custom.length > 0;

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
		const cols: DataTableColumn<ModuleEntryRow>[] = fieldColumns.map(([key, field]) => ({
			key,
			header: field.title,
			width: columnWidth(field),
			sortable: true,
			align: field.numeric ? "right" : "left",
			headerAlign: field.numeric ? "right" : "left",
			cell: (row) => (
				<span className={field.numeric ? "tabular-nums text-text-2" : "text-text-2"}>
					{formatCellValue(row[key])}
				</span>
			),
		}));

		if (hasRowActions) {
			const actionsCount = (builtins.edit ? 1 : 0) + (builtins.delete ? 1 : 0) + custom.length;

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
						<div className="flex items-center justify-end gap-1">
							{custom.map((action) => (
								<Link
									key={action.key}
									to={`/modules/${moduleId}/view/${view.id}/${action.route}/${entryId}`}
									className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
									title={action.name}
									aria-label={action.name}
									onClick={(e) => e.stopPropagation()}
								>
									<span className="inline-block min-w-[14px] text-[11px] font-medium">
										{action.name.slice(0, 2)}
									</span>
								</Link>
							))}

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
