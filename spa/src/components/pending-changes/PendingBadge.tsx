import { Badge } from "@/components/ui/Badge";

/**
 * Small "Pending" pill shown next to a form field whose draft value differs
 * from the published content. Mirrors the page tree's `.badge--warn` styling
 * (see StatusBadge) so pending state reads consistently across the admin.
 */

interface PendingBadgeProps {
	/** Label override — "New" for fields on a never-published draft entry. */
	label?: string;
}

export const PendingBadge = ({ label = "Pending" }: PendingBadgeProps) => (
	<Badge tone="warn" dot uppercase>
		{label}
	</Badge>
);
