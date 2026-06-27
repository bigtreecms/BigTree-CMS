import { StatusBadge as UIStatusBadge } from "@/components/ui/StatusBadge";
import { PAGE_STATUS_VARIANTS } from "@/lib/statusMapping";

/**
 * Page-domain status pill. Maps the page lifecycle states onto the shared
 * `ui/StatusBadge` tone + label, rendered as the dotted pill matching the
 * prototype's `.badge--{success|warn|info|...}` styling.
 */
export type PageStatus = "published" | "draft" | "scheduled" | "archived" | "changed" | "pending";

interface StatusBadgeProps {
	status: PageStatus;
}

export const StatusBadge = ({ status }: StatusBadgeProps) => {
	const v = PAGE_STATUS_VARIANTS[status];

	return <UIStatusBadge tone={v.tone} label={v.label} dot />;
};
