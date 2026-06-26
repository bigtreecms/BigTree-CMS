import { useEffect, useState } from "react";

import { useDebouncedValue } from "@/hooks/useDebouncedValue";

export const usePaginatedSearch = (debounceMs = 200) => {
	const [query, setQuery] = useState("");
	const [page, setPage] = useState(1);
	const debouncedQuery = useDebouncedValue(query, debounceMs);

	useEffect(() => {
		setPage(1);
	}, [debouncedQuery]);

	return { query, setQuery, page, setPage, debouncedQuery };
};
