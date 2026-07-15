import type { ReactNode } from "react";

interface PermissionRowProps {
	children: ReactNode;
	/** CSS `grid-template-columns` — must match the parent tree's header. */
	columns: string;
	/**
	 * Indentation depth (0-based). Each level adds 16px on top of the 12px
	 * base left-padding, matching the page/resource tree's visual hierarchy.
	 * Omit for non-hierarchical rows (module list).
	 */
	depth?: number;
	/**
	 * True for GBP sub-rows (nested category rows under a module). Renders
	 * with a dimmed background and fixed deep left-indent instead of
	 * depth-based indentation.
	 */
	nested?: boolean;
}

/**
 * Shared grid row wrapper for Page / Module / Resource permission trees.
 * Owns the border, background, and padding conventions so they don't diverge
 * across the three trees.
 */
export const PermissionRow = ({ columns, depth, nested, children }: PermissionRowProps) => {
	const paddingLeft = depth !== undefined ? `${12 + depth * 16}px` : undefined;

	return (
		<div
			className={`grid items-center gap-2 border-t border-border py-1.5 first:border-t-0 ${
				nested ? "bg-surface-2/40 pl-8 pr-3 text-[12px]" : "bg-surface px-3 text-[12.5px]"
			}`}
			style={{
				gridTemplateColumns: columns,
				...(paddingLeft ? { paddingLeft } : {}),
			}}
		>
			{children}
		</div>
	);
};
