import { useMemo, useState } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";

import {
	autoModulesApi,
	type ModuleEntriesListParams,
	type ModuleEntryRow,
} from "@/api/endpoints/auto-modules";
import { queryKeys } from "@/lib/queryKeys";
import type { ModuleView } from "@/api/endpoints/modules";
import { useModuleEntryLinks } from "@/pages/ModuleLayout";
import { useDebouncedValue } from "@/hooks/useDebouncedValue";

import { isPersistedEntryId, parseViewActions } from "./viewHelpers";

interface UseModuleEntriesOptions {
	/**
	 * Keep the previous page's rows visible while the next query resolves —
	 * for the paginated (`SearchableView`) and optimistic-reorder
	 * (`DraggableView`) views. Off by default.
	 */
	keepPrevious?: boolean;
	/**
	 * Extra list params (page, sort) merged into the query and its key.
	 * `SearchableView` passes its pager/sort state through here; `NestedView`
	 * pins the legacy `position DESC, id ASC` sort. `view` and `q` are supplied
	 * by the hook and shouldn't be repeated here.
	 */
	listParams?: Omit<ModuleEntriesListParams, "view" | "q">;
	moduleId: string;
	view: ModuleView;
}

/**
 * Shared scaffold for the module list-view runtimes (`searchable`, `grouped`,
 * `nested`, `draggable`, `images`, `images-grouped`). Owns the debounced search
 * box state, the parsed view actions, the `/modules/{id}/entries` query, and the
 * row → edit-screen navigation guard that was hand-rolled identically in every
 * view. Views layer their own grouping / tree / drag state on top of `rows`
 * (or `listQuery.data`).
 */
export const useModuleEntries = ({
	moduleId,
	view,
	listParams,
	keepPrevious,
}: UseModuleEntriesOptions) => {
	const navigate = useNavigate();
	const { editPath } = useModuleEntryLinks();
	const [query, setQuery] = useState("");
	const debouncedQuery = useDebouncedValue(query, 200);

	const { builtins, custom } = useMemo(() => parseViewActions(view.actions), [view.actions]);
	const fieldColumns = useMemo(() => Object.entries(view.fields ?? {}), [view.fields]);

	const params: ModuleEntriesListParams = {
		view: view.id,
		q: debouncedQuery || undefined,
		...listParams,
	};

	const listQuery = useQuery({
		queryKey: queryKeys.moduleEntries.viewQuery(moduleId, view.id, params),
		queryFn: () => autoModulesApi.list(moduleId, params),
		...(keepPrevious ? { placeholderData: keepPreviousData } : {}),
	});

	const rows = listQuery.data?.items ?? [];

	const openEdit = (row: ModuleEntryRow) => {
		if (!builtins.edit || !isPersistedEntryId(row.id)) {
			return;
		}

		navigate(editPath(row.id));
	};

	return {
		query,
		setQuery,
		debouncedQuery,
		builtins,
		custom,
		fieldColumns,
		listQuery,
		rows,
		openEdit,
	};
};
