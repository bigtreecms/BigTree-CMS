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
	/** Builds a custom-action path for a route + row id. */
	actionPath: (route: string, id: string | number) => string;
	builtins: BuiltinViewActionFlags;
	/**
	 * When false, the edit link is hidden and delete is disabled — used for
	 * not-yet-persisted (pending) rows. Defaults to true.
	 */
	canEditOrDelete?: boolean;
	/** Extra wrapper classes — e.g. `w-full` in a table cell, or a status dim class. */
	className?: string;
	custom: CustomViewAction[];
	/** Builds the edit-form path for a row id. */
	editPath: (id: string | number) => string;
	moduleId: string;
	onDelete: (row: ModuleEntryRow) => void;
	row: ModuleEntryRow;
	viewId: string;
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
					label={action.name}
					title={action.name}
					to={actionPath(action.route, row.id)}
					onClick={stop}
				>
					<Icon size={15} />
				</IconButton>
			);
		})}

		{builtins.edit && canEditOrDelete && (
			<IconButton label="Edit" title="Edit" to={editPath(row.id)} onClick={stop}>
				<Edit size={15} />
			</IconButton>
		)}

		<BuiltinToggleButtons builtins={builtins} moduleId={moduleId} row={row} viewId={viewId} />

		{builtins.delete && (
			<IconButton
				disabled={!canEditOrDelete}
				label="Delete"
				title="Delete"
				tone="danger"
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
