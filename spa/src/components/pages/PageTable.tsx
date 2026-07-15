import { useState, type ReactNode } from "react";
import type { PageListRow } from "@/api/endpoints/pages";
import { PageRow } from "./PageRow";
import { useDragReorder } from "@/hooks/useDragReorder";
import { Badge } from "@/components/ui/Badge";
import { Chip } from "@/components/ui/Chip";
import { InlineEmpty } from "@/components/ui/InlineEmpty";

/**
 * Section + table block for a list of page rows.
 *
 * Header has the section title, count chip, and filter chips (All / Published /
 * Draft / Scheduled). Filters operate on the in-memory rows — the parent
 * passes the full set; we filter client-side because list payloads are small.
 */

type Filter = "all" | "published" | "draft" | "scheduled";

interface PageTableProps {
	/** Whether drag-to-reorder is enabled. Defaults to true. */
	allowReorder?: boolean;
	/** Custom empty state — defaults to "No pages yet." */
	emptyLabel?: string;
	/** Show only rows matching this filter. "all" disables filtering. */
	enableFilters?: boolean;
	icon?: ReactNode;
	/** Label for the left action column header (e.g. "Archive" or "Restore"). */
	leftActionLabel?: string;
	/** Optional delete handler. When provided, the right action becomes a delete button. */
	onDelete?: (id: number) => void;
	/** Optional move handler. When provided, a Move button column is shown between Archive and Edit. */
	onMove?: (id: number) => void;
	onRename: (id: number, next: string) => void;
	onReorder: (orderedIds: number[]) => void;
	onToggleArchive: (id: number) => void;
	/** Label for the right action column header (e.g. "Edit" or "Delete"). */
	rightActionLabel?: string;
	rows: PageListRow[];
	title: string;
}

export const PageTable = ({
	title,
	icon,
	rows,
	onReorder,
	onRename,
	onToggleArchive,
	enableFilters = true,
	emptyLabel,
	allowReorder = true,
	leftActionLabel,
	rightActionLabel,
	onDelete,
	onMove,
}: PageTableProps) => {
	const [filter, setFilter] = useState<Filter>("all");
	const filtered =
		enableFilters && filter !== "all" ? rows.filter((r) => statusMatches(r, filter)) : rows;

	const getEmptyMessage = () => {
		const base = emptyLabel ?? "No pages yet.";

		if (filter === "all" || !enableFilters) {
			return base;
		}

		let filterLabel = "";
		if (filter === "published") {
			filterLabel = "Published";
		} else if (filter === "draft") {
			filterLabel = "Draft";
		} else if (filter === "scheduled") {
			filterLabel = "Scheduled";
		}

		if (!filterLabel) {
			return base;
		}

		// Turn "No visible pages." into "No visible pages in Draft status."
		const withoutTrailingPeriod = base.replace(/\.$/, "");
		return `${withoutTrailingPeriod} in ${filterLabel} status.`;
	};

	// We pass a non-functional setter that mirrors the optimistic reorder back
	// into the parent via onReorder. The actual list state lives upstream.
	const fullDrag = useDragReorder<PageListRow, number>(
		filtered,
		(next) => onReorder(next.map((r) => r.id)),
		(orderedIds) => onReorder(orderedIds)
	);

	const drag = allowReorder
		? fullDrag
		: {
				dragId: null,
				overId: null,
				onDragStart: () => {},
				onDragOver: () => {},
				onDrop: () => {},
				onDragEnd: () => {},
			};

	return (
		<div className="mt-7">
			<div className="mb-2.5 flex items-center gap-2.5 border-b border-border pb-2">
				{icon}
				<span className="text-[11px] font-semibold uppercase tracking-[0.08em] text-text-3">
					{title}
				</span>
				<Badge className="tabular-nums">{filtered.length}</Badge>
				<span className="flex-1" />
				{enableFilters && (
					<div className="flex gap-1">
						<Chip active={filter === "all"} onClick={() => setFilter("all")}>
							All
						</Chip>
						<Chip
							active={filter === "published"}
							onClick={() => setFilter("published")}
						>
							Published
						</Chip>
						<Chip active={filter === "draft"} onClick={() => setFilter("draft")}>
							Draft
						</Chip>
						<Chip
							active={filter === "scheduled"}
							onClick={() => setFilter("scheduled")}
						>
							Scheduled
						</Chip>
					</div>
				)}
			</div>

			<div className="w-full overflow-hidden rounded-lg border border-border bg-surface">
				<div className="hidden h-[34px] grid-cols-[28px_1fr_240px_56px_56px_56px] items-center gap-x-3 border-b border-border bg-surface-2 px-2 pr-3 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-text-3 sm:grid">
					<span />
					<span>Title</span>
					<span>Status & updated</span>
					<span>{leftActionLabel ?? "Archive"}</span>
					<span>{onMove ? "Move" : ""}</span>
					<span>{rightActionLabel ?? "Edit"}</span>
				</div>

				{filtered.length === 0 ? (
					<InlineEmpty align="center" variant="plain">
						{getEmptyMessage()}
					</InlineEmpty>
				) : (
					filtered.map((row) => (
						<PageRow
							allowReorder={allowReorder}
							drag={drag}
							key={row.id}
							leftActionLabel={leftActionLabel}
							row={row}
							onDelete={onDelete ? () => onDelete(row.id) : undefined}
							onMove={onMove ? () => onMove(row.id) : undefined}
							onRename={(next) => onRename(row.id, next)}
							onToggleArchive={() => onToggleArchive(row.id)}
						/>
					))
				)}
			</div>
		</div>
	);
};

const statusMatches = (row: PageListRow, f: Filter): boolean => {
	if (f === "all") {
		return true;
	}

	if (f === "published") {
		// A row can have a pending change (has_pending_change) and still be
		// considered Published. It just can't be *completely* pending (the
		// brand new pending pages...) or scheduled for a future publish_at.
		return !row.archived && !row.pending && !row.scheduled;
	}

	if (f === "draft") {
		return !!row.pending;
	}

	if (f === "scheduled") {
		return !!row.scheduled;
	}

	return false;
};
