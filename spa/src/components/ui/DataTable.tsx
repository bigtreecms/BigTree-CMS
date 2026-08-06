import type { CSSProperties, ReactNode } from "react";
import { ChevronDown, ChevronUp } from "lucide-react";

import { useDragReorder } from "@/hooks/useDragReorder";
import { Card } from "@/components/ui/Card";
import { DragHandle } from "@/components/ui/DragHandle";
import { Loading } from "@/components/ui/Loading";

export interface DataTableColumn<Row> {
	/** Alignment applied to the cell on desktop layouts. */
	align?: "left" | "right" | "center";
	cell: (row: Row) => ReactNode;
	header: ReactNode;
	/** Alignment applied to the header cell. */
	headerAlign?: "left" | "right" | "center";
	/** Hide on small screens (collapses to single column). Defaults to false. */
	hideOnMobile?: boolean;
	/** Used as the React key and as the sort identifier when `sortable` is on. */
	key: string;
	sortable?: boolean;
	/** Grid track expression (e.g. "minmax(0,1.3fr)" or "140px"). */
	width: string;
}

export interface DataTableSort {
	dir: "asc" | "desc";
	key: string;
}

export interface DataTableProps<Row> {
	columns: DataTableColumn<Row>[];
	emptyLabel?: ReactNode;
	getRowKey: (row: Row) => string | number;
	isLoading?: boolean;
	loadingLabel?: string;
	/**
	 * When provided, rows gain a drag handle and can be reordered; the callback
	 * receives the row keys in their new order. Purely additive — without it the
	 * table behaves exactly as before. Reordering is a desktop-only affordance.
	 */
	onReorder?: (orderedKeys: Array<string | number>) => void;
	onRowClick?: (row: Row) => void;
	onSortChange?: (sort: DataTableSort) => void;
	/**
	 * Optional extra classes applied per row (e.g. a status-based background
	 * tint). Returned classes are appended after the base row classes, so they
	 * win on conflicting properties.
	 */
	rowClassName?: (row: Row) => string | undefined;
	rows: Row[];
	sort?: DataTableSort;
}

/**
 * CSS-grid generic table. Rather than using a real <table>, rows are laid out
 * with `grid-template-columns: ${cols.map(c => c.width).join(' ')}` so a
 * single row collapses cleanly to one column on mobile.
 *
 * Tailwind v4 can't statically extract `grid-cols-[${expr}]` interpolations,
 * so the per-instance column tracks are passed through a CSS custom property
 * (`--dt-cols`) and consumed by the static utility `md:grid-cols-[var(--dt-cols)]`.
 */
