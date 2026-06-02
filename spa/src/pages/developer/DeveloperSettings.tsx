import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus, Search, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Pager } from "@/components/ui/Pager";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import { settingsApi, type SettingDetail } from "@/api/endpoints/settings";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

/**
 * /developer/settings — admin CRUD for settings definitions.
 *
 *   - List + paginated + ?q= search, same shape as the user-facing /settings.
 *   - Delete + Add. Row click opens the definition (schema) editor at
 *     /developer/settings/:id/edit; the user-facing value editor lives
 *     separately at /settings/:id/edit.
 */
const PER_PAGE = 25;

export const DeveloperSettings = () => {
	const navigate = useNavigate();
	const queryClient = useQueryClient();
	const [search, setSearch] = useState("");
	const [debounced, setDebounced] = useState("");
	const [page, setPage] = useState(1);
	const [confirmDelete, setConfirmDelete] = useState<SettingDetail | null>(null);

	useEffect(() => {
		const handle = setTimeout(() => {
			setDebounced(search.trim());
			setPage(1);
		}, 200);

		return () => clearTimeout(handle);
	}, [search]);

	const query = useQuery({
		queryKey: ["settings", "list", { page, per_page: PER_PAGE, q: debounced, include_system: true }],
		queryFn: () =>
			settingsApi.list({
				page,
				per_page: PER_PAGE,
				q: debounced || undefined,
				include_system: true,
			}),
		placeholderData: keepPreviousData,
	});

	const deleteMutation = useMutation({
		mutationFn: (id: string) => settingsApi.delete(id),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["settings"] });
			setConfirmDelete(null);
			toast.success("Setting deleted");
		},
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Delete failed");
		},
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
					<div className="truncate font-medium text-text">{row.name}</div>
					<div className="truncate font-mono text-[11px] text-text-3">{row.id}</div>
				</div>
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
			width: "minmax(0,1fr)",
			hideOnMobile: true,
			cell: (row) => (
				<div className="flex flex-wrap gap-1">
					{row.encrypted && (
						<span className="rounded bg-info-bg px-1.5 py-0.5 text-[10.5px] font-medium text-info">
							Encrypted
						</span>
					)}
					{row.locked && (
						<span className="rounded bg-warn-bg px-1.5 py-0.5 text-[10.5px] font-medium text-warn">
							Locked
						</span>
					)}
					{row.system && (
						<span className="rounded bg-surface-2 px-1.5 py-0.5 text-[10.5px] font-medium text-text-3">
							System
						</span>
					)}
				</div>
			),
		},
		{
			key: "actions",
			header: "",
			width: "56px",
			align: "right",
			cell: (row) =>
				row.locked ? (
					<span
						className="grid h-7 w-7 place-items-center text-text-3 opacity-40"
						title="Locked — cannot be deleted"
					>
						<Trash size={13} />
					</span>
				) : (
					<button
						type="button"
						className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
						onClick={(e) => {
							e.stopPropagation();
							setConfirmDelete(row);
						}}
						title="Delete setting"
						aria-label="Delete setting"
					>
						<Trash size={13} />
					</button>
				),
		},
	];

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb items={[{ label: "Developer", to: "/developer" }, { label: "Settings" }]} />

			<PageHead
				title="Settings (admin)"
				sub={total === 1 ? "1 setting" : `${total.toLocaleString()} settings`}
				actions={
					<Link
						to="/developer/settings/add"
						className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover"
					>
						<Plus size={13} />
						Add setting
					</Link>
				}
			/>

			<DeveloperSectionNav />

			<div className="mb-3 max-w-md">
				<div className="relative">
					<Search
						size={14}
						className="absolute left-3 top-1/2 -translate-y-1/2 text-text-3"
					/>
					<input
						className="w-full rounded-md border border-border bg-surface py-1.5 pl-9 pr-3 text-[13.5px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring"
						placeholder="Search settings…"
						value={search}
						onChange={(e) => setSearch(e.target.value)}
					/>
				</div>
			</div>

			<DataTable<SettingDetail>
				columns={columns}
				rows={rows}
				getRowKey={(row) => row.id}
				isLoading={query.isLoading || (query.isFetching && !query.data)}
				loadingLabel="Loading…"
				emptyLabel={debounced ? `No settings match “${debounced}”.` : "No settings yet."}
				onRowClick={(row) =>
					navigate(`/developer/settings/${encodeURIComponent(row.id)}/edit`)
				}
			/>

			{totalPages > 1 && (
				<div className="mt-3 flex justify-end">
					<Pager page={page} totalPages={totalPages} onChange={setPage} />
				</div>
			)}

			{confirmDelete && (
				<ConfirmDialog
					open
					onOpenChange={(open) => {
						if (!open) {
							setConfirmDelete(null);
						}
					}}
					title={`Delete "${confirmDelete.name}"?`}
					description="Both the definition and the stored value will be removed."
					confirmLabel="Delete setting"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(confirmDelete.id)}
				/>
			)}
		</div>
	);
};
