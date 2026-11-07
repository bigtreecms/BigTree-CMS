import { Edit, Eye, Trash } from "lucide-react";

import { IconButton } from "@/components/ui/IconButton";
import type { ModuleEntryRow } from "@/api/endpoints/auto-modules";
import { modulePreviewUrl, openPreviewWindow, showPreview } from "@/lib/preview";

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
	/** The view's `preview_url`; when set, each row gets a Preview action. */
	previewUrl?: string;
	row: ModuleEntryRow;
	viewId: string;
}

const stop = (e: { stopPropagation: () => void }) => e.stopPropagation();

/**
 * The trailing per-row action cluster shared by every module list view
 * (searchable / grouped / nested / draggable): custom actions, preview, edit,
 * the publisher toggles, and delete — all as {@link IconButton}s. The single source
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
	previewUrl,
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

		{previewUrl && <PreviewAction previewUrl={previewUrl} rowId={row.id} />}

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

interface PreviewActionProps {
	previewUrl: string;
	rowId: string | number;
}

/**
 * Opens the entry on the front end through the view's preview URL. Goes
 * through showPreview so the admin PHP session exists before the preview
 * renders; the href stays real for modified clicks and "copy link".
 */
const PreviewAction = ({ previewUrl, rowId }: PreviewActionProps) => {
	const url = modulePreviewUrl(previewUrl, rowId);

	return (
		<IconButton
			href={url}
			label="Preview"
			target="_blank"
			title="Preview"
			onClick={(e) => {
				e.stopPropagation();

				if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
					return;
				}

				e.preventDefault();
				void showPreview(openPreviewWindow(), url);
			}}
		>
			<Eye size={15} />
		</IconButton>
	);
};
