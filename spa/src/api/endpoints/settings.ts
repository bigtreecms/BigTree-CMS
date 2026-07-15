import { api } from "@/api/client";

/**
 * Settings — config values stored in bigtree_settings, definitions in JSONDB.
 *
 *   GET    /settings              paginated, ?q= search, optional ?include_encrypted=true (level 2)
 *   GET    /settings/{id}         single
 *   PATCH  /settings/{id}         when body is `{value}` only, the service does
 *                                 a fast value-only path (no metadata churn);
 *                                 with any other key the whole definition is
 *                                 updated. We always send `{value}` from here.
 *
 * Create / delete are level-2 / Developer-section concerns and live in their
 * own endpoint module under Phase 8. This module is read + value-update only.
 */

/**
 * Server-side `present()` shape. `value` is the JSON-decoded stored value (any
 * shape — depends on the setting's `type`). For encrypted settings the value
 * is `null` unless the caller is a publisher AND passes `include_encrypted=true`.
 */
export interface SettingDetail {
	description: string;
	encrypted: boolean;
	extension: string | null;
	id: string;
	locked: boolean;
	name: string;
	settings: Record<string, unknown> | unknown[];
	system: boolean;
	type: string;
	value: unknown;
	/** True when the server intentionally withheld the encrypted value. */
	value_omitted?: boolean;
}

export interface SettingListParams {
	/** Include settings flagged as `system` (hidden from the main list by default). */
	include_system?: boolean;
	page?: number;
	per_page?: number;
	q?: string;
}

export const settingsApi = {
	list: (params: SettingListParams = {}) =>
		api.getWithMeta<SettingDetail[]>("/settings", {
			query: {
				page: params.page,
				per_page: params.per_page,
				q: params.q,
				include_system: params.include_system ? true : undefined,
			},
		}),

	get: (id: string, opts: { includeEncrypted?: boolean } = {}) =>
		api.get<SettingDetail>(`/settings/${encodeURIComponent(id)}`, {
			query: opts.includeEncrypted ? { include_encrypted: true } : undefined,
		}),

	/**
	 * Value-only PATCH. The server detects this shape and skips the legacy
	 * definition rewrite path — exactly what we want from the user-facing
	 * editor (which never touches the schema).
	 */
	updateValue: (id: string, value: unknown) =>
		api.patch<SettingDetail>(`/settings/${encodeURIComponent(id)}`, { value }),

	/**
	 * Schema-update PATCH — used by the Developer admin pages to change a
	 * setting's definition (name, type, locked flag, etc.). The server
	 * detects this by the presence of any key other than `value`.
	 */
	updateDefinition: (id: string, body: Partial<SettingCreateBody>) =>
		api.patch<SettingDetail>(`/settings/${encodeURIComponent(id)}`, body),

	create: (body: SettingCreateBody) => api.post<SettingDetail>("/settings", body),

	delete: (id: string) => api.delete<void>(`/settings/${encodeURIComponent(id)}`),
};

export interface SettingCreateBody {
	description?: string;
	encrypted?: boolean;
	extension?: string;
	id: string;
	locked?: boolean;
	name?: string;
	settings?: Record<string, unknown> | unknown[];
	system?: boolean;
	type?: string;
}
