import type { ResourceUsageLink } from "@/api/endpoints/resources";

/** /pages/:id/edit */
export const pageEditPath = (id: number | string) => `/pages/${id}/edit`;

/** /pages/draft/:pcid/edit — for pending-changes new-page drafts. */
export const pageDraftEditPath = (pcid: number | string) => `/pages/draft/${pcid}/edit`;

/** /pages/:id/edit/revisions */
export const pageRevisionsPath = (id: number | string) => `/pages/${id}/edit/revisions`;

/** /settings/:id/edit */
export const settingEditPath = (id: number | string) => `/settings/${id}/edit`;

/**
 * Build the edit path for a module entry.
 * Mirrors the shape `moduleActions` uses: `/modules/{route}/{editRoute}/{entryId}`.
 * Segments that are empty or null are omitted.
 */
export const moduleEntryEditPath = (
	route: string,
	editRoute: string | null | undefined,
	entryId: number | string
) => {
	const segments = ["modules", route, editRoute ?? "", String(entryId)].filter(
		(s) => s !== "" && s !== null
	);

	return `/${segments.join("/")}`;
};

/**
 * Resolve a resource-usage link descriptor into an in-app route.
 * Pages distinguish live (`/pages/{id}/edit`) from pending drafts
 * (`/pages/draft/{id}/edit`). Module entries follow the moduleActions URL
 * shape. Returns null when there is nowhere to navigate to.
 */
export const resourceUsagePath = (link: ResourceUsageLink | null): string | null => {
	if (!link) {
		return null;
	}

	if (link.kind === "page") {
		if (link.entry.startsWith("p")) {
			return pageDraftEditPath(link.entry.slice(1));
		}

		return pageEditPath(link.entry);
	}

	if (link.kind === "setting") {
		return settingEditPath(link.entry);
	}

	return moduleEntryEditPath(link.route, link.edit_route, link.entry);
};
