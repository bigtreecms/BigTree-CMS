import { useEffect, useState } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { Eye } from "lucide-react";

import { useAuthStore } from "@/auth/store";
import { authApi } from "@/auth/endpoints";
import { DebugLayout } from "@/components/developer/DebugLayout";
import { Button } from "@/components/ui/Button";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { MonoText } from "@/components/ui/MonoText";
import { Pager } from "@/components/ui/Pager";
import { SearchInput } from "@/components/ui/SearchInput";
import { Toolbar } from "@/components/ui/Toolbar";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { usersApi, type UserListItem, levelToLabel } from "@/api/endpoints/users";
import { adminPath } from "@/lib/adminBoot";
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
			window.location.assign(adminPath("/dashboard"));
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
					<Button
						disabled={isSelf || emulateMutation.isPending}
						icon={<Eye size={12} />}
						size="sm"
						variant="secondary"
						onClick={(e) => {
							e.stopPropagation();
							emulateDialog.open(row);
						}}
					>
						{isSelf ? "You" : "Emulate"}
					</Button>
				);
			},
		},
	];

	return (
		<DebugLayout
			sub="Assume another user's identity to debug permissions. You drop to their access level until you stop."
			title="User emulator"
		>
			<Toolbar
				search={
					<SearchInput
						placeholder="Search by name, email, company…"
						value={query}
						onChange={setQuery}
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
					emptyLabel="No users match this search."
					getRowKey={(row) => row.id}
					isLoading={listQ.isLoading}
					loadingLabel="Loading users…"
					rows={rows}
				/>
			)}

			{totalPages > 1 && (
				<div className="mt-3 flex justify-end">
					<Pager page={page} totalPages={totalPages} onChange={setPage} />
				</div>
			)}

			<ConfirmDialog
				{...emulateDialog.dialogProps}
				confirmLabel="Emulate user"
				description="You'll be signed in as this user with their exact permissions. A banner stays on screen so you can return to your own account at any time."
				title={`Emulate ${emulateDialog.item?.name || emulateDialog.item?.email}?`}
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
