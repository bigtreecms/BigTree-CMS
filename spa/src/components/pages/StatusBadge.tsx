import type { BadgeTone } from "@/components/ui/Badge";
import { StatusBadge as UIStatusBadge } from "@/components/ui/StatusBadge";

/**
 * Page-domain status pill. Maps the page lifecycle states onto the shared
 * `ui/StatusBadge` tone + label, rendered as the dotted pill matching the
 * prototype's `.badge--{success|warn|info|...}` styling.
 */
export type PageStatus = "published" | "draft" | "scheduled" | "archived" | "changed" | "pending";

const VARIANTS: Record<PageStatus, { label: string; tone: BadgeTone }> = {
	published: {
		label: "Published",
		tone: "success",
	},
	draft: {
		label: "Draft",
		tone: "warn",
	},
	changed: {
		label: "Changed",
		tone: "warn",
	},
	pending: {
		label: "Draft",
		tone: "warn",
	},
	scheduled: {
		label: "Scheduled",
		tone: "info",
	},
	archived: {
		label: "Archived",
		tone: "neutral",
	},
};

interface StatusBadgeProps {
	status: PageStatus;
}

export const StatusBadge = ({ status }: StatusBadgeProps) => {
	const v = VARIANTS[status];

	return <UIStatusBadge tone={v.tone} label={v.label} dot />;
};
