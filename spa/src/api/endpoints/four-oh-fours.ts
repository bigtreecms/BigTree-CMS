import { api } from "@/api/client";

/**
 * 404 manager — paginated list of every captured 404 / 301 redirect / ignored
 * URL plus the actions the legacy admin exposes.
 */

export type FourOhFourType = "404" | "301" | "ignored";

export interface FourOhFour {
	id: number;
	broken_url: string;
	get_vars: string;
	redirect_url: string;
	requests: number;
	ignored: boolean;
	site_key: string | null;
	type: FourOhFourType;
}

export interface FourOhFoursListParams {
	page?: number;
	per_page?: number;
	type?: FourOhFourType;
	site_key?: string;
	q?: string;
}

export interface ClearDeadResult {
	before: number;
	after: number;
	deleted: number;
}

/**
 * A configured site in a multi-site install (from `GET /pages/sites`). Empty in
 * a single-site install — the 404 screens fall back to a plain, site-less form.
 */
export interface FourOhFourSite {
	key: string;
	domain: string;
	www_root: string;
}

export interface ImportCsvOptions {
	siteKey?: string;
	firstRowTitles?: boolean;
}

export const fourOhFoursApi = {
	list: (params: FourOhFoursListParams = {}) =>
		api.getWithMeta<FourOhFour[]>("/404s", {
			query: {
				page: params.page,
				per_page: params.per_page,
				type: params.type,
				site_key: params.site_key,
				q: params.q,
			},
		}),

	/**
	 * Unpaginated dump of a bucket, used to build the "Export CSV" download.
	 * Returns `{ data, meta }` so the caller can warn when the server capped the
	 * export (`meta.capped`) — the backend bounds the row count to protect memory.
	 */
	export: (type: FourOhFourType, siteKey?: string) =>
		api.getWithMeta<FourOhFour[]>("/404s/export", {
			query: { type, site_key: siteKey },
		}),

	/** Multi-site list (empty array in a single-site install). */
	sites: () => api.get<FourOhFourSite[]>("/pages/sites"),

	create: (body: { from: string; to: string; site_key?: string }) =>
		api.post<FourOhFour>("/404s", body),

	delete: (id: number) => api.delete<void>(`/404s/${id}`),

	setRedirect: (id: number, url: string) => api.post<FourOhFour>(`/404s/${id}/redirect`, { url }),

	ignore: (id: number) => api.post<void>(`/404s/${id}/ignore`),

	clearDead: () => api.post<ClearDeadResult>("/404s/clear-dead", {}),

	bulkDelete: (ids: number[]) => api.post<void>("/404s/bulk-delete", { ids }),

	importCsv: (file: File, opts: ImportCsvOptions = {}) => {
		const form = new FormData();
		form.append("file", file);

		if (opts.siteKey) {
			form.append("site_key", opts.siteKey);
		}

		if (opts.firstRowTitles) {
			form.append("first_row_titles", "1");
		}

		return api.post<{ imported: number; skipped: number }>("/404s/import", form);
	},
};
