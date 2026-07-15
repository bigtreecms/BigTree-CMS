import { useQuery } from "@tanstack/react-query";

import { dbApi } from "@/api/endpoints/db";
import { queryKeys } from "@/lib/queryKeys";

interface UseDbColumnsOptions {
	/**
	 * Override the default `table !== ""` gate with the caller's own condition
	 * (e.g. the resource designer's column-bound flag, or an editing guard).
	 */
	enabled?: boolean;
	/** Fetch the sort-aware column listing (the `db_column_sort` control). */
	sort?: boolean;
}

/**
 * Columns of a chosen table (GET /db/tables/{table}/columns), shared by the
 * designer column pickers (DataColumnSelect, ResourceDesigner, the module
 * views/reports tabs, the field-settings ColumnSelectControl). Disabled until a
 * table is chosen unless an explicit `enabled` is passed; cached for 5 minutes.
 */
export const useDbColumns = (table: string, options?: UseDbColumnsOptions) => {
	const sort = options?.sort ?? false;

	return useQuery({
		queryKey: queryKeys.db.columns(table, sort),
		queryFn: () => dbApi.columns(table, sort),
		enabled: options?.enabled ?? table !== "",
		staleTime: 5 * 60 * 1000,
	});
};
