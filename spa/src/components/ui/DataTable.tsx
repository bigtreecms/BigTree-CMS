import type { CSSProperties, ReactNode } from "react";
import { ChevronDown, ChevronUp, GripVertical } from "lucide-react";

import { useDragReorder } from "@/hooks/useDragReorder";

export interface DataTableColumn<Row> {
	/** Used as the React key and as the sort identifier when `sortable` is on. */
	key: string;
	header: ReactNode;
	/** Grid track expression (e.g. "minmax(0,1.3fr)" or "140px"). */
	width: string;
	cell: (row: Row) => ReactNode;
	sortable?: boolean;
	/** Hide on small screens (collapses to single column). Defaults to false. */
	hideOnMobile?: boolean;
	/** Alignment applied to the header cell. */
	headerAlign?: "left" | "right" | "center";
	/** Alignment applied to the cell on desktop layouts. */
	align?: "left" | "right" | "center";
}

export interface DataTableSort {
	key: string;
	dir: "asc" | "desc";
}

interface DataTableProps<Row> {
	columns: DataTableColumn<Row>[];
	rows: Row[];
	getRowKey: (row: Row) => string | number;
	isLoading?: boolean;
	loadingLabel?: string;
	emptyLabel?: ReactNode;
	sort?: DataTableSort;
	onSortChange?: (sort: DataTableSort) => void;
	onRowClick?: (row: Row) => void;
	/**
	 * Optional extra classes applied per row (e.g. a status-based background
	 * tint). Returned classes are appended after the base row classes, so they
	 * win on conflicting properties.
	 */
	rowClassName?: (row: Row) => string | undefined;
	/**
	 * When provided, rows gain a drag handle and can be reordered; the callback
	 * receives the row keys in their new order. Purely additive — without it the
	 * table behaves exactly as before. Reordering is a desktop-only affordance.
	 */
	onReorder?: (orderedKeys: Array<string | number>) => void;
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

	return (
		<div className="overflow-hidden rounded-xl border border-border bg-surface">
			<div
				className="hidden md:grid md:grid-cols-[var(--dt-cols)] items-center gap-4 border-b border-border bg-surface-2 px-3.5 py-2 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-text-3"
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
								key={col.key}
								type="button"
								className={`flex items-center gap-1 hover:text-text ${headerAlignClass}`}
								onClick={() => toggleSort(col.key)}
							>
								{col.header}
								<SortCaret active={isActive} dir={sort?.dir} />
							</button>
						);
					}

					return (
						<div key={col.key} className={headerAlignClass}>
							{col.header}
						</div>
					);
				})}
			</div>

			{isLoading ? (
				<div className="p-9 text-center text-[13px] text-text-3">{loadingLabel}</div>
			) : rows.length === 0 ? (
				<div className="p-9 text-center text-[13px] text-text-3">{emptyLabel}</div>
			) : (
				rows.map((row) => {
					const key = getRowKey(row);
					const isDropTarget = reorderable && drag.overId === key && drag.dragId !== key;

					return (
						<div
							key={key}
							className={`grid grid-cols-1 md:grid-cols-[var(--dt-cols)] gap-x-4 border-b border-border px-3.5 py-2 text-[13px] last:border-b-0 hover:bg-surface-2 md:items-center md:py-1.5 ${
								onRowClick ? "cursor-pointer" : ""
							} ${isDropTarget ? "shadow-[inset_0_2px_0_0_var(--color-accent)]" : ""} ${
								rowClassName?.(row) ?? ""
							}`}
							style={colsStyle}
							onClick={() => onRowClick?.(row)}
							onDragOver={reorderable ? (e) => drag.onDragOver(e, key) : undefined}
							onDrop={reorderable ? drag.onDrop : undefined}
						>
							{reorderable && (
								<span
									className="hidden h-6 w-6 cursor-grab place-items-center rounded text-text-4 hover:bg-hover hover:text-text-2 active:cursor-grabbing md:grid"
									title="Drag to reorder"
									draggable
									onClick={(e) => e.stopPropagation()}
									onDragStart={(e) => drag.onDragStart(e, key)}
									onDragEnd={drag.onDragEnd}
								>
									<GripVertical size={14} />
								</span>
							)}
							{columns.map((col) => {
								const alignClass =
									col.align === "right"
										? "md:text-right"
										: col.align === "center"
											? "md:text-center"
											: "";

								return (
									<div
										key={col.key}
										className={`${col.hideOnMobile ? "hidden md:block" : ""} ${alignClass}`}
									>
										{col.cell(row)}
									</div>
								);
							})}
						</div>
					);
				})
			)}
		</div>
	);
};

interface SortCaretProps {
	active: boolean;
	dir?: "asc" | "desc";
}

const SortCaret = ({ active, dir }: SortCaretProps) => {
	if (!active) {
		return <ChevronDown size={10} className="opacity-40" />;
	}

	if (dir === "asc") {
		return <ChevronUp size={10} />;
	}

	return <ChevronDown size={10} />;
};
