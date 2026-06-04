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

	/** Whether this page has any direct child pages (computed server-side via efficient EXISTS subquery on the parent index). */
	has_children: boolean;

	/** Present when this item represents a brand new page that only exists as a pending draft (type=NEW in bigtree_pending_changes). */
	pending_change_id?: number;

	/** True for pending "NEW" page drafts that do not yet exist in bigtree_pages. */
	pending?: boolean;

	/** ISO timestamp when the page is scheduled to be published (null if not scheduled). */
	publish_at?: string | null;
	/** ISO timestamp when the page is scheduled to expire (null if not set). */
	expire_at?: string | null;

	/** Server-computed flag indicating the page has a future publish_at (avoids client timezone issues). */
	scheduled?: boolean;
}

export interface PageDetail {
	id: number;
	parent: number;
	/** Caller's permission level for this page; "p" unlocks "Save & Publish". */
	access: PageAccess;
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
	/**
	 * Cached last-30-days page views from the Google Analytics 4 sync. `null` until
	 * the first sync runs, or when analytics isn't connected.
	 */
	ga_page_views: number | null;
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

	/** True when the payload reflects an unpublished draft overlaid on (or in place of) the live page. */
	changes_applied?: boolean;
	/** The bigtree_pending_changes id backing this draft, when `changes_applied`. */
	pending_change_id?: number;
	/** True for a NEW draft that only exists in bigtree_pending_changes (no live row; `id` is 0). */
	pending?: boolean;
}

/** Body shape for POST /pages and PATCH /pages/{id} edits. */
export interface PageEditBody {
	parent?: number;
	nav_title?: string;
	title?: string;
	route?: string;
	in_nav?: boolean;
	meta_keywords?: string;
	meta_description?: string;
	seo_invisible?: boolean;
	template?: string;
	external?: string;
	new_window?: boolean;
	/** Field id → value map for the template's `resources`. */
	resources?: Record<string, unknown>;
	publish_at?: string | null;
	expire_at?: string | null;
	max_age?: number;
	tags?: number[];
	open_graph?: {
		title?: string;
		description?: string;
		type?: string;
		image?: string;
		image_width?: number;
		image_height?: number;
	};
	trunk?: boolean;
	/**
	 * When true, write live (requires publisher access). When false/omitted, the
	 * save is queued as a pending change (draft) for a publisher to approve.
	 */
	publish?: boolean;
}

export interface PageRevision {
	id: number;
	page: number;
	title: string;
	author: number;
	saved: boolean;
	saved_description: string;
	updated_at: string;
}

/** Search hit returned by GET /pages/search. */
export interface PageSearchHit {
	id: number;
	nav_title: string;
	path: string;
	archived: boolean;
}

/** Returned by create/patch when the save was queued as a draft instead of published live. */
export interface PagePendingResult {
	pending: true;
	pending_change_id: number;
}

/**
 * SEO rating for a page, computed server-side by the legacy algorithm
 * (`BigTreeAdmin::getPageSEORating`). `available` is false when the page can't be
 * rated (e.g. external link / removed template), in which case `score`/`color`
 * are null.
 */
export interface PageSeoRating {
	available: boolean;
	score: number | null;
	recommendations: string[];
	color: string | null;
}

export const isPendingResult = (r: unknown): r is PagePendingResult =>
	typeof r === "object" && r !== null && (r as { pending?: unknown }).pending === true;

export const pagesApi = {
	list: (parent: number, includeArchived = false) =>
		api.get<PageListRow[]>("/pages", {
			query: { parent, include_archived: includeArchived },
		}),

	get: (id: number, opts: { lineage?: boolean; pending?: boolean } = {}) =>
		api.get<PageDetail>(`/pages/${id}`, {
			query: {
				...(opts.lineage ? { fields: "lineage" } : {}),
				...(opts.pending ? { pending: true } : {}),
			},
		}),

	/** Load a NEW page draft (lives only in bigtree_pending_changes) by its change id. */
	getPending: (pcid: number, opts: { lineage?: boolean } = {}) =>
		api.get<PageDetail>(`/pages/pending/${pcid}`, {
			query: opts.lineage ? { fields: "lineage" } : undefined,
		}),

	/** Re-save a NEW page draft, or promote it to live (body.publish = true). */
	patchPending: (pcid: number, body: PageEditBody) =>
		api.patch<PageDetail | PagePendingResult>(`/pages/pending/${pcid}`, body),

	search: (q: string) => api.get<PageSearchHit[]>("/pages/search", { query: { q } }),

	seoRating: (id: number) => api.get<PageSeoRating>(`/pages/${id}/seo-rating`),

	create: (body: PageEditBody) => api.post<PageDetail | PagePendingResult>("/pages", body),

	patch: (id: number, body: PageEditBody) =>
		api.patch<PageDetail | PagePendingResult>(`/pages/${id}`, body),

	archive: (id: number) => api.post<void>(`/pages/${id}/archive`),
	unarchive: (id: number) => api.post<void>(`/pages/${id}/unarchive`),
	delete: (id: number) => api.delete<void>(`/pages/${id}`),

	move: (id: number, parent: number) => api.post<void>(`/pages/${id}/move`, { parent }),

	reorder: (parent: number, ids: number[]) => api.post<void>(`/pages/${parent}/reorder`, { ids }),

	revisions: {
		list: (id: number) => api.get<PageRevision[]>(`/pages/${id}/revisions`),

		save: (id: number, description: string) =>
			api.post<{ id: number }>(`/pages/${id}/revisions`, { description }),

		delete: (id: number, revisionId: number) =>
			api.delete<void>(`/pages/${id}/revisions/${revisionId}`),

		restore: (id: number, revisionId: number) =>
			api.post<void>(`/pages/${id}/revisions/${revisionId}/restore`, {}),
	},
};
