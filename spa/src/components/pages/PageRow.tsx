import { Archive, Edit, FileText, GripVertical, Move, RotateCcw, Trash2 } from "lucide-react";
import { Link, useLocation } from "react-router-dom";
import type { PageListRow } from "@/api/endpoints/pages";
import { EditableTitle } from "./EditableTitle";
import { StatusBadge, type PageStatus } from "./StatusBadge";
import { relativeTime } from "@/lib/time";
import type { DragReorderApi } from "@/hooks/useDragReorder";

/**
 * Single row in a Pages table. Mirrors the prototype's `.table__row` layout:
 *   [grip] [icon + title (clickable + editable)] [status + updated] [archive] [edit]
 *
 * - Grip: drag-to-reorder. Locked rows (no edit access) get a faded grip.
 * - Title: single-click navigates into the page; double-click renames inline.
 * - Left action: toggles archived state (becomes "restore" when archived). For
 *   draft rows (pending NEW changes) it instead becomes a Delete action — these
 *   are pending changes that should be deleted, not archived, matching legacy.
 * - Right action: Delete (archived rows) or Edit link (everything else).
 */

interface PageRowProps {
	row: PageListRow;
	drag: DragReorderApi<number>;
	onRename: (next: string) => void;
	onToggleArchive: () => void;
	/** Whether drag-to-reorder is enabled for this table (from PageTable.allowReorder). */
	allowReorder?: boolean;
	/** Optional override label for the left action column (used for title attribute on archived rows). */
	leftActionLabel?: string;
	/**
	 * Optional delete handler. Renders a delete button in the right column for
	 * archived rows, and in the left (archive) column for draft rows (pending
	 * NEW changes) — those are deleted rather than archived.
	 */
	onDelete?: () => void;
	/** Optional move-to-different-parent handler. When present, a Move button appears between Archive and Edit. */
	onMove?: () => void;
}

