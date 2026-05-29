import { useMemo, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus, ShieldAlert, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import { fieldTypesApi, type FieldType } from "@/api/endpoints/field-types";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

/**
 * /developer/field-types — list every field type (built-in + custom).
 *
 * The server's `listSplit` mode separates the two; we use it so the table
 * can mark built-ins as un-deletable. Deletion + Add only apply to custom
 * types — the built-ins ship with the core install and don't live in the
 * mutable JSONDB.
 */
type Row = FieldType & { _isBuiltin: boolean };

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

	const rows: Row[] = useMemo(() => {
		const out: Row[] = [];

		for (const ft of Object.values(query.data?.default ?? {})) {
			out.push({ ...ft, _isBuiltin: true });
		}

		for (const ft of Object.values(query.data?.custom ?? {})) {
			out.push({ ...ft, _isBuiltin: false });
		}

		return out.sort((a, b) => a.id.localeCompare(b.id));
	}, [query.data]);

	const columns: DataTableColumn<Row>[] = [
		{
			key: "name",
			header: "Name",
			width: "minmax(0,1.5fr)",
			cell: (row) => (
				<div className="min-w-0">
					<div className="flex items-center gap-2">
						<span className="truncate font-medium text-text">{row.name || row.id}</span>
						{row._isBuiltin && (
							<span className="rounded bg-surface-2 px-1.5 py-0.5 text-[10.5px] font-medium text-text-3">
								Built-in
							</span>
						)}
					</div>
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
			cell: (row) =>
				row._isBuiltin ? (
					<span
						className="grid h-7 w-7 place-items-center text-text-3 opacity-40"
						title="Built-in — cannot be deleted"
					>
						<ShieldAlert size={13} />
					</span>
				) : (
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
				sub={`${rows.length} field types (${Object.keys(query.data?.custom ?? {}).length} custom)`}
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
				emptyLabel="No field types loaded."
				onRowClick={(row) => {
					if (!row._isBuiltin) {
						navigate(`/developer/field-types/${encodeURIComponent(row.id)}/edit`);
					}
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
