import type { ModuleEntryRow } from "@/api/endpoints/auto-modules";

import { statusFromRow, statusTextClass } from "./viewHelpers";

/**
 * The "Status" cell shared by every list-based view (draggable, searchable,
 * nested, grouped). Renders the entry's Published / Pending / Changed / Inactive
 * state as an uppercase label, color-coded to match the legacy admin.
 *
 * Kept deliberately full-opacity: the surrounding view dims the rest of a
 * muted (pending/changed) row, but the status label always stays legible.
 */

interface ViewStatusBadgeProps {
	row: ModuleEntryRow;
	className?: string;
	/**
	 * When set, render as plain value text on mobile and only take on the
	 * uppercase badge styling at `md+`. Used inside `DataTable`, which stacks
	 * its cells under their own labels on mobile — there the badge's all-caps
	 * styling would clash with the (also all-caps) column label above it, so the
	 * value should read like any other cell. Other views keep the badge at all
	 * sizes (default).
	 */
	plainOnMobile?: boolean;
}

export const ViewStatusBadge = ({
	row,
	className = "",
	plainOnMobile = false,
}: ViewStatusBadgeProps) => {
	const status = statusFromRow(row);

	const styleClasses = plainOnMobile
		? "text-[13px] md:text-[11px] md:font-semibold md:uppercase md:tracking-[0.06em]"
		: "text-[11px] font-semibold uppercase tracking-[0.06em]";

	return (
		<span
			className={`whitespace-nowrap ${styleClasses} ${statusTextClass[status.key]} ${className}`}
		>
			{status.label}
		</span>
	);
};
