import { useEffect, useState } from "react";
import { keepPreviousData, useMutation, useQuery } from "@tanstack/react-query";
import { Eye, Search, X } from "lucide-react";

import { useAuthStore } from "@/auth/store";
import { authApi } from "@/auth/endpoints";
import { DebugLayout } from "@/components/developer/DebugLayout";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Pager } from "@/components/ui/Pager";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { usersApi, type UserListItem, levelToLabel } from "@/api/endpoints/users";
import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

const PER_PAGE = 15;

/**
 * Developer → Debug → User emulator. Pick a user to assume their identity for
 * debugging permissions. Emulating mints a fresh token set for the target,
 * parks the developer's own session client-side, and hard-reloads into the
 * dashboard so every query refetches under the emulated identity. The banner
 * (rendered in the shell) is the way back.
 */
export const DebugEmulator = () => {
	const currentUserId = useAuthStore((s) => s.user?.id);

	const [query, setQuery] = useState("");
	const [page, setPage] = useState(1);
	const [pendingEmulate, setPendingEmulate] = useState<UserListItem | null>(null);

	useEffect(() => {
		setPage(1);
	}, [query]);

	const listQ = useQuery({
		queryKey: ["users", "list", { page, q: query }],
		queryFn: () => usersApi.list({ q: query || undefined, page, per_page: PER_PAGE }),
		placeholderData: keepPreviousData,
	});

	const rows = listQ.data?.items ?? [];
	const meta = listQ.data?.meta ?? {};
	const total = meta.total ?? rows.length;
	const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));

	const emulateMutation = useMutation({
		mutationFn: (userId: number) => authApi.emulate(userId),
		onSuccess: () => {
			// Full reload under the emulated session — resets all query caches.
			const base = import.meta.env.PROD ? "/admin/spa/dashboard" : "/dashboard";
			window.location.assign(base);
		},
		onError: (err) => {
			const msg =
				err instanceof ApiError && err.message ? err.message : "Could not emulate user";
			toast.error(msg);
		},
	});

	const columns: DataTableColumn<UserListItem>[] = [
		{
			key: "name",
			header: "User",
			width: "minmax(0,1.6fr)",
			cell: (row) => (
				<div className="min-w-0">
					<div className="truncate font-medium text-text">{row.name || "—"}</div>
					<div className="truncate font-mono text-[11px] text-text-3">{row.email}</div>
				</div>
			),
		},
		{
			key: "company",
			header: "Company",
			width: "minmax(0,1fr)",
			hideOnMobile: true,
			cell: (row) => <span className="text-text-2">{row.company || "—"}</span>,
		},
		{
			key: "level",
			header: "Level",
			width: "140px",
			hideOnMobile: true,
			cell: (row) => <span className="text-text-3">{levelToLabel(row.level)}</span>,
		},
		{
			key: "actions",
			header: "",
			width: "130px",
			align: "right",
			headerAlign: "right",
			cell: (row) => {
				const isSelf = row.id === currentUserId;

				return (
					<button
						type="button"
						disabled={isSelf || emulateMutation.isPending}
						onClick={(e) => {
							e.stopPropagation();
							setPendingEmulate(row);
						}}
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 py-1 text-[12px] font-medium text-text-2 hover:border-border-strong hover:bg-hover disabled:opacity-50"
					>
						<Eye size={12} />
						{isSelf ? "You" : "Emulate"}
					</button>
				);
			},
		},
	];

	return (
		<DebugLayout
			title="User emulator"
			sub="Assume another user's identity to debug permissions. You drop to their access level until you stop."
		>
			<div className="mb-3 flex flex-wrap items-center gap-3">
				<div className="relative max-w-md flex-1">
					<Search
						size={14}
						className="absolute left-3 top-1/2 -translate-y-1/2 text-text-3"
					/>
					<input
						className="w-full rounded-md border border-border bg-surface py-1.5 pl-9 pr-9 text-[13.5px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring"
						placeholder="Search by name, email, company…"
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

				<span className="text-[12px] text-text-3 tabular-nums">{total} users</span>
			</div>

			{listQ.error ? (
				<ErrorPanel error={listQ.error} />
			) : (
				<DataTable
					columns={columns}
					rows={rows}
					getRowKey={(row) => row.id}
					isLoading={listQ.isLoading}
					loadingLabel="Loading users…"
					emptyLabel="No users match this search."
				/>
			)}

			{totalPages > 1 && (
				<div className="mt-3 flex justify-end">
					<Pager page={page} totalPages={totalPages} onChange={setPage} />
				</div>
			)}

			<ConfirmDialog
				open={pendingEmulate !== null}
				onOpenChange={(open) => {
					if (!open) {
						setPendingEmulate(null);
					}
				}}
				title={`Emulate ${pendingEmulate?.name || pendingEmulate?.email}?`}
				description="You'll be signed in as this user with their exact permissions. A banner stays on screen so you can return to your own account at any time."
				confirmLabel="Emulate user"
				onConfirm={() => {
					if (pendingEmulate) {
						emulateMutation.mutate(pendingEmulate.id);
						setPendingEmulate(null);
					}
				}}
			/>
		</DebugLayout>
	);
};
