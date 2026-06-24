import { Edit, Trash } from "lucide-react";

import { IconButton } from "@/components/ui/IconButton";
import type { ModuleEntryRow } from "@/api/endpoints/auto-modules";

import { BuiltinToggleButtons } from "./BuiltinToggleButtons";
import {
	iconForCustomAction,
	type BuiltinViewActionFlags,
	type CustomViewAction,
} from "./viewHelpers";

interface RowActionsProps {
	moduleId: string;
	viewId: string;
	row: ModuleEntryRow;
	builtins: BuiltinViewActionFlags;
	custom: CustomViewAction[];
	/** Builds the edit-form path for a row id. */
	editPath: (id: string | number) => string;
	/** Builds a custom-action path for a route + row id. */
	actionPath: (route: string, id: string | number) => string;
	onDelete: (row: ModuleEntryRow) => void;
	/**
	 * When false, the edit link is hidden and delete is disabled — used for
	 * not-yet-persisted (pending) rows. Defaults to true.
	 */
	canEditOrDelete?: boolean;
	/** Extra wrapper classes — e.g. `w-full` in a table cell, or a status dim class. */
	className?: string;
}

const stop = (e: { stopPropagation: () => void }) => e.stopPropagation();

/**
 * The trailing per-row action cluster shared by every module list view
 * (searchable / grouped / nested / draggable): custom actions, edit, the
 * publisher toggles, and delete — all as {@link IconButton}s. The single source
 * of truth for that group, so the views only differ by how they lay out rows,
 * not how they render actions.
 */
export const RowActions = ({
	moduleId,
	viewId,
	row,
	builtins,
	custom,
	editPath,
	actionPath,
	onDelete,
	canEditOrDelete = true,
	className,
}: RowActionsProps) => (
	<div className={`flex items-center justify-end gap-1${className ? ` ${className}` : ""}`}>
		{custom.map((action) => {
			const Icon = iconForCustomAction(action.className);

			return (
				<IconButton
					key={action.key}
					to={actionPath(action.route, row.id)}
					label={action.name}
					title={action.name}
					onClick={stop}
				>
					<Icon size={15} />
				</IconButton>
			);
		})}

		{builtins.edit && canEditOrDelete && (
			<IconButton to={editPath(row.id)} label="Edit" title="Edit" onClick={stop}>
				<Edit size={15} />
			</IconButton>
		)}

		<BuiltinToggleButtons moduleId={moduleId} viewId={viewId} row={row} builtins={builtins} />

		{builtins.delete && (
			<IconButton
				label="Delete"
				title="Delete"
				tone="danger"
				disabled={!canEditOrDelete}
				onClick={(e) => {
					e.stopPropagation();
					onDelete(row);
				}}
			>
				<Trash size={15} />
			</IconButton>
		)}
	</div>
);
