import { type ReactNode, useState } from "react";
import type { PageListRow } from "@/api/endpoints/pages";
import { PageRow } from "./PageRow";
import { useDragReorder } from "@/hooks/useDragReorder";

/**
 * Section + table block for a list of page rows.
 *
 * Header has the section title, count chip, and filter chips (All / Published /
 * Draft / Scheduled). Filters operate on the in-memory rows — the parent
 * passes the full set; we filter client-side because list payloads are small.
 */

type Filter = "all" | "published" | "draft" | "scheduled";

interface PageTableProps {
	title: string;
	icon?: ReactNode;
	rows: PageListRow[];
	onReorder: (orderedIds: number[]) => void;
	onRename: (id: number, next: string) => void;
	onToggleArchive: (id: number) => void;
	/** Show only rows matching this filter. "all" disables filtering. */
	enableFilters?: boolean;
	/** Custom empty state — defaults to "No pages yet." */
	emptyLabel?: string;
}

export function PageTable({
	title,
	icon,
	rows,
	onReorder,
	onRename,
	onToggleArchive,
	enableFilters = true,
	emptyLabel,
}: PageTableProps) {
	const [filter, setFilter] = useState<Filter>("all");
	const filtered =
		enableFilters && filter !== "all"
			? rows.filter((r) => statusMatches(r, filter))
			: rows;

	// We pass a non-functional setter that mirrors the optimistic reorder back
	// into the parent via onReorder. The actual list state lives upstream.
	const drag = useDragReorder<PageListRow, number>(
		filtered,
		(next) => onReorder(next.map((r) => r.id)),
		(orderedIds) => onReorder(orderedIds),
	);

	return (
		<div className="mt-7">
			<div className="mb-2.5 flex items-center gap-2.5 border-b border-border pb-2">
				{icon}
				<span className="text-[11px] font-semibold uppercase tracking-[0.08em] text-text-3">
					{title}
				</span>
				<span className="rounded-full bg-surface-2 px-1.5 py-0.5 text-[11px] font-medium text-text-3 tabular-nums">
					{filtered.length}
				</span>
				<span className="flex-1" />
				{enableFilters && (
					<div className="flex gap-1">
						<Chip
							active={filter === "all"}
							onClick={() => setFilter("all")}
						>
							All
						</Chip>
						<Chip
							active={filter === "published"}
							onClick={() => setFilter("published")}
						>
							Published
						</Chip>
						<Chip
							active={filter === "draft"}
							onClick={() => setFilter("draft")}
						>
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
				<div className="grid h-[34px] grid-cols-[28px_1fr_240px_56px_56px] items-center gap-x-3 border-b border-border bg-surface-2 px-2 pr-3 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
					<span />
					<span>Title</span>
					<span>Status & updated</span>
					<span>Archive</span>
					<span>Edit</span>
				</div>

				{filtered.length === 0 ? (
					<div className="px-3 py-6 text-center text-[12.5px] text-text-3">
						{emptyLabel ?? "No pages yet."}
					</div>
				) : (
					filtered.map((row) => (
						<PageRow
							key={row.id}
							row={row}
							drag={drag}
							onRename={(next) => onRename(row.id, next)}
							onToggleArchive={() => onToggleArchive(row.id)}
						/>
					))
				)}
			</div>
		</div>
	);
}

function Chip({
	active,
	onClick,
	children,
}: {
	active: boolean;
	onClick: () => void;
	children: ReactNode;
}) {
	return (
		<button
			type="button"
			onClick={onClick}
			data-active={active}
			className={`inline-flex cursor-pointer items-center gap-1 rounded-full border px-2 py-0.5 text-[11.5px] transition-colors ${
				active
					? "border-accent-soft-2 bg-accent-soft text-accent"
					: "border-border bg-transparent text-text-3 hover:bg-hover"
			}`}
		>
			{children}
		</button>
	);
}

function statusMatches(row: PageListRow, f: Filter): boolean {
	if (f === "all") return true;
	if (f === "published") return !row.archived;
	// Draft / Scheduled detection needs additional fields from the API — for
	// now these filters select nothing (placeholder pending the API addition).
	return false;
}
