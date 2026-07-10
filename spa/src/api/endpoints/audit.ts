import { api } from "@/api/client";

/**
 * Developer → Debug → Audit trail.
 *
 *   Read-only log of mutating actions. The `user` field is the actor; for
 *   deleted accounts the server falls back to a cached name (suffixed
 *   "(deleted)"). `entry` is the affected row's identifier within `table` —
 *   we surface it raw rather than resolving per-table deep links.
 */

export interface AuditContext {
	ip: string | null;
	user_agent: string | null;
	request_id: string | null;
	method: string | null;
	path: string | null;
}

export interface AuditEntry {
	id: number;
	user: number;
	user_name: string | null;
	user_email: string | null;
	table: string;
	entry: string;
	type: string;
	date: string;
	context?: AuditContext;
}

export interface AuditListParams {
	user?: number;
	table?: string;
	entry?: string;
	start?: string;
	end?: string;
	include?: string;
	page?: number;
	per_page?: number;
}

export const auditApi = {
	list: (params: AuditListParams = {}) => {
		const query: Record<string, string | number | undefined> = {
			user: params.user,
			table: params.table,
			entry: params.entry,
			start: params.start,
			end: params.end,
			include: params.include,
			page: params.page,
			per_page: params.per_page,
		};

		return api.listWithMeta<AuditEntry>("/audit", { query });
	},

	/** Every database table name, sorted — feeds the table filter's searchable select. */
	tables: () => api.get<string[]>("/audit/tables"),
};
