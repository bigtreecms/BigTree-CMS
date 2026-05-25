/**
 * Pill-shaped status badge matching the prototype's `.badge--{success|warn|info|...}`
 * styling. The colored dot + label combo signals state at a glance.
 */
export type PageStatus = "published" | "draft" | "scheduled" | "archived";

const VARIANTS: Record<PageStatus, { label: string; cls: string }> = {
	published: {
		label: "Published",
		cls: "bg-success-bg text-success",
	},
	draft: {
		label: "Draft",
		cls: "bg-warn-bg text-warn",
	},
	scheduled: {
		label: "Scheduled",
		cls: "bg-info-bg text-info",
	},
	archived: {
		label: "Archived",
		cls: "bg-surface-2 text-text-2",
	},
};

export function StatusBadge({ status }: { status: PageStatus }) {
	const v = VARIANTS[status];
	return (
		<span
			className={`inline-flex items-center gap-1.5 rounded-full px-1.5 py-0.5 text-[11px] font-medium ${v.cls}`}
		>
			<span className="h-1.5 w-1.5 rounded-full bg-current" />
			{v.label}
		</span>
	);
}
