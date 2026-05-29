import { api } from "@/api/client";

import type { ModuleFormField } from "@/api/endpoints/modules";

/**
 * Feeds — XML / JSON / RSS endpoints generated from a module's table. CRUD
 * lives in the Developer section; consumers fetch through the public-facing
 * /feeds/{route} URL which isn't part of this API surface.
 */

export interface FeedSummary {
	id: string;
	name: string;
	description: string;
	table: string;
	type: string;
	settings: Record<string, unknown> | unknown[];
	fields: ModuleFormField[];
}

export interface FeedEditBody {
	id?: string;
	name?: string;
	description?: string;
	table?: string;
	type?: string;
	settings?: Record<string, unknown> | unknown[];
	fields?: ModuleFormField[];
}

export const feedsApi = {
	list: () => api.get<FeedSummary[]>("/feeds"),

	get: (id: string) => api.get<FeedSummary>(`/feeds/${encodeURIComponent(id)}`),

	create: (body: FeedEditBody) => api.post<FeedSummary>("/feeds", body),

	update: (id: string, body: FeedEditBody) =>
		api.patch<FeedSummary>(`/feeds/${encodeURIComponent(id)}`, body),

	delete: (id: string) => api.delete<void>(`/feeds/${encodeURIComponent(id)}`),
};
