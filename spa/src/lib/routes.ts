import type { ResourceUsageLink } from "@/api/endpoints/resources";

/** /pages/:id — view a page's children (Pages table drill-down). */
export const pagePath = (id: number | string) => `/pages/${id}`;

/** /pages/:id/edit */
export const pageEditPath = (id: number | string) => `/pages/${id}/edit`;

/** /pages/add/:parentId — add a subpage under parent. */
export const pageAddPath = (parentId: number | string) => `/pages/add/${parentId}`;

/** /pages/draft/:pcid/edit — for pending-changes new-page drafts. */
export const pageDraftEditPath = (pcid: number | string) => `/pages/draft/${pcid}/edit`;

/** /pages/:id/edit/revisions */
export const pageRevisionsPath = (id: number | string) => `/pages/${id}/edit/revisions`;

/** /developer/{section}/:id/edit — section is e.g. "callouts", "feeds", "templates". */
export const developerEditPath = (section: string, id: string | number) =>
	`/developer/${section}/${encodeURIComponent(id)}/edit`;

/** /settings/:id/edit — the id is URL-encoded (setting ids can contain `.`/`/`). */
export const settingEditPath = (id: number | string) => `/settings/${encodeURIComponent(id)}/edit`;

/** /developer/modules/:id — the module designer's detail/edit route. */
export const moduleDetailPath = (id: number | string) =>
	`/developer/modules/${encodeURIComponent(id)}`;

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
