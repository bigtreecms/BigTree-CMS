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
	method: string | null;
	path: string | null;
	request_id: string | null;
	user_agent: string | null;
	/** Change source: null for ordinary REST changes, "ai_assistant" for AI-approved ones. */
	via: string | null;
}

export interface AuditEntry {
	context?: AuditContext;
	date: string;
	entry: string;
	id: number;
	table: string;
	type: string;
	user: number;
	user_email: string | null;
	user_name: string | null;
}

export interface AuditListParams {
	end?: string;
	entry?: string;
	include?: string;
	page?: number;
	per_page?: number;
	start?: string;
	table?: string;
	user?: number;
	/** Filter by change source, e.g. "ai_assistant" for AI-approved changes only. */
	via?: string;
}

export const auditApi = {
	list: (params: AuditListParams = {}) => {
		const query: Record<string, string | number | undefined> = {
			user: params.user,
			table: params.table,
			entry: params.entry,
			start: params.start,
			end: params.end,
			via: params.via,
			include: params.include,
			page: params.page,
			per_page: params.per_page,
		};

		return api.listWithMeta<AuditEntry>("/audit", { query });
	},

	/** Every database table name, sorted — feeds the table filter's searchable select. */
	tables: () => api.get<string[]>("/audit/tables"),
};
