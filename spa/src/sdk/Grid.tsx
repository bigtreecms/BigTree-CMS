import type { CSSProperties, HTMLAttributes, ReactNode } from "react";

/**
 * Flexible CSS grid for custom module actions.
 *
 * Supports equal columns, fixed/fr templates, and simple responsive collapse
 * (stack below a breakpoint). Pair with {@link GridItem} for spans.
 *
 * @example Equal two columns
 * ```tsx
 * <Grid columns={2} gap={16}>
 *   <Panel>Left</Panel>
 *   <Panel>Right</Panel>
 * </Grid>
 * ```
 *
 * @example Sidebar + main (Form Builder palette layout)
 * ```tsx
 * <Grid columns="220px 1fr" gap={16} collapseBelow="lg">
 *   <GridItem>Palette</GridItem>
 *   <GridItem>Fields</GridItem>
 * </Grid>
 * ```
 */

export type GridCollapseBelow = "sm" | "md" | "lg" | "xl" | "never";

export type GridColumns = number | string;

interface GridProps extends HTMLAttributes<HTMLDivElement> {
	children: ReactNode;
	/**
	 * Column template:
	 * - `number` → `repeat(n, minmax(0, 1fr))` equal flexible columns
	 * - `string` → raw `grid-template-columns` (e.g. `"220px 1fr"`, `"1fr 2fr"`)
	 * Default `2`.
	 */
	columns?: GridColumns;
	/**
	 * Gap in px. Pass `{ row, column }` for separate axes.
	 * Default `16`.
	 */
	gap?: number | { row?: number; column?: number };
	/**
	 * Stack to a single column below this viewport width (Tailwind breakpoints).
	 * Default `"lg"` so sidebars work on desktop and stack on mobile.
	 * Pass `"never"` to always keep the multi-column template.
	 */
	collapseBelow?: GridCollapseBelow;
	/** Align items on the block axis (default stretch). */
	align?: "start" | "center" | "end" | "stretch";
}

const COLLAPSE_MEDIA: Record<Exclude<GridCollapseBelow, "never">, string> = {
	sm: "40rem", // 640px
	md: "48rem", // 768px
	lg: "64rem", // 1024px
	xl: "80rem", // 1280px
};

const resolveTemplate = (columns: GridColumns): string => {
	if (typeof columns === "number") {
		const n = Math.max(1, Math.floor(columns));

		return `repeat(${n}, minmax(0, 1fr))`;
	}

	return columns;
};

const resolveGap = (gap: GridProps["gap"]): { row: number; column: number } => {
	if (typeof gap === "number") {
		return { row: gap, column: gap };
	}

	return {
		row: gap?.row ?? 16,
		column: gap?.column ?? 16,
	};
};

/**
 * CSS grid container. Prefer this over hand-rolled `display:grid` so custom
 * actions stay consistent and collapse sensibly on small screens.
 */
export const Grid = ({
	children,
	columns = 2,
	gap = 16,
	collapseBelow = "lg",
	align = "stretch",
	className = "",
	style,
	...rest
}: GridProps) => {
	const template = resolveTemplate(columns);
	const gaps = resolveGap(gap);
	const alignMap = {
		start: "start",
		center: "center",
		end: "end",
		stretch: "stretch",
	} as const;

	const baseStyle: CSSProperties = {
		display: "grid",
		gridTemplateColumns: template,
		columnGap: gaps.column,
		rowGap: gaps.row,
		alignItems: alignMap[align],
		...style,
	};

	// Responsive collapse via a unique class + embedded <style> so we don't
	// depend on arbitrary Tailwind class generation for author templates.
	const needsCollapse = collapseBelow !== "never" && template !== "1fr";
	const collapseId = needsCollapse
		? `bt-grid-${collapseBelow}-${String(columns).replace(/[^a-zA-Z0-9]+/g, "-")}`
		: "";

	if (!needsCollapse) {
		return (
			<div className={className} style={baseStyle} {...rest}>
				{children}
			</div>
		);
	}

	const maxWidth = COLLAPSE_MEDIA[collapseBelow];

	return (
		<>
			<style>{`
				@media (max-width: ${maxWidth}) {
					.${collapseId} {
						grid-template-columns: minmax(0, 1fr) !important;
					}
				}
			`}</style>
			<div className={`${collapseId} ${className}`.trim()} style={baseStyle} {...rest}>
				{children}
			</div>
		</>
	);
};

interface GridItemProps extends HTMLAttributes<HTMLDivElement> {
	children: ReactNode;
	/**
	 * Column span. Number of tracks, or `"full"` for the entire row.
	 * Default `1`.
	 */
	span?: number | "full";
	/** Explicit column start (1-based). */
	colStart?: number;
	/** Explicit row start (1-based). */
	rowStart?: number;
	/** Row span (default 1). */
	rowSpan?: number;
}

/**
 * Optional grid child with span / placement. Plain children of {@link Grid}
 * also work; use this when you need a cell to span multiple tracks.
 */
export const GridItem = ({
	children,
	span = 1,
	colStart,
	rowStart,
	rowSpan,
	className = "",
	style,
	...rest
}: GridItemProps) => {
	const itemStyle: CSSProperties = {
		minWidth: 0,
		gridColumn:
			span === "full"
				? "1 / -1"
				: colStart != null
					? `${colStart} / span ${span}`
					: span > 1
						? `span ${span}`
						: undefined,
		gridRow:
			rowStart != null
				? `${rowStart} / span ${rowSpan ?? 1}`
				: rowSpan && rowSpan > 1
					? `span ${rowSpan}`
					: undefined,
		...style,
	};

	return (
		<div className={className} style={itemStyle} {...rest}>
			{children}
		</div>
	);
};
