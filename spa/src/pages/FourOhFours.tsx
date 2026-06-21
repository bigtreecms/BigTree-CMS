import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Check, Download, EyeOff, Link2, Plus, Trash, Upload, X } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { Button } from "@/components/ui/Button";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Pager } from "@/components/ui/Pager";
import { SearchInput } from "@/components/ui/SearchInput";
import { SubNav } from "@/components/ui/SubNav";

import {
	fourOhFoursApi,
	type FourOhFour,
	type FourOhFourType,
} from "@/api/endpoints/four-oh-fours";

import { ApiError } from "@/types/api";
import { downloadCsv } from "@/lib/csv";
import { toast } from "@/lib/toast";

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
	const queryClient = useQueryClient();
	const navigate = useNavigate();
	const [search, setSearch] = useState("");
	const [debounced, setDebounced] = useState("");
	const [page, setPage] = useState(1);
	const [selected, setSelected] = useState<Set<number>>(new Set());
	const [editingRedirectId, setEditingRedirectId] = useState<number | null>(null);
	const [redirectDraft, setRedirectDraft] = useState("");
	const [confirmBulkDelete, setConfirmBulkDelete] = useState(false);
	const [confirmClearDead, setConfirmClearDead] = useState(false);

	useEffect(() => {
		const handle = setTimeout(() => {
			setDebounced(search.trim());
			setPage(1);
		}, 200);

		return () => clearTimeout(handle);
	}, [search]);

	// Reset selection + redirect editor when the bucket / page / search changes.
	useEffect(() => {
		setSelected(new Set());
		setEditingRedirectId(null);
		setRedirectDraft("");
	}, [type, page, debounced]);

	// Switching buckets resets pagination back to the first page.
	useEffect(() => {
		setPage(1);
	}, [type]);

	const listQ = useQuery({
		queryKey: ["404s", "list", { type, page, per_page: PER_PAGE, q: debounced }],
		queryFn: () =>
			fourOhFoursApi.list({ type, page, per_page: PER_PAGE, q: debounced || undefined }),
		placeholderData: keepPreviousData,
	});

	const rows = listQ.data?.data ?? [];
	const total = (listQ.data?.meta?.total as number | undefined) ?? rows.length;
	const totalPages = (listQ.data?.meta?.pages as number | undefined) ?? 1;

	const invalidate = () => {
		queryClient.invalidateQueries({ queryKey: ["404s"] });
		queryClient.invalidateQueries({ queryKey: ["dashboard"] });
	};

	const deleteMutation = useMutation({
		mutationFn: (id: number) => fourOhFoursApi.delete(id),
		onSuccess: () => {
			invalidate();
			toast.success("Entry deleted");
		},
		onError: (err) => apiToast(err, "Could not delete entry"),
	});

	const ignoreMutation = useMutation({
		mutationFn: (id: number) => fourOhFoursApi.ignore(id),
		onSuccess: () => {
			invalidate();
			toast.success("Entry ignored");
		},
		onError: (err) => apiToast(err, "Could not ignore entry"),
	});

	const setRedirectMutation = useMutation({
		mutationFn: ({ id, url }: { id: number; url: string }) =>
			fourOhFoursApi.setRedirect(id, url),
		onSuccess: () => {
			setEditingRedirectId(null);
			setRedirectDraft("");
			invalidate();
			toast.success("Redirect saved");
		},
		onError: (err) => apiToast(err, "Could not save redirect"),
	});

	const bulkDeleteMutation = useMutation({
		mutationFn: (ids: number[]) => fourOhFoursApi.bulkDelete(ids),
		onSuccess: () => {
			setSelected(new Set());
			setConfirmBulkDelete(false);
			invalidate();
			toast.success("Selected entries deleted");
		},
		onError: (err) => apiToast(err, "Bulk delete failed"),
	});

	const clearDeadMutation = useMutation({
		mutationFn: () => fourOhFoursApi.clearDead(),
		onSuccess: (result) => {
			setConfirmClearDead(false);
			invalidate();
			toast.success(`Cleared ${result.deleted} dead 404${result.deleted === 1 ? "" : "s"}`);
		},
		onError: (err) => apiToast(err, "Could not clear dead 404s"),
	});

	const exportMutation = useMutation({
		mutationFn: () => fourOhFoursApi.export(type),
		onSuccess: ({ data: rows, meta }) => {
			if (rows.length === 0) {
				toast.error("Nothing to export.");

				return;
			}

			downloadCsv(
				`${type}-report-${new Date().toISOString().slice(0, 10)}.csv`,
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
					`Export capped at the ${Number(meta.max).toLocaleString()} most-requested entries.`
				);

				return;
			}

			toast.success(`Exported ${rows.length} ${rows.length === 1 ? "entry" : "entries"}`);
		},
		onError: (err) => apiToast(err, "CSV export failed"),
	});

	const toggleRow = (id: number) => {
		setSelected((prev) => {
			const next = new Set(prev);

			if (next.has(id)) {
				next.delete(id);
			} else {
				next.add(id);
			}

			return next;
		});
	};

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
				<input
					type="checkbox"
					checked={rows.length > 0 && selected.size === rows.length}
					onChange={toggleAll}
					aria-label="Select all"
					className="size-3.5 accent-accent"
				/>
			),
			width: "32px",
			cell: (row) => (
				<input
					type="checkbox"
					checked={selected.has(row.id)}
					onChange={(e) => {
						e.stopPropagation();
						toggleRow(row.id);
					}}
					onClick={(e) => e.stopPropagation()}
					aria-label="Select row"
					className="size-3.5 accent-accent"
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
							<input
								type="text"
								className="flex-1 rounded-md border border-border bg-surface px-2 py-1 font-mono text-[11.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
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
								autoFocus
							/>
							<button
								type="button"
								className="rounded p-1 text-text-3 hover:bg-hover hover:text-accent"
								onClick={() =>
									setRedirectMutation.mutate({ id: row.id, url: redirectDraft })
								}
								title="Save"
							>
								<Check size={13} />
							</button>
							<button
								type="button"
								className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
								onClick={() => setEditingRedirectId(null)}
								title="Cancel"
							>
								<X size={13} />
							</button>
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
					<button
						type="button"
						className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
						onClick={() => {
							setEditingRedirectId(row.id);
							setRedirectDraft(row.redirect_url);
						}}
						title="Set redirect"
					>
						<Link2 size={13} />
					</button>
					{!row.ignored && (
						<button
							type="button"
							className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
							onClick={() => ignoreMutation.mutate(row.id)}
							title="Ignore"
						>
							<EyeOff size={13} />
						</button>
					)}
					<button
						type="button"
						className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
						onClick={() => deleteMutation.mutate(row.id)}
						title="Delete"
					>
						<Trash size={13} />
					</button>
				</div>
			),
		},
	];

	const selectedCount = selected.size;

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Dashboard", to: "/dashboard" },
					{ label: "404 Report", to: "/dashboard/404s" },
					{ label: TYPE_LABEL[type] },
				]}
			/>

			<PageHead
				title={TYPE_LABEL[type]}
				sub={total === 1 ? "1 entry" : `${total.toLocaleString()} entries`}
				actions={
					<>
						<Button
							icon={<Download size={13} />}
							disabled={exportMutation.isPending}
							onClick={() => exportMutation.mutate()}
						>
							{exportMutation.isPending ? "Exporting…" : "Export CSV"}
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
									variant="primary"
									icon={<Plus size={13} />}
									onClick={() => navigate("/dashboard/404s/301/add")}
								>
									Add 301
								</Button>
							</>
						)}

						{type === "404" && (
							<Button
								icon={<Trash size={13} />}
								onClick={() => setConfirmClearDead(true)}
							>
								Clear dead
							</Button>
						)}
					</>
				}
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
					value={search}
					onChange={setSearch}
					placeholder="Search by URL…"
					className="w-full sm:w-auto sm:max-w-md sm:flex-1"
				/>

				<div className="hidden flex-1 sm:block" />

				{selectedCount > 0 && (
					<Button
						variant="dangerGhost"
						icon={<Trash size={13} />}
						onClick={() => setConfirmBulkDelete(true)}
					>
						Delete {selectedCount}
					</Button>
				)}
			</div>

			<DataTable<FourOhFour>
				columns={columns}
				rows={rows}
				getRowKey={(row) => row.id}
				isLoading={listQ.isLoading || (listQ.isFetching && !listQ.data)}
				loadingLabel="Loading…"
				emptyLabel={
					debounced
						? `No ${TYPE_LABEL[type].toLowerCase()} match “${debounced}”.`
						: `No ${TYPE_LABEL[type].toLowerCase()} recorded.`
				}
			/>

			{totalPages > 1 && (
				<div className="mt-3 flex justify-end">
					<Pager page={page} totalPages={totalPages} onChange={setPage} />
				</div>
			)}

			{confirmBulkDelete && (
				<ConfirmDialog
					open
					onOpenChange={setConfirmBulkDelete}
					title={`Delete ${selectedCount} entries?`}
					description="They can be re-captured the next time the broken URL is requested, but any redirects you'd set up on them will be lost."
					confirmLabel="Delete"
					variant="danger"
					onConfirm={() => bulkDeleteMutation.mutate(Array.from(selected))}
				/>
			)}

			{confirmClearDead && (
				<ConfirmDialog
					open
					onOpenChange={setConfirmClearDead}
					title="Clear dead 404s?"
					description="Deletes unredirected 404 entries with fewer than 5 recorded hits — usually one-off typos and crawler noise."
					confirmLabel="Clear"
					variant="danger"
					onConfirm={() => clearDeadMutation.mutate()}
				/>
			)}
		</div>
	);
};

const apiToast = (err: unknown, fallback: string) => {
	const message = err instanceof ApiError && err.message ? err.message : fallback;
	toast.error(message);
};
