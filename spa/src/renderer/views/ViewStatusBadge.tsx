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
}

export const ViewStatusBadge = ({ row, className = "" }: ViewStatusBadgeProps) => {
	const status = statusFromRow(row);

	return (
		<span
			className={`whitespace-nowrap text-[11px] font-semibold uppercase tracking-[0.06em] ${statusTextClass[status.key]} ${className}`}
		>
			{status.label}
		</span>
	);
};
