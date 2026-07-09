import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { Plus, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Badge } from "@/components/ui/Badge";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { MonoText } from "@/components/ui/MonoText";
import { Button } from "@/components/ui/Button";
import { Pager } from "@/components/ui/Pager";
import { SearchInput } from "@/components/ui/SearchInput";
import { IconButton } from "@/components/ui/IconButton";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import { settingsApi, type SettingDetail } from "@/api/endpoints/settings";

import { formatNumber } from "@/lib/number";
import { queryKeys } from "@/lib/queryKeys";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useDebouncedValue } from "@/hooks/useDebouncedValue";
import { useToastMutation } from "@/hooks/useToastMutation";

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
	const [search, setSearch] = useState("");
	const [page, setPage] = useState(1);
	const deleteDialog = useConfirmDialog<SettingDetail>();
	const debounced = useDebouncedValue(search.trim());

	useEffect(() => {
		setPage(1);
	}, [debounced]);

	const query = useQuery({
		queryKey: queryKeys.settings.list({
			page,
			per_page: PER_PAGE,
			q: debounced,
			include_system: true,
		}),
		queryFn: () =>
			settingsApi.list({
				page,
				per_page: PER_PAGE,
				q: debounced || undefined,
				include_system: true,
			}),
		placeholderData: keepPreviousData,
	});

	const deleteMutation = useToastMutation({
		mutationFn: (id: string) => settingsApi.delete(id),
		invalidate: [["settings"]],
		successMessage: "Setting deleted",
		errorMessage: "Delete failed",
		onSuccess: () => {
			deleteDialog.close();
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
					<MonoText as="div">{row.id}</MonoText>
				</div>
			),
		},
		{
			key: "type",
			header: "Type",
			width: "120px",
			hideOnMobile: true,
			cell: (row) => <Badge size="sm">{row.type || "text"}</Badge>,
		},
		{
			key: "flags",
			header: "Flags",
			width: "minmax(0,1fr)",
			hideOnMobile: true,
			cell: (row) => (
				<div className="flex flex-wrap gap-1">
					{row.encrypted && (
						<Badge size="sm" tone="info">
							Encrypted
						</Badge>
					)}
					{row.locked && (
						<Badge size="sm" tone="warn">
							Locked
						</Badge>
					)}
					{row.system && <Badge size="sm">System</Badge>}
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
						className="grid size-7 place-items-center text-text-3 opacity-40"
						title="Locked — cannot be deleted"
					>
						<Trash size={13} />
					</span>
				) : (
					<IconButton
						tone="danger"
						onClick={(e) => {
							e.stopPropagation();
							deleteDialog.open(row);
						}}
						title="Delete setting"
						label="Delete setting"
					>
						<Trash size={13} />
					</IconButton>
				),
		},
	];

	return (
		<PageContainer width="wide">
			<Breadcrumb items={[{ label: "Developer", to: "/developer" }, { label: "Settings" }]} />

			<PageHead
				title="Settings (admin)"
				sub={total === 1 ? "1 setting" : `${formatNumber(total)} settings`}
				actions={
					<Button
						variant="primary"
						icon={<Plus size={13} />}
						to="/developer/settings/add"
					>
						Add setting
					</Button>
				}
			/>

			<DeveloperSectionNav />

			<div className="mb-3 max-w-md">
				<SearchInput value={search} onChange={setSearch} placeholder="Search settings…" />
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

			{deleteDialog.item && (
				<ConfirmDialog
					open={deleteDialog.isOpen}
					onOpenChange={(open) => {
						if (!open) {
							deleteDialog.close();
						}
					}}
					title={`Delete "${deleteDialog.item.name}"?`}
					description="Both the definition and the stored value will be removed."
					confirmLabel="Delete setting"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(deleteDialog.item!.id)}
				/>
			)}
		</PageContainer>
	);
};
