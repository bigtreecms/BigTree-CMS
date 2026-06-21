import { Badge, type BadgeTone } from "@/components/ui/Badge";

/**
 * Pill-shaped status badge matching the prototype's `.badge--{success|warn|info|...}`
 * styling. The colored dot + label combo signals state at a glance.
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

	return (
		<Badge tone={v.tone} dot>
			{v.label}
		</Badge>
	);
};
