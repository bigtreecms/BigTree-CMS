import { api } from "@/api/client";

/** Result shapes returned by the federated GET /search endpoint (SearchService). */

export interface SearchPage {
	archived: boolean;
	id: number;
	nav_title: string;
	path: string;
}

export interface SearchTag {
	id: number;
	route: string;
	tag: string;
	usage_count: number;
}

export interface SearchUser {
	email: string;
	id: number;
	level: number;
	name: string;
}

export interface SearchModule {
	icon?: string;
	/** JSONDB module ids are strings (e.g. `modules-…`); legacy numeric ids still appear. */
	id: number | string;
	name: string;
	route: string;
}

export interface SearchModuleEntryGroup {
	/** Raw view cache rows (column1, id, sort_field, etc.). Rendering is intentionally generic. */
	items: Array<Record<string, unknown>>;
	module: { id: number | string; name: string; route: string };
}

/** Semantic-only hit (administrators): the id can contain `.`/`/`. */
export interface SearchSetting {
	id: string;
	name: string;
}

export interface SearchResultGroups {
	entries?: SearchModuleEntryGroup[];
	modules?: SearchModule[];
	pages?: SearchPage[];
	settings?: SearchSetting[];
	tags?: SearchTag[];
	users?: SearchUser[];
}

/** Response from POST /search/ai when AI search is enabled. */
export interface AiSearchResponse {
	answer: string;
	mode: "ai";
	results: SearchResultGroups;
}

export const searchApi = {
	/**
	 * Federated quick search across pages, tags, users (level≥1), modules, and module entries.
	 * Matches the contract used by the legacy nav quick-search and the new SPA header.
	 */
	search: (q: string, opts: { limit?: number; types?: string[] } = {}) => {
		const query: Record<string, string | number> = { q: q.trim() };
		if (opts.limit) query.limit = opts.limit;
		if (opts.types && opts.types.length) query.types = opts.types.join(",");
		return api.get<SearchResultGroups>("/search", { query });
	},

	/**
	 * Agentic search powered by the site's configured AI service.
	 * Only available when features.ai_search is true on the auth user.
	 */
	aiSearch: (q: string, opts: { limit?: number } = {}) =>
		api.post<AiSearchResponse>("/search/ai", {
			q: q.trim(),
			limit: opts.limit,
		}),
};
