import type { ReactNode } from "react";
import { useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Plus, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Button } from "@/components/ui/Button";
import { IconButton } from "@/components/ui/IconButton";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import { pluralize } from "@/lib/number";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useToastMutation } from "@/hooks/useToastMutation";

export interface DeveloperListPageProps<T> {
	/** Add-button label (e.g. "Add callout"). */
	addLabel: string;
	/** Columns WITHOUT the trailing actions column — the component appends it. */
	columns: DataTableColumn<T>[];
	confirmDescription: string;
	/** Confirm-dialog primary button label (e.g. "Delete callout" or just "Delete"). */
	confirmLabel: string;
	/** Singular noun pluralized against the row count for the subtitle (e.g. "callout"). */
	countNoun: string;
	/** Title/label for the row delete IconButton (e.g. "Delete callout"). */
	deleteButtonLabel: string;

	/** Toast shown after a successful delete (e.g. "Callout deleted"). */
	deleteSuccessMessage: string;
	/** Optional derivation from the raw list response (e.g. FieldTypes' registry map → rows). */
	deriveRows?: (data: T[]) => T[];
	emptyLabel: string;
	getRowKey: (row: T) => string;
	invalidateKey: readonly unknown[];

	list: () => Promise<T[]>;
	loadingLabel: string;

	/** Optional reorder handler — passed straight through to DataTable. */
	onReorder?: (orderedKeys: Array<string | number>) => void;
	queryKey: readonly unknown[];
	remove: (id: string) => Promise<void>;
	/** Route base — the Add button links to `${route}/add` and rows to `${route}/${id}/edit`. */
	route: string;
	/** Confirm-dialog title noun for a row (e.g. `(row) => row.name`). */
	rowLabel: (row: T) => string;

	/** Override the row-click destination. Defaults to `${route}/${key}/edit`. */
	rowPath?: (row: T) => string;
	/** Plural display name — used for the breadcrumb and page title (e.g. "Callouts"). */
	title: string;
	/** Escape slot rendered inside PageHead's actions, before the Add button. */
	toolbar?: ReactNode;
}

/**
 * Shared scaffold for the developer index pages (Callouts, Feeds, Templates,
 * FieldTypes, CalloutGroups, ModuleGroups). Owns the list query, the delete
 * dialog + mutation, and the full page chrome; callers supply their columns,
 * entity nouns/routes, and confirm-dialog copy.
 */
export const DeveloperListPage = <T,>({
	title,
	countNoun,
	route,
	addLabel,
	loadingLabel,
	emptyLabel,
	queryKey,
	invalidateKey,
	list,
	remove,
	deriveRows,
	columns,
	getRowKey,
	deleteButtonLabel,
	deleteSuccessMessage,
	rowLabel,
	confirmLabel,
	confirmDescription,
	onReorder,
	toolbar,
	rowPath,
}: DeveloperListPageProps<T>) => {
	const navigate = useNavigate();
	const deleteDialog = useConfirmDialog<T>();

	const query = useQuery({
		queryKey,
		queryFn: () => list(),
	});

	const deleteMutation = useToastMutation({
		mutationFn: (id: string) => remove(id),
		invalidate: [invalidateKey],
		successMessage: deleteSuccessMessage,
		errorMessage: "Delete failed",
		onSuccess: () => {
			deleteDialog.close();
		},
	});

	const data = query.data ?? [];
	const rows = deriveRows ? deriveRows(data) : data;

	const allColumns: DataTableColumn<T>[] = [
		...columns,
		{
			key: "actions",
			header: "",
			width: "56px",
			align: "right",
			cell: (row) => (
				<IconButton
					label={deleteButtonLabel}
					title={deleteButtonLabel}
					tone="danger"
					onClick={(e) => {
						e.stopPropagation();
						deleteDialog.open(row);
					}}
				>
					<Trash size={13} />
				</IconButton>
			),
		},
	];

	return (
		<PageContainer width="wide">
			<Breadcrumb items={[{ label: "Developer", to: "/developer" }, { label: title }]} />

			<PageHead
				actions={
					<>
						{toolbar}
						<Button icon={<Plus size={13} />} to={`${route}/add`} variant="primary">
							{addLabel}
						</Button>
					</>
				}
				sub={pluralize(rows.length, countNoun)}
				title={title}
			/>

			<DeveloperSectionNav />

			<DataTable<T>
				columns={allColumns}
				emptyLabel={emptyLabel}
				getRowKey={getRowKey}
				isLoading={query.isLoading}
				loadingLabel={loadingLabel}
				rows={rows}
				onReorder={onReorder}
				onRowClick={(row) =>
					navigate(
						rowPath
							? rowPath(row)
							: `${route}/${encodeURIComponent(getRowKey(row))}/edit`
					)
				}
			/>

			{deleteDialog.item && (
				<ConfirmDialog
					{...deleteDialog.dialogProps}
					confirmLabel={confirmLabel}
					description={confirmDescription}
					title={`Delete "${rowLabel(deleteDialog.item)}"?`}
					variant="danger"
					onConfirm={() => deleteMutation.mutate(getRowKey(deleteDialog.item!))}
				/>
			)}
		</PageContainer>
	);
};
