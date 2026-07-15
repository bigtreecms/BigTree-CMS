import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { Check, Download, EyeOff, Link2, Plus, Trash, Upload, X } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Pager } from "@/components/ui/Pager";
import { SearchInput } from "@/components/ui/SearchInput";
import { SubNav } from "@/components/ui/SubNav";
import { TextInput } from "@/components/ui/TextInput";
import { IconButton } from "@/components/ui/IconButton";

import {
	fourOhFoursApi,
	type FourOhFour,
	type FourOhFourType,
} from "@/api/endpoints/four-oh-fours";

import { downloadCsv } from "@/lib/csv";
import { derivePagination } from "@/lib/pagination";
import { formatNumber, pluralize } from "@/lib/number";
import { todayStamp } from "@/lib/time";
import { toast } from "@/lib/toast";
import { queryKeys } from "@/lib/queryKeys";
import { usePaginatedSearch } from "@/hooks/usePaginatedSearch";
import { useToastMutation } from "@/hooks/useToastMutation";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useToggleSet } from "@/hooks/useToggleSet";

/**
 * /dashboard/404s — the 404 Report.
 *
 *   Three route-driven buckets (passed in as `type`), each with its own URL,
 *   title and breadcrumb:
 *     - /dashboard/404s          → Active 404s
 *     - /dashboard/404s/ignored  → Ignored 404s
 *     - /dashboard/404s/301      → 301 Redirects
 *
 *   - Per-row inline "Set redirect" editor: clicking the link icon swaps the
 *     row into a small text input + save / cancel pair.
 *   - Bulk select via checkboxes + Bulk delete + Clear dead (server prune).
 *   - Every bucket can export its full contents to CSV. The 301 bucket also
 *     offers a manual "Add 301" form and a guided "Import CSV" flow.
 */

const PER_PAGE = 25;

interface FourOhFoursProps {
	type: FourOhFourType;
}

const TYPE_LABEL: Record<FourOhFourType, string> = {
	"404": "Active 404s",
	ignored: "Ignored 404s",
	"301": "301 Redirects",
};

const TYPE_ROUTE: Record<FourOhFourType, string> = {
	"404": "/dashboard/404s",
	ignored: "/dashboard/404s/ignored",
	"301": "/dashboard/404s/301",
};

