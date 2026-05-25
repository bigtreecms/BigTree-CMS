import { Archive, Edit, FileText, GripVertical, RotateCcw } from "lucide-react";
import { Link } from "react-router-dom";
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
 * - Archive icon: toggles archived state. Becomes "restore" icon when archived.
 * - Edit icon: navigates to the page editor (future — placeholder for now).
 */

interface PageRowProps {
	row: PageListRow;
	drag: DragReorderApi<number>;
	onRename: (next: string) => void;
	onToggleArchive: () => void;
}

export const PageRow = ({ row, drag, onRename, onToggleArchive }: PageRowProps) => {
	const locked = row.access === "v" || row.access === "n"; // can't edit
	const isDragging = drag.dragId === row.id;
	const isDropTarget = drag.overId === row.id && drag.dragId !== row.id;

	return (
		<div
			className={`grid h-[var(--row-h)] grid-cols-[28px_1fr_240px_56px_56px] items-center gap-x-3 border-b border-border px-2 pr-3 text-[13px] transition-colors last:border-b-0 hover:bg-surface-2 ${
				isDragging ? "bg-accent-soft shadow-md" : ""
			} ${isDropTarget ? "shadow-[inset_0_2px_0_0_var(--color-accent)]" : ""}`}
			draggable={!locked}
			onDragStart={(e) => drag.onDragStart(e, row.id)}
			onDragOver={(e) => drag.onDragOver(e, row.id)}
			onDrop={drag.onDrop}
			onDragEnd={drag.onDragEnd}
		>
			{/* Grip */}
			<span
				className={`grid h-6 w-6 cursor-grab place-items-center rounded text-text-4 hover:bg-hover hover:text-text-2 active:cursor-grabbing ${
					locked ? "cursor-default opacity-25 hover:bg-transparent hover:text-text-4" : ""
				}`}
				title={locked ? "Locked" : "Drag to reorder"}
				aria-hidden="true"
			>
				<GripVertical size={14} />
			</span>

			{/* Title cell */}
			<div className="flex min-w-0 items-center gap-2.5">
				<span className="grid h-[22px] w-[22px] flex-shrink-0 place-items-center text-text-3">
					<FileText size={15} />
				</span>
				<Link
					to={`/pages/${row.id}`}
					className="min-w-0 flex-1 outline-none"
					onClick={(e) => {
						// Don't navigate when the user double-clicks the inner editable span
						if ((e.target as HTMLElement).closest("[contenteditable='true']")) {
							e.preventDefault();
						}
					}}
				>
					<EditableTitle value={row.nav_title} onChange={onRename} />
				</Link>
			</div>

			{/* Status + updated */}
			<div className="flex min-w-0 items-center gap-2.5">
				<StatusBadge status={statusFor(row)} />
				<span className="overflow-hidden text-ellipsis whitespace-nowrap text-[12px] text-text-3 tabular-nums">
					{relativeTime(row.updated_at)}
				</span>
			</div>

			{/* Archive */}
			<button
				type="button"
				onClick={onToggleArchive}
				className="grid h-7 w-7 place-items-center rounded-md border-0 bg-transparent text-text-3 transition-colors hover:bg-hover hover:text-text disabled:cursor-not-allowed disabled:opacity-40"
				title={row.archived ? "Restore" : "Archive"}
				disabled={locked}
			>
				{row.archived ? <RotateCcw size={14} /> : <Archive size={14} />}
			</button>

			{/* Edit */}
			<Link
				to={`/pages/${row.id}/edit`}
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
		</div>
	);
};

/**
 * Derive a display status from the row. Today the API exposes archived +
 * publish_at/expire_at but doesn't directly tell us if there's a pending
 * change ("Draft"). When PageService starts including a pending-change flag
 * in the list payload, we'll extend this.
 */
const statusFor = (row: PageListRow): PageStatus => {
	if (row.archived) {
		return "archived";
	}

	// `updated_at` in the list rows isn't the publish_at field; we'd need that
	// to detect scheduled status. For now everything non-archived shows as
	// Published — Draft / Scheduled will follow once the list payload carries
	// the necessary flags.
	return "published";
};
