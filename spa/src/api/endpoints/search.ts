import { api } from "@/api/client";

/** Result shapes returned by the federated GET /search endpoint (SearchService). */

export interface SearchPage {
	id: number;
	nav_title: string;
	path: string;
	archived: boolean;
}

export interface SearchTag {
	id: number;
	tag: string;
	route: string;
	usage_count: number;
}

export interface SearchUser {
	id: number;
	name: string;
	email: string;
	level: number;
}

export interface SearchModule {
	id: number;
	name: string;
	route: string;
	icon?: string;
}

export interface SearchModuleEntryGroup {
	module: { id: number; name: string; route: string };
	/** Raw view cache rows (column1, id, sort_field, etc.). Rendering is intentionally generic. */
	items: Array<Record<string, unknown>>;
}

export interface SearchResultGroups {
	pages?: SearchPage[];
	tags?: SearchTag[];
	users?: SearchUser[];
	modules?: SearchModule[];
	entries?: SearchModuleEntryGroup[];
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
};
