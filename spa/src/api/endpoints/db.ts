import { api } from "@/api/client";

/**
 * Database introspection for the field-settings designer controls
 * (db_table / db_column / db_column_sort). Backed by GET /db/tables and
 * GET /db/tables/{table}/columns. Developer-gated server-side.
 */

export interface DbOption {
	label: string;
	/**
	 * SQL column type (e.g. "varchar", "date", "datetime"). Present on column
	 * options in the non-sorted listing; used by the report designer to default
	 * a filter type. Absent on table options and sorted column options.
	 */
	type?: string;
	value: string;
}

export const dbApi = {
	tables: () => api.get<DbOption[]>("/db/tables"),

	columns: (table: string, sort = false) =>
		api.get<DbOption[]>(`/db/tables/${encodeURIComponent(table)}/columns`, {
			query: sort ? { sort: true } : undefined,
		}),
};
