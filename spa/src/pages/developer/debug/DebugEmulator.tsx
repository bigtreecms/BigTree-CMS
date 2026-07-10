import { useEffect, useState } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { Eye } from "lucide-react";

import { useAuthStore } from "@/auth/store";
import { authApi } from "@/auth/endpoints";
import { DebugLayout } from "@/components/developer/DebugLayout";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { MonoText } from "@/components/ui/MonoText";
import { Pager } from "@/components/ui/Pager";
import { SearchInput } from "@/components/ui/SearchInput";
import { Toolbar } from "@/components/ui/Toolbar";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { usersApi, type UserListItem, levelToLabel } from "@/api/endpoints/users";
import { derivePagination } from "@/lib/pagination";
import { queryKeys } from "@/lib/queryKeys";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useToastMutation } from "@/hooks/useToastMutation";

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
	const emulateDialog = useConfirmDialog<UserListItem>();

	useEffect(() => {
		setPage(1);
	}, [query]);

	const listQ = useQuery({
		queryKey: queryKeys.users.list({ page, q: query }),
		queryFn: () => usersApi.list({ q: query || undefined, page, per_page: PER_PAGE }),
		placeholderData: keepPreviousData,
	});

	const { rows, total, totalPages } = derivePagination({
		rows: listQ.data?.items,
		meta: listQ.data?.meta,
		page,
		perPage: PER_PAGE,
	});

	const emulateMutation = useToastMutation({
		mutationFn: (userId: number) => authApi.emulate(userId),
		errorMessage: "Could not emulate user",
		onSuccess: () => {
			// Full reload under the emulated session — resets all query caches.
			const base = import.meta.env.PROD ? "/admin/spa/dashboard" : "/dashboard";
			window.location.assign(base);
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
					<MonoText as="div">{row.email}</MonoText>
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
							emulateDialog.open(row);
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
			<Toolbar
				search={
					<SearchInput
						value={query}
						onChange={setQuery}
						placeholder="Search by name, email, company…"
					/>
				}
			>
				<span className="text-[12px] text-text-3 tabular-nums">{total} users</span>
			</Toolbar>

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
				open={emulateDialog.isOpen}
				onOpenChange={(open) => {
					if (!open) {
						emulateDialog.close();
					}
				}}
				title={`Emulate ${emulateDialog.item?.name || emulateDialog.item?.email}?`}
				description="You'll be signed in as this user with their exact permissions. A banner stays on screen so you can return to your own account at any time."
				confirmLabel="Emulate user"
				onConfirm={() => {
					if (emulateDialog.item) {
						emulateMutation.mutate(emulateDialog.item.id);
						emulateDialog.close();
					}
				}}
			/>
		</DebugLayout>
	);
};