export const PageRow = ({
	row,
	drag,
	onRename,
	onToggleArchive,
	allowReorder = true,
	leftActionLabel,
	onDelete,
	onMove,
}: PageRowProps) => {
	const location = useLocation();
	const locked = row.access === "v" || row.access === "n"; // can't edit
	const canReorder = allowReorder && !locked;
	const isDragging = drag.dragId === row.id;
	const isDropTarget = drag.overId === row.id && drag.dragId !== row.id;

	// Draft pages (pending NEW changes) only exist in bigtree_pending_changes —
	// they are deleted rather than archived.
	const isDraft = Boolean(row.pending) && !row.archived;
	const leftActionIsDelete = isDraft && Boolean(onDelete);

	return (
		<div
			className={`grid min-h-[var(--row-h)] grid-cols-[24px_1fr_auto_auto_auto] items-center gap-x-2 border-b border-border px-2 pr-3 py-2 text-[13px] transition-colors last:border-b-0 hover:bg-surface-2 sm:h-[var(--row-h)] sm:min-h-0 sm:grid-cols-[28px_1fr_240px_56px_56px_56px] sm:gap-x-3 sm:py-0 ${
				isDragging ? "bg-accent-soft shadow-md" : ""
			} ${isDropTarget ? "shadow-[inset_0_2px_0_0_var(--color-accent)]" : ""}`}
			draggable={canReorder}
			onDragStart={(e) => drag.onDragStart(e, row.id)}
			onDragOver={(e) => drag.onDragOver(e, row.id)}
			onDrop={drag.onDrop}
			onDragEnd={drag.onDragEnd}
		>
			{/* Grip */}
			<span
				className={`grid h-6 w-6 place-items-center rounded text-text-4 ${
					canReorder
						? "cursor-grab hover:bg-hover hover:text-text-2 active:cursor-grabbing"
						: "cursor-default opacity-25 hover:bg-transparent hover:text-text-4"
				}`}
				title={canReorder ? "Drag to reorder" : "Reordering disabled for this section"}
				aria-hidden="true"
			>
				<GripVertical size={14} />
			</span>

			{/* Title cell */}
			<div className="flex min-w-0 items-center gap-2.5">
				<span className="grid h-[22px] w-[22px] flex-shrink-0 place-items-center text-text-3">
					<FileText size={15} />
				</span>
				<div className="min-w-0 flex-1">
					<Link
						to={`/pages/${row.id}`}
						className="block min-w-0 outline-none"
						onClick={(e) => {
							// Don't navigate when the user double-clicks the inner editable span
							if ((e.target as HTMLElement).closest("[contenteditable='true']")) {
								e.preventDefault();
							}
						}}
					>
						<EditableTitle value={row.nav_title} onChange={onRename} />
					</Link>

					{/* On mobile the dedicated status column is hidden, so the
					    status + updated time sit under the title instead. */}
					<div className="mt-1 flex items-center gap-2 sm:hidden">
						<StatusBadge status={statusFor(row)} />
						<span className="overflow-hidden text-ellipsis whitespace-nowrap text-[12px] text-text-3 tabular-nums">
							{relativeTime(row.updated_at)}
						</span>
					</div>
				</div>
			</div>

			{/* Status + updated (desktop column; shown under the title on mobile) */}
			<div className="hidden min-w-0 items-center gap-2.5 sm:flex">
				<StatusBadge status={statusFor(row)} />
				<span className="overflow-hidden text-ellipsis whitespace-nowrap text-[12px] text-text-3 tabular-nums">
					{relativeTime(row.updated_at)}
				</span>
			</div>

			{/* Left action: Delete (draft pages) or Archive/Restore */}
			{leftActionIsDelete ? (
				<button
					type="button"
					onClick={onDelete}
					className="grid h-7 w-7 place-items-center rounded-md border-0 bg-transparent text-text-3 transition-colors hover:bg-hover hover:text-danger disabled:cursor-not-allowed disabled:opacity-40"
					title="Delete"
					disabled={locked}
				>
					<Trash2 size={14} />
				</button>
			) : (
				<button
					type="button"
					onClick={onToggleArchive}
					className="grid h-7 w-7 place-items-center rounded-md border-0 bg-transparent text-text-3 transition-colors hover:bg-hover hover:text-text disabled:cursor-not-allowed disabled:opacity-40"
					title={leftActionLabel ?? (row.archived ? "Restore" : "Archive")}
					disabled={locked}
				>
					{row.archived ? <RotateCcw size={14} /> : <Archive size={14} />}
				</button>
			)}

			{/* Move */}
			{onMove ? (
				<button
					type="button"
					onClick={onMove}
					className="grid h-7 w-7 place-items-center rounded-md border-0 bg-transparent text-text-3 transition-colors hover:bg-hover hover:text-text disabled:cursor-not-allowed disabled:opacity-40"
					title="Move to a different parent"
					disabled={locked || row.archived}
					aria-label="Move page"
				>
					<Move size={14} />
				</button>
			) : (
				<span />
			)}

			{/* Right action: Delete (archived rows) or Edit link */}
			{row.archived && onDelete ? (
				<button
					type="button"
					onClick={onDelete}
					className="grid h-7 w-7 place-items-center rounded-md border-0 bg-transparent text-text-3 transition-colors hover:bg-hover hover:text-danger disabled:cursor-not-allowed disabled:opacity-40"
					title="Delete"
					disabled={locked}
				>
					<Trash2 size={14} />
				</button>
			) : (
				<Link
					to={
						isDraft
							? `/pages/draft/${row.pending_change_id}/edit`
							: `/pages/${row.id}/edit`
					}
					state={{ from: location.pathname + location.search }}
					className="grid h-7 w-7 place-items-center rounded-md border-0 bg-transparent text-text-3 transition-colors hover:bg-hover hover:text-text"
					title="Edit page"
					aria-disabled={locked}
					onClick={(e) => {
						if (locked) {
							e.preventDefault();
						}
					}}
				>
					<Edit size={14} />
				</Link>
			)}
		</div>
	);
};

/**
 * Derive a display status from the row.
 * The backend now includes `has_pending_change` (from bigtree_pending_changes)
 * so we can show "Changed" for pages with unpublished edits.
 * New pending pages (type=NEW) use the "pending" status which displays as "Draft"
 * to match the filter labels.
 */
const statusFor = (row: PageListRow): PageStatus => {
	if (row.archived) {
		return "archived";
	}
	if (row.pending) {
		return "pending";
	}
	if (row.has_pending_change) {
		return "changed";
	}
	if (row.scheduled) {
		return "scheduled";
	}

	return "published";
};
