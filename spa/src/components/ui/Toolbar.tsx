import type { ReactNode } from "react";

interface ToolbarProps {
	/** Right-pushed content after the flex spacer (pager, filters, actions). */
	children?: ReactNode;
	className?: string;
	/** Left-pinned content (typically a {@link SearchInput}). Gets the shared responsive width recipe. */
	search?: ReactNode;
}

/**
 * The list/table toolbar row that sits above almost every list view: a
 * left-pinned search box, a flex spacer, then right-aligned controls (pager,
 * filters, actions). Owns the `mb-3 flex flex-wrap items-center gap-3` row and
 * the `w-full sm:w-auto sm:max-w-md sm:flex-1` responsive search-width recipe so
 * every toolbar stays in lockstep — especially the way it wraps on small screens.
 */
export const Toolbar = ({ search, children, className }: ToolbarProps) => {
	return (
		<div
			className={`mb-3 flex flex-wrap items-center gap-3${className ? ` ${className}` : ""}`}
		>
			{search && <div className="w-full sm:w-auto sm:max-w-md sm:flex-1">{search}</div>}

			{children && (
				<>
					<div className="flex-1" />
					{children}
				</>
			)}
		</div>
	);
};
