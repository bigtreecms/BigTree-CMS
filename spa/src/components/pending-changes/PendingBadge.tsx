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
	<span className="inline-flex items-center gap-1 rounded-full bg-warn-bg px-1.5 py-0.5 text-[10.5px] font-medium uppercase tracking-[0.04em] text-warn">
		<span className="h-1.5 w-1.5 rounded-full bg-current" />
		{label}
	</span>
);
