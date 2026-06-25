import type { ModuleEntryRow } from "@/api/endpoints/auto-modules";
import type { BadgeTone } from "@/components/ui/Badge";
import { StatusBadge } from "@/components/ui/StatusBadge";

import { statusFromRow, type StatusKey } from "./viewHelpers";

/**
 * The "Status" cell shared by every list-based view (draggable, searchable,
 * nested, grouped). Maps the entry's Published / Pending / Changed / Inactive
 * state onto the shared `ui/StatusBadge` text variant (an uppercase color-coded
 * label, matching the legacy admin).
 *
 * Kept deliberately full-opacity: the surrounding view dims the rest of a muted
 * (pending/changed) row, but the status label always stays legible.
 */

// Entry status → badge tone. Published reads as "live" (green); pending/changed
// need attention (warn); inactive is muted. Folds the old `statusTextClass` color
// map into the shared StatusBadge's tone → color mapping.
const STATUS_TONE: Record<StatusKey, BadgeTone> = {
	published: "success",
	pending: "warn",
	changed: "warn",
	inactive: "neutral",
};

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

	return (
		<StatusBadge
			variant="text"
			tone={STATUS_TONE[status.key]}
			label={status.label}
			plainOnMobile={plainOnMobile}
			className={className}
		/>
	);
};
