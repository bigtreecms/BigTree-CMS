import type { BadgeTone } from "@/components/ui/Badge";

export interface StatusVariant {
	label: string;
	tone: BadgeTone;
}

/** Page lifecycle states → badge label + tone. */
export const PAGE_STATUS_VARIANTS: Record<
	"published" | "draft" | "scheduled" | "archived" | "changed" | "pending",
	StatusVariant
> = {
	published: { label: "Published", tone: "success" },
	draft: { label: "Draft", tone: "warn" },
	changed: { label: "Changed", tone: "warn" },
	pending: { label: "Draft", tone: "warn" },
	scheduled: { label: "Scheduled", tone: "info" },
	archived: { label: "Archived", tone: "neutral" },
};

/** Resource usage status → badge label + tone. */
export const RESOURCE_USAGE_STATUS_VARIANTS: Record<
	"published" | "archived" | "pending" | "none",
	StatusVariant
> = {
	published: { label: "Published", tone: "success" },
	archived: { label: "Archived", tone: "warn" },
	pending: { label: "Pending Draft", tone: "info" },
	none: { label: "—", tone: "neutral" },
};
