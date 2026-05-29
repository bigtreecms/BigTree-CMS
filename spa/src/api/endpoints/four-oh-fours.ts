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

	create: (body: { from: string; to: string; site_key?: string }) =>
		api.post<FourOhFour>("/404s", body),

	delete: (id: number) => api.delete<void>(`/404s/${id}`),

	setRedirect: (id: number, url: string) => api.post<FourOhFour>(`/404s/${id}/redirect`, { url }),

	ignore: (id: number) => api.post<void>(`/404s/${id}/ignore`),

	clearDead: () => api.post<ClearDeadResult>("/404s/clear-dead", {}),

	bulkDelete: (ids: number[]) => api.post<void>("/404s/bulk-delete", { ids }),
};
