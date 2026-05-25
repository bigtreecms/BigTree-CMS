import { api } from "@/api/client";

/**
 * Wire shapes for pages. These match what PageService returns (look at the
 * present() helper in core/inc/bigtree/services/PageService.php). We split
 * the list-row shape from the full-page shape — list responses are deliberately
 * thinner so the tree-load endpoint stays cheap on sites with thousands of pages.
 */

export type PageAccess = "n" | "v" | "e" | "p";

export interface PageListRow {
	id: number;
	parent: number;
	nav_title: string;
	route: string;
	in_nav: boolean;
	archived: boolean;
	trunk: boolean;
	position: number;
	template: string;
	external: string;
	updated_at: string;
	access: PageAccess;
	/** True when there is an unpublished change in bigtree_pending_changes for this page. */
	has_pending_change?: boolean;

	/** Present when this item represents a brand new page that only exists as a pending draft (type=NEW in bigtree_pending_changes). */
	pending_change_id?: number;

	/** True for pending "NEW" page drafts that do not yet exist in bigtree_pages. */
	pending?: boolean;
}

export interface PageDetail {
	id: number;
	parent: number;
	trunk: boolean;
	in_nav: boolean;
	nav_title: string;
	route: string;
	path: string;
	title: string;
	meta_keywords: string;
	meta_description: string;
	seo_invisible: boolean;
	template: string;
	external: string;
	new_window: boolean;
	resources: Record<string, unknown>;
	archived: boolean;
	archived_inherited: boolean;
	publish_at: string | null;
	expire_at: string | null;
	max_age: number;
	last_edited_by: number;
	position: number;
	created_at: string;
	updated_at: string;
	tags?: Array<{
		id: number;
		tag: string;
		route: string;
		usage_count: number;
	}>;
	open_graph?: {
		title: string;
		description: string;
		type: string;
		image: string;
		image_width: number;
		image_height: number;
	} | null;
	lineage?: Array<{ id: number; nav_title: string; route: string }>;
}

export const pagesApi = {
	list: (parent: number, includeArchived = false) =>
		api.get<PageListRow[]>("/pages", {
			query: { parent, include_archived: includeArchived },
		}),

	get: (id: number, opts: { lineage?: boolean } = {}) =>
		api.get<PageDetail>(`/pages/${id}`, {
			query: opts.lineage ? { fields: "lineage" } : undefined,
		}),

	patch: (
		id: number,
		body: Partial<
			Pick<PageDetail, "nav_title" | "title" | "in_nav" | "template" | "external" | "route">
		>
	) => api.patch<PageDetail>(`/pages/${id}`, body),

	archive: (id: number) => api.post<void>(`/pages/${id}/archive`),
	unarchive: (id: number) => api.post<void>(`/pages/${id}/unarchive`),
	delete: (id: number) => api.delete<void>(`/pages/${id}`),

	reorder: (parent: number, ids: number[]) => api.post<void>(`/pages/${parent}/reorder`, { ids }),
};
