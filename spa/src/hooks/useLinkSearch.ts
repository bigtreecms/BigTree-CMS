import { useRef, useState, type RefObject } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";

import { searchApi, type SearchModule, type SearchPage } from "@/api/endpoints/search";
import { resourcesApi } from "@/api/endpoints/resources";
import type { ResourceSummary } from "@/api/endpoints/resource-folders";

import { useDebouncedValue } from "@/hooks/useDebouncedValue";
import { useOnClickOutside } from "@/hooks/useOnClickOutside";
import { queryKeys } from "@/lib/queryKeys";

/** Searches shorter than this never reach the API. */
const MIN_QUERY_LENGTH = 2;

export interface UseLinkSearchOptions {
	/** Federated search types: `["pages"]` for the link field, `["pages", "modules"]` for the finder. */
	types: string[];
	/** Cap per group — sent to the search endpoint and applied to the resource slice. */
	limit: number;
	/** Extra gate ANDed with the open state (e.g. a field whose search setting is off). */
	enabled?: boolean;
}

export interface UseLinkSearchResult {
	query: string;
	setQuery: (next: string) => void;
	setOpen: (next: boolean) => void;
	/** Attach to the element that should keep the dropdown open while clicked inside. */
	containerRef: RefObject<HTMLDivElement>;
	/** The dropdown is open, enabled, and the debounced query is long enough to query on. */
	shouldSearch: boolean;
	pages: SearchPage[];
	modules: SearchModule[];
	files: ResourceSummary[];
	/** Both queries are still in flight. */
	isFetching: boolean;
	/** At least one query has returned for the current search. */
	hasData: boolean;
}

/**
 * The typeahead scaffold shared by the `link` field and the page editor's Link
 * Finder: debounced query state, close-on-outside-click, and the paired
 * federated-search + resource-search queries that both surfaces run.
 *
 * Rendering stays with the caller — one writes the picked reference into a field
 * value, the other copies it to the clipboard — as does the open/close wiring on
 * the input. Attach `containerRef` to the wrapper that bounds the dropdown.
 */
export const useLinkSearch = ({
	types,
	limit,
	enabled = true,
}: UseLinkSearchOptions): UseLinkSearchResult => {
	const [query, setQuery] = useState("");
	const [open, setOpen] = useState(false);
	const containerRef = useRef<HTMLDivElement>(null);
	const debounced = useDebouncedValue(query.trim(), 200);

	useOnClickOutside(containerRef, () => setOpen(false), open);

	const shouldSearch = open && enabled && debounced.length >= MIN_QUERY_LENGTH;

	const generalQuery = useQuery({
		queryKey: queryKeys.search.results(debounced, { types, limit }),
		queryFn: () => searchApi.search(debounced, { types, limit }),
		enabled: shouldSearch,
		placeholderData: keepPreviousData,
	});

	const filesQuery = useQuery({
		queryKey: queryKeys.resources.search(debounced),
		queryFn: () => resourcesApi.search(debounced),
		enabled: shouldSearch,
		placeholderData: keepPreviousData,
	});

	return {
		query,
		setQuery,
		setOpen,
		containerRef,
		shouldSearch,
		pages: generalQuery.data?.pages ?? [],
		modules: generalQuery.data?.modules ?? [],
		files: (filesQuery.data ?? []).slice(0, limit),
		isFetching: generalQuery.isFetching && filesQuery.isFetching,
		hasData: Boolean(generalQuery.data) || Boolean(filesQuery.data),
	};
};
