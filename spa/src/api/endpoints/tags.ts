import { api } from "@/api/client";
import type { ApiMeta } from "@/types/api";

/**
 * Tag endpoints (`/tags`). The server normalizes incoming tag strings
 * (lowercase, alphanumerics only) and de-duplicates on create — POSTing
 * an existing tag returns the existing row rather than an error, which
 * is what the TagInput create-on-blur flow relies on.
 *
 * Permission notes:
 *   - GET endpoints accept level 0
 *   - POST / DELETE / POST /merge require level 1 (admin)
 */

export interface Tag {
	id: number;
	tag: string;
	route: string;
	usage_count: number;
}

export interface TagListParams {
	page?: number;
	per_page?: number;
	q?: string;
}

export interface TagListResponse {
	items: Tag[];
	meta: ApiMeta;
}

export interface MergePayload {
	into: number;
	from: number[];
}

export const tagsApi = {
	list: (params: TagListParams = {}) =>
		api
			.getWithMeta<Tag[]>("/tags", {
				query: {
					page: params.page,
					per_page: params.per_page,
					q: params.q,
				},
			})
			.then((res) => ({ items: res.data ?? [], meta: res.meta ?? {} })),

	get: (id: number) => api.get<Tag>(`/tags/${id}`),

	search: (q: string) => api.get<Tag[]>("/tags/search", { query: { q } }),

	/** Creates a new tag, or returns the existing row if the normalized form matches. */
	create: (tag: string) => api.post<Tag>("/tags", { tag }),

	delete: (id: number) => api.delete<void>(`/tags/${id}`),

	merge: (body: MergePayload) => api.post<Tag>("/tags/merge", body),
};
