import type { DataTableColumn } from "@/components/ui/DataTable";
import { NameIdCell } from "@/components/ui/NameIdCell";
import { Badge } from "@/components/ui/Badge";

import { DeveloperListPage } from "@/components/developer/DeveloperListPage";

import { fieldTypesApi, type FieldType } from "@/api/endpoints/field-types";

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
];

export const FieldTypes = () => (
	<DeveloperListPage<Row>
		title="Field types"
		countNoun="custom field type"
		route="/developer/field-types"
		addLabel="Add custom type"
		loadingLabel="Loading field types…"
		emptyLabel="No custom field types yet."
		queryKey={queryKeys.fieldTypes.split()}
		invalidateKey={queryKeys.fieldTypes.root()}
		list={async () =>
			Object.values((await fieldTypesApi.listSplit()).custom ?? {}).sort((a, b) =>
				a.id.localeCompare(b.id)
			)
		}
		remove={(id) => fieldTypesApi.delete(id)}
		columns={columns}
		getRowKey={(row) => row.id}
		deleteButtonLabel="Delete field type"
		deleteSuccessMessage="Field type deleted"
		rowLabel={(row) => row.name || row.id}
		confirmLabel="Delete"
		confirmDescription="Any field already using this type will fall through to the StubField renderer until it's reassigned."
	/>
);
