import type { ReactNode } from "react";

interface PermissionTreeHeaderProps {
	/** Header cells (first a label like "Folder"/"Module"/"Page", then the column headings). */
	children: ReactNode;
	/** CSS `grid-template-columns` — must match the matching tree's row grid. */
	columns: string;
}

/**
 * Shared bordered grid header row for the Page / Module / Resource permission
 * trees. Owns the canonical overline styling and rounded top border; callers
 * pass the matching `columns` grid template and the header cells.
 */
export const PermissionTreeHeader = ({ columns, children }: PermissionTreeHeaderProps) => {
	return (
		<div
			className="grid items-center gap-2 rounded-t-md border border-border bg-surface-2 px-3 py-2 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-text-3"
			style={{ gridTemplateColumns: columns }}
		>
			{children}
		</div>
	);
};