export const DataTable = <Row,>({
	columns,
	rows,
	getRowKey,
	isLoading,
	loadingLabel = "Loading…",
	emptyLabel = "No results.",
	sort,
	onSortChange,
	onRowClick,
	rowClassName,
	onReorder,
}: DataTableProps<Row>) => {
	const reorderable = !!onReorder;
	const dragItems = rows.map((row) => ({ id: getRowKey(row) }));
	const drag = useDragReorder<{ id: string | number }, string | number>(
		dragItems,
		() => {},
		(orderedKeys) => onReorder?.(orderedKeys)
	);

	const trackWidths = columns.map((c) => c.width).join(" ");
	const colsValue = reorderable ? `28px ${trackWidths}` : trackWidths;
	const colsStyle = { "--dt-cols": colsValue } as CSSProperties;

	const toggleSort = (key: string) => {
		if (!onSortChange) {
			return;
		}

		if (sort?.key === key) {
			onSortChange({ key, dir: sort.dir === "asc" ? "desc" : "asc" });

			return;
		}

		onSortChange({ key, dir: "asc" });
	};

	// Apply `uppercase` on the header *cells* (not only the row). Sortable
	// headers render as <button>s; UA / preflight button styles don't reliably
	// inherit text-transform from the parent, so a row-level `uppercase` alone
	// left sortable columns (News, Tags, …) in title case while static headers
	// (e.g. Form Builder actions) stayed all-caps.
	const headerCellClass =
		"text-[10.5px] font-semibold uppercase tracking-[0.06em] text-text-3";

	return (
		<Card className="overflow-hidden">
			<div
				className="hidden md:grid md:grid-cols-(--dt-cols) items-center gap-4 border-b border-border bg-surface-2 px-3.5 py-2"
				style={colsStyle}
			>
				{reorderable && <div aria-hidden="true" />}
				{columns.map((col) => {
					const headerAlignClass =
						col.headerAlign === "right"
							? "justify-end text-right"
							: col.headerAlign === "center"
								? "justify-center text-center"
								: "text-left";

					if (col.sortable && onSortChange) {
						const isActive = sort?.key === col.key;

						return (
							<button
								className={`flex items-center gap-1 hover:text-text ${headerCellClass} ${headerAlignClass}`}
								key={col.key}
								type="button"
								onClick={() => toggleSort(col.key)}
							>
								{col.header}
								<SortCaret active={isActive} dir={sort?.dir} />
							</button>
						);
					}

					return (
						<div className={`${headerCellClass} ${headerAlignClass}`} key={col.key}>
							{col.header}
						</div>
					);
				})}
			</div>

			{isLoading ? (
				<Loading label={loadingLabel} variant="block" />
			) : rows.length === 0 ? (
				<div className="p-9 text-center text-[13px] text-text-3">{emptyLabel}</div>
			) : (
				rows.map((row) => {
					const key = getRowKey(row);
					const isDropTarget = reorderable && drag.overId === key && drag.dragId !== key;

					return (
						<div
							className={`grid grid-cols-1 gap-x-4 gap-y-2 border-b border-border px-3.5 py-2.5 text-[13px] last:border-b-0 hover:bg-surface-2 md:grid-cols-(--dt-cols) md:items-center md:gap-y-0 md:py-1.5 ${
								onRowClick ? "cursor-pointer" : ""
							} ${isDropTarget ? "shadow-[inset_0_2px_0_0_var(--color-accent)]" : ""} ${
								rowClassName?.(row) ?? ""
							}`}
							key={key}
							style={colsStyle}
							onClick={() => onRowClick?.(row)}
							onDragOver={reorderable ? (e) => drag.onDragOver(e, key) : undefined}
							onDrop={reorderable ? drag.onDrop : undefined}
						>
							{reorderable && (
								// Hidden on mobile (drag-reorder is a pointer affordance); the
								// wrapper carries the responsive display so the shared DragHandle
								// keeps its own `grid` layout.
								<span className="hidden md:block">
									<DragHandle
										draggable
										onClick={(e) => e.stopPropagation()}
										onDragEnd={drag.onDragEnd}
										onDragStart={(e) => drag.onDragStart(e, key)}
									/>
								</span>
							)}
							{columns.map((col) => {
								const alignClass =
									col.align === "right"
										? "md:text-right"
										: col.align === "center"
											? "md:text-center"
											: "";

								// On mobile the header row is hidden and cells stack into a
								// single column, so each cell would otherwise lose its
								// context. Re-surface the column header as a small label
								// above the value — skipping hidden columns and non-text
								// headers (e.g. a select-all checkbox or an empty actions
								// header). The label follows the column's alignment so
								// right-aligned columns line up.
								const mobileLabel =
									!col.hideOnMobile &&
									typeof col.header === "string" &&
									col.header.trim().length > 0
										? col.header
										: null;
								const labelAlignClass =
									col.align === "right"
										? "text-right"
										: col.align === "center"
											? "text-center"
											: "";

								return (
									<div
										className={`${col.hideOnMobile ? "hidden md:block" : ""} ${alignClass}`}
										key={col.key}
									>
										{mobileLabel && (
											<span
												className={`mb-0.5 block text-[10px] font-semibold uppercase tracking-[0.06em] text-text-3 md:hidden ${labelAlignClass}`}
											>
												{mobileLabel}
											</span>
										)}
										{col.cell(row)}
									</div>
								);
							})}
						</div>
					);
				})
			)}
		</Card>
	);
};

interface SortCaretProps {
	active: boolean;
	dir?: "asc" | "desc";
}

const SortCaret = ({ active, dir }: SortCaretProps) => {
	if (!active) {
		return <ChevronDown className="opacity-40" size={10} />;
	}

	if (dir === "asc") {
		return <ChevronUp size={10} />;
	}

	return <ChevronDown size={10} />;
};
