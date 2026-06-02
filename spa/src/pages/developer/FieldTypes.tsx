import { useMemo, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import { fieldTypesApi, type FieldType } from "@/api/endpoints/field-types";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

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
	const queryClient = useQueryClient();
	const [confirmDelete, setConfirmDelete] = useState<Row | null>(null);

	const query = useQuery({
		queryKey: ["field-types", "split"],
		queryFn: () => fieldTypesApi.listSplit(),
	});

	const deleteMutation = useMutation({
		mutationFn: (id: string) => fieldTypesApi.delete(id),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["field-types"] });
			setConfirmDelete(null);
			toast.success("Field type deleted");
		},
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Delete failed");
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
			cell: (row) => (
				<div className="min-w-0">
					<div className="truncate font-medium text-text">{row.name || row.id}</div>
					<div className="truncate font-mono text-[11px] text-text-3">{row.id}</div>
				</div>
			),
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
							<span
								key={u}
								className="rounded bg-surface-2 px-1.5 py-0.5 text-[10.5px] text-text-3"
							>
								{u}
							</span>
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
				<button
					type="button"
					className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
					onClick={(e) => {
						e.stopPropagation();
						setConfirmDelete(row);
					}}
					title="Delete field type"
					aria-label="Delete field type"
				>
					<Trash size={13} />
				</button>
			),
		},
	];

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb
				items={[{ label: "Developer", to: "/developer" }, { label: "Field types" }]}
			/>

			<PageHead
				title="Field types"
				sub={`${rows.length} custom field type${rows.length === 1 ? "" : "s"}`}
				actions={
					<Link
						to="/developer/field-types/add"
						className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover"
					>
						<Plus size={13} />
						Add custom type
					</Link>
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

			{confirmDelete && (
				<ConfirmDialog
					open
					onOpenChange={(open) => {
						if (!open) {
							setConfirmDelete(null);
						}
					}}
					title={`Delete "${confirmDelete.name || confirmDelete.id}"?`}
					description="Any field already using this type will fall through to the StubField renderer until it's reassigned."
					confirmLabel="Delete"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(confirmDelete.id)}
				/>
			)}
		</div>
	);
};
