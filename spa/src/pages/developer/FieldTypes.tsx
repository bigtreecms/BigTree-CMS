import { useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Plus, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Badge } from "@/components/ui/Badge";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { NameIdCell } from "@/components/ui/NameIdCell";
import { Button } from "@/components/ui/Button";
import { IconButton } from "@/components/ui/IconButton";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import { fieldTypesApi, type FieldType } from "@/api/endpoints/field-types";

import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useToastMutation } from "@/hooks/useToastMutation";
import { pluralize } from "@/lib/number";
import { queryKeys } from "@/lib/queryKeys";

/**
 * /developer/field-types — list the custom (user-defined) field types.
 *
 * Built-ins ship with the core install, don't live in the mutable JSONDB, and
 * aren't editable, so they're omitted here. We still use the server's
 * `listSplit` mode because its `custom` bucket is a flat `{ id: FieldType }`
 * map carrying the full type record (the default listing is use-case-nested).
 */
type Row = FieldType;

export const FieldTypes = () => {
	const navigate = useNavigate();
	const deleteDialog = useConfirmDialog<Row>();

	const query = useQuery({
		queryKey: queryKeys.fieldTypes.split(),
		queryFn: () => fieldTypesApi.listSplit(),
	});

	const deleteMutation = useToastMutation({
		mutationFn: (id: string) => fieldTypesApi.delete(id),
		invalidate: [queryKeys.fieldTypes.root()],
		successMessage: "Field type deleted",
		errorMessage: "Delete failed",
		onSuccess: () => {
			deleteDialog.close();
		},
	});

	const rows: Row[] = useMemo(
		() => Object.values(query.data?.custom ?? {}).sort((a, b) => a.id.localeCompare(b.id)),
		[query.data]
	);

	const columns: DataTableColumn<Row>[] = [
		{
			key: "name",
			header: "Name",
			width: "minmax(0,1.5fr)",
			cell: (row) => <NameIdCell name={row.name || row.id} id={row.id} />,
		},
		{
			key: "use_cases",
			header: "Use cases",
			width: "minmax(0,1fr)",
			hideOnMobile: true,
			cell: (row) => {
				const uses = Array.isArray(row.use_cases) ? (row.use_cases as string[]) : [];

				return uses.length === 0 ? (
					<span className="text-text-3">—</span>
				) : (
					<div className="flex flex-wrap gap-1">
						{uses.map((u) => (
							<Badge key={u} size="sm">
								{u}
							</Badge>
						))}
					</div>
				);
			},
		},
		{
			key: "self_draw",
			header: "Self-draw",
			width: "100px",
			hideOnMobile: true,
			cell: (row) =>
				row.self_draw ? (
					<span className="text-[11px] text-info">Yes</span>
				) : (
					<span className="text-text-3">—</span>
				),
		},
		{
			key: "actions",
			header: "",
			width: "56px",
			align: "right",
			cell: (row) => (
				<IconButton
					tone="danger"
					onClick={(e) => {
						e.stopPropagation();
						deleteDialog.open(row);
					}}
					title="Delete field type"
					label="Delete field type"
				>
					<Trash size={13} />
				</IconButton>
			),
		},
	];

	return (
		<PageContainer width="wide">
			<Breadcrumb
				items={[{ label: "Developer", to: "/developer" }, { label: "Field types" }]}
			/>

			<PageHead
				title="Field types"
				sub={pluralize(rows.length, "custom field type")}
				actions={
					<Button
						variant="primary"
						icon={<Plus size={13} />}
						to="/developer/field-types/add"
					>
						Add custom type
					</Button>
				}
			/>

			<DeveloperSectionNav />

			<DataTable<Row>
				columns={columns}
				rows={rows}
				getRowKey={(row) => row.id}
				isLoading={query.isLoading}
				loadingLabel="Loading field types…"
				emptyLabel="No custom field types yet."
				onRowClick={(row) => {
					navigate(`/developer/field-types/${encodeURIComponent(row.id)}/edit`);
				}}
			/>

			{deleteDialog.item && (
				<ConfirmDialog
					open={deleteDialog.isOpen}
					onOpenChange={(open) => {
						if (!open) {
							deleteDialog.close();
						}
					}}
					title={`Delete "${deleteDialog.item.name || deleteDialog.item.id}"?`}
					description="Any field already using this type will fall through to the StubField renderer until it's reassigned."
					confirmLabel="Delete"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(deleteDialog.item!.id)}
				/>
			)}
		</PageContainer>
	);
};
