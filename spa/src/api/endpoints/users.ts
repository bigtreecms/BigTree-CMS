import { api } from "@/api/client";

/**
 * User shapes returned by the BigTree API.
 *
 * The list endpoint (UserService::presentList) returns a thin row; the get
 * endpoint (UserService::presentFull) adds `two_factor_enabled` plus the
 * full `permissions` and `alerts` JSON blobs (only visible to self or admin).
 */

export interface UserListItem {
	id: number;
	email: string;
	name: string;
	company: string;
	level: number;
	daily_digest: boolean;
	timezone: string;
}

/**
 * Per-resource permission codes used inside the permissions JSON blob.
 *
 *   "p" — Publisher / Creator (publish or upload)
 *   "e" — Editor / Consumer (edit or use)
 *   "n" — No Access
 *   "i" — Inherit from parent (pages + folders only; modules don't inherit)
 *   ""  — Unset (equivalent to "i" when applicable)
 */
export type PermissionCode = "p" | "e" | "n" | "i" | "";

export interface UserPermissions {
	/** Per-page permission map, keyed by page id (0 = root / All Pages). */
	page?: Record<string, PermissionCode>;
	/** Per-module permission map, keyed by module id. */
	module?: Record<string, PermissionCode>;
	/** Per-resource-folder permission map, keyed by folder id (0 = Home). */
	resources?: Record<string, PermissionCode>;
	/** Per-module group-based-permission map, keyed by module id → category id. */
	module_gbp?: Record<string, Record<string, PermissionCode>>;
}

/** Content alerts: `{ pageId: "on" }` triggers an alert when the page goes stale. */
export type UserAlerts = Record<string, "on" | "" | null>;

export interface UserDetail {
	id: number;
	email: string;
	name: string;
	company: string;
	level: number;
	daily_digest: boolean;
	timezone: string;
	two_factor_enabled: boolean;
	permissions?: UserPermissions;
	alerts?: UserAlerts;
}

export interface CreateUserPayload {
	email: string;
	name: string;
	company?: string;
	level: number;
	password?: string;
	timezone?: string;
	daily_digest?: boolean;
	alerts?: UserAlerts;
	permissions?: UserPermissions;
}

export interface UpdateUserPayload {
	email?: string;
	name?: string;
	company?: string;
	level?: number;
	timezone?: string;
	daily_digest?: boolean;
	alerts?: UserAlerts;
	permissions?: UserPermissions;
}

export interface ChangePasswordPayload {
	new_password: string;
	/** Required when changing your own password; ignored when an admin changes someone else's. */
	current_password?: string;
}

export interface UsersListResponse {
	items: UserListItem[];
	meta: {
		page?: number;
		per_page?: number;
		total?: number;
		pages?: number;
	};
}

export const usersApi = {
	list: (params: { q?: string; page?: number; per_page?: number } = {}) =>
		api.getWithMeta<UserListItem[]>("/users", { query: params }).then((res) => ({
			items: res.data,
			meta: res.meta ?? {},
		})),

	get: (id: number) => api.get<UserDetail>(`/users/${id}`),

	me: () => api.get<UserDetail>("/users/me"),

	create: (payload: CreateUserPayload) => api.post<UserDetail>("/users", payload),

	update: (id: number, payload: UpdateUserPayload) =>
		api.patch<UserDetail>(`/users/${id}`, payload),

	delete: (id: number) => api.delete<void>(`/users/${id}`),

	password: (id: number, payload: ChangePasswordPayload) =>
		api.post<void>(`/users/${id}/password`, payload),
};

export type UserLevelLabel = "Normal User" | "Administrator" | "Developer";

/**
 * Map numeric level from the API to the display labels used in the UI.
 * Matches the canonical BigTree admin terminology:
 *
 *   0 — Normal User:    per-resource access only
 *   1 — Administrator:  full content access (pages, modules, files, settings)
 *   2 — Developer:      Administrator + access to the Developer section
 */
export function levelToLabel(level: number): UserLevelLabel {
	if (level >= 2) return "Developer";
	if (level === 1) return "Administrator";
	return "Normal User";
}

/**
 * Map UI label back to the numeric level the API expects on create/update.
 */
export function labelToLevel(label: UserLevelLabel): number {
	if (label === "Developer") return 2;
	if (label === "Administrator") return 1;
	return 0;
}
