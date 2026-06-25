import type { ReactNode } from "react";

interface FieldGridProps {
	/** The fields to lay out — one column below `md`, two columns at `md` and up. */
	children: ReactNode;
	/** Layout-only classes the caller still owns (extra spacing, col-span overrides). */
	className?: string;
}

/**
 * The responsive two-column field grid that wraps paired form inputs: a single
 * column on small screens, two columns from `md` up. The single source of truth
 * for `grid grid-cols-1 gap-4 md:grid-cols-2`, which was hand-rolled across the
 * module designer and developer edit pages. Pairs naturally with `FormShell`.
 */
export const FieldGrid = ({ children, className }: FieldGridProps) => {
	return (
		<div className={`grid grid-cols-1 gap-4 md:grid-cols-2${className ? ` ${className}` : ""}`}>
			{children}
		</div>
	);
};