export const FourOhFours = ({ type }: FourOhFoursProps) => {
	const navigate = useNavigate();
	const {
		query: search,
		setQuery: setSearch,
		page,
		setPage,
		debouncedQuery,
	} = usePaginatedSearch();
	const debounced = debouncedQuery.trim();
	const { set: selected, toggle: toggleRow, setSet: setSelected } = useToggleSet<number>();
	const [editingRedirectId, setEditingRedirectId] = useState<number | null>(null);
	const [redirectDraft, setRedirectDraft] = useState("");
	const bulkDeleteDialog = useConfirmDialog<true>();
	const clearDeadDialog = useConfirmDialog<true>();

	// Clears the multi-select + inline redirect editor — run from every handler
	// that changes which rows are on screen, instead of chaining off page/search.
	const resetRowState = () => {
		setSelected(new Set());
		setEditingRedirectId(null);
		setRedirectDraft("");
	};

	// Switching buckets (the `type` prop, driven by the route) resets pagination
	// and row state during render — no effect, so it can't cascade into another.
	const [prevType, setPrevType] = useState(type);

	if (type !== prevType) {
		setPrevType(type);
		setPage(1);
		resetRowState();
	}

	useEffect(() => {
		setSelected(new Set());
		setEditingRedirectId(null);
		setRedirectDraft("");
	}, [debounced, setSelected]);

	const listQ = useQuery({
		queryKey: queryKeys.redirects.list({ type, page, per_page: PER_PAGE, q: debounced }),
		queryFn: () =>
			fourOhFoursApi.list({ type, page, per_page: PER_PAGE, q: debounced || undefined }),
		placeholderData: keepPreviousData,
	});

	const { rows, total, totalPages } = derivePagination({
		rows: listQ.data?.data,
		meta: listQ.data?.meta,
		page,
	});

	const deleteMutation = useToastMutation({
		mutationFn: (id: number) => fourOhFoursApi.delete(id),
		invalidate: [["404s"], ["dashboard"]],
		successMessage: "Entry deleted",
		errorMessage: "Could not delete entry",
	});

	const ignoreMutation = useToastMutation({
		mutationFn: (id: number) => fourOhFoursApi.ignore(id),
		invalidate: [["404s"], ["dashboard"]],
		successMessage: "Entry ignored",
		errorMessage: "Could not ignore entry",
	});

	const setRedirectMutation = useToastMutation({
		mutationFn: ({ id, url }: { id: number; url: string }) =>
			fourOhFoursApi.setRedirect(id, url),
		invalidate: [["404s"], ["dashboard"]],
		successMessage: "Redirect saved",
		errorMessage: "Could not save redirect",
		onSuccess: () => {
			setEditingRedirectId(null);
			setRedirectDraft("");
		},
	});

	const bulkDeleteMutation = useToastMutation({
		mutationFn: (ids: number[]) => fourOhFoursApi.bulkDelete(ids),
		invalidate: [["404s"], ["dashboard"]],
		successMessage: "Selected entries deleted",
		errorMessage: "Bulk delete failed",
		onSuccess: () => {
			setSelected(new Set());
			bulkDeleteDialog.close();
		},
	});

	const clearDeadMutation = useToastMutation({
		mutationFn: () => fourOhFoursApi.clearDead(),
		invalidate: [["404s"], ["dashboard"]],
		errorMessage: "Could not clear dead 404s",
		onSuccess: (result) => {
			clearDeadDialog.close();
			toast.success(`Cleared ${pluralize(result.deleted, "dead 404")}`);
		},
	});

	const exportMutation = useToastMutation({
		mutationFn: () => fourOhFoursApi.export(type),
		errorMessage: "CSV export failed",
		onSuccess: ({ data: rows, meta }) => {
			if (rows.length === 0) {
				toast.error("Nothing to export.");

				return;
			}

			downloadCsv(
				`${type}-report-${todayStamp()}.csv`,
				["Requests", "Broken URL", "Query Variables", "Redirect", "Ignored"],
				rows.map((r) => [
					r.requests,
					r.broken_url,
					r.get_vars ? `?${r.get_vars}` : "",
					r.redirect_url,
					r.ignored ? "Yes" : "No",
				])
			);

			if (meta?.capped) {
				toast.warning(
					`Export capped at the ${formatNumber(Number(meta.max))} most-requested entries.`
				);

				return;
			}

			toast.success(`Exported ${rows.length} ${rows.length === 1 ? "entry" : "entries"}`);
		},
	});

	const toggleAll = () => {
		setSelected((prev) => {
			if (prev.size === rows.length) {
				return new Set();
			}

			return new Set(rows.map((r) => r.id));
		});
	};

	const columns: DataTableColumn<FourOhFour>[] = [
		{
			key: "select",
			header: (
				<Checkbox
					checked={rows.length > 0 && selected.size === rows.length}
					label="Select all"
					labelClassName="sr-only"
					size="sm"
					onChange={toggleAll}
				/>
			),
			width: "32px",
			cell: (row) => (
				<Checkbox
					checked={selected.has(row.id)}
					label="Select row"
					labelClassName="sr-only"
					size="sm"
					onChange={() => toggleRow(row.id)}
				/>
			),
		},
		{
			key: "broken",
			header: "Broken URL",
			width: "minmax(0,1.4fr)",
			cell: (row) => (
				<span
					className="block truncate font-mono text-[11.5px] text-text-2"
					title={row.broken_url}
				>
					{row.broken_url}
				</span>
			),
		},
		{
			key: "redirect",
			header: "Redirect to",
			width: "minmax(0,1.6fr)",
			cell: (row) => {
				const isEditing = editingRedirectId === row.id;

				if (isEditing) {
					return (
						<div
							className="flex items-center gap-1"
							onClick={(e) => e.stopPropagation()}
						>
							<TextInput
								autoFocus
								compact
								mono
								aria-label="Redirect URL"
								className="flex-1"
								value={redirectDraft}
								onChange={(e) => setRedirectDraft(e.target.value)}
								onKeyDown={(e) => {
									if (e.key === "Enter") {
										e.preventDefault();
										setRedirectMutation.mutate({
											id: row.id,
											url: redirectDraft,
										});
									} else if (e.key === "Escape") {
										setEditingRedirectId(null);
									}
								}}
							/>
							<IconButton
								label="Save"
								title="Save"
								tone="accent"
								onClick={() =>
									setRedirectMutation.mutate({ id: row.id, url: redirectDraft })
								}
							>
								<Check size={13} />
							</IconButton>
							<IconButton
								label="Cancel"
								title="Cancel"
								onClick={() => setEditingRedirectId(null)}
							>
								<X size={13} />
							</IconButton>
						</div>
					);
				}

				if (row.redirect_url) {
					return (
						<span
							className="block truncate font-mono text-[11.5px] text-text-2"
							title={row.redirect_url}
						>
							{row.redirect_url}
						</span>
					);
				}

				return <span className="text-[11.5px] text-text-3">—</span>;
			},
		},
		{
			key: "requests",
			header: "Hits",
			width: "70px",
			hideOnMobile: true,
			align: "right",
			headerAlign: "right",
			cell: (row) => (
				<span className="tabular-nums text-[11.5px] text-text-3">{row.requests}</span>
			),
		},
		{
			key: "actions",
			header: "",
			width: "120px",
			align: "right",
			cell: (row) => (
				<div
					className="flex items-center justify-end gap-1"
					onClick={(e) => e.stopPropagation()}
				>
					<IconButton
						label="Set redirect"
						title="Set redirect"
						onClick={() => {
							setEditingRedirectId(row.id);
							setRedirectDraft(row.redirect_url);
						}}
					>
						<Link2 size={13} />
					</IconButton>
					{!row.ignored && (
						<IconButton
							label="Ignore"
							title="Ignore"
							onClick={() => ignoreMutation.mutate(row.id)}
						>
							<EyeOff size={13} />
						</IconButton>
					)}
					<IconButton
						label="Delete"
						title="Delete"
						tone="danger"
						onClick={() => deleteMutation.mutate(row.id)}
					>
						<Trash size={13} />
					</IconButton>
				</div>
			),
		},
	];

	const selectedCount = selected.size;

	return (
		<PageContainer width="wide">
			<Breadcrumb
				items={[
					{ label: "Dashboard", to: "/dashboard" },
					{ label: "404 Report", to: "/dashboard/404s" },
					{ label: TYPE_LABEL[type] },
				]}
			/>

			<PageHead
				actions={
					<>
						<Button
							icon={<Download size={13} />}
							loading={exportMutation.isPending}
							loadingLabel="Exporting…"
							onClick={() => exportMutation.mutate()}
						>
							Export CSV
						</Button>

						{type === "301" && (
							<>
								<Button
									icon={<Upload size={13} />}
									onClick={() => navigate("/dashboard/404s/301/import")}
								>
									Import CSV
								</Button>

								<Button
									icon={<Plus size={13} />}
									variant="primary"
									onClick={() => navigate("/dashboard/404s/301/add")}
								>
									Add 301
								</Button>
							</>
						)}

						{type === "404" && (
							<Button
								icon={<Trash size={13} />}
								onClick={() => clearDeadDialog.open(true)}
							>
								Clear dead
							</Button>
						)}
					</>
				}
				sub={total === 1 ? "1 entry" : `${formatNumber(total)} entries`}
				title={TYPE_LABEL[type]}
			/>

			<div className="mb-3 flex flex-wrap items-center gap-3">
				<SubNav<FourOhFourType>
					items={[
						{ value: "404", label: TYPE_LABEL["404"] },
						{ value: "ignored", label: TYPE_LABEL.ignored },
						{ value: "301", label: TYPE_LABEL["301"] },
					]}
					value={type}
					onChange={(v) => navigate(TYPE_ROUTE[v])}
				/>

				<SearchInput
					className="w-full sm:w-auto sm:max-w-md sm:flex-1"
					placeholder="Search by URL…"
					value={search}
					onChange={setSearch}
				/>

				<div className="hidden flex-1 sm:block" />

				{selectedCount > 0 && (
					<Button
						icon={<Trash size={13} />}
						variant="dangerGhost"
						onClick={() => bulkDeleteDialog.open(true)}
					>
						Delete {selectedCount}
					</Button>
				)}
			</div>

			<DataTable<FourOhFour>
				columns={columns}
				emptyLabel={
					debounced
						? `No ${TYPE_LABEL[type].toLowerCase()} match “${debounced}”.`
						: `No ${TYPE_LABEL[type].toLowerCase()} recorded.`
				}
				getRowKey={(row) => row.id}
				isLoading={listQ.isLoading || (listQ.isFetching && !listQ.data)}
				loadingLabel="Loading…"
				rows={rows}
			/>

			<div className="mt-3 flex justify-end">
				<Pager
					page={page}
					totalPages={totalPages}
					onChange={(p) => {
						setPage(p);
						resetRowState();
					}}
				/>
			</div>

			<ConfirmDialog
				{...bulkDeleteDialog.dialogProps}
				confirmLabel="Delete"
				description="They can be re-captured the next time the broken URL is requested, but any redirects you'd set up on them will be lost."
				title={`Delete ${selectedCount} entries?`}
				variant="danger"
				onConfirm={() => bulkDeleteMutation.mutate(Array.from(selected))}
			/>

			<ConfirmDialog
				{...clearDeadDialog.dialogProps}
				confirmLabel="Clear"
				description="Deletes unredirected 404 entries with fewer than 5 recorded hits — usually one-off typos and crawler noise."
				title="Clear dead 404s?"
				variant="danger"
				onConfirm={() => clearDeadMutation.mutate()}
			/>
		</PageContainer>
	);
};
