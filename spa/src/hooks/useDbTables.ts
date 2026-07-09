import { useQuery } from "@tanstack/react-query";

import { dbApi } from "@/api/endpoints/db";
import { queryKeys } from "@/lib/queryKeys";

/**
 * The site's data tables (GET /db/tables), shared by every designer table
 * picker (DataTableSelect, the field-settings TableSelectControl). The list is
 * small and rarely changes, so it's cached for 5 minutes; a module scaffold
 * that creates a new table should invalidate {@link dbTablesQueryKey}.
 */
export const useDbTables = () =>
	useQuery({
		queryKey: queryKeys.db.tables(),
		queryFn: () => dbApi.tables(),
		staleTime: 5 * 60 * 1000,
	});

/** Query key for the /db/tables list — invalidate after creating a table. */
export const dbTablesQueryKey = () => queryKeys.db.tables();
