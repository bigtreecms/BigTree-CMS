import { api } from "@/api/client";

/**
 * User shapes returned by the BigTree API.
 *
 * The list endpoint (UserService::presentList) returns a thin row; the get
 * endpoint (UserService::presentFull) adds `two_factor_enabled` plus the
 * full `permissions` and `alerts` JSON blobs (only visible to self or admin).
 */

export interface UserListItem {
	company: string;
	daily_digest: boolean;
	email: string;
	id: number;
	level: number;
	name: string;
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
	/** Per-module permission map, keyed by module id. */
	module?: Record<string, PermissionCode>;
	/** Per-module group-based-permission map, keyed by module id → category id. */
	module_gbp?: Record<string, Record<string, PermissionCode>>;
	/** Per-page permission map, keyed by page id (0 = root / All Pages). */
	page?: Record<string, PermissionCode>;
	/** Per-resource-folder permission map, keyed by folder id (0 = Home). */
	resources?: Record<string, PermissionCode>;
}

/** Content alerts: `{ pageId: "on" }` triggers an alert when the page goes stale. */
export type UserAlerts = Record<string, "on" | "" | null>;

export interface UserDetail {
	alerts?: UserAlerts;
	company: string;
	daily_digest: boolean;
	email: string;
	id: number;
	level: number;
	name: string;
	permissions?: UserPermissions;
	timezone: string;
	two_factor_enabled: boolean;
}

export interface CreateUserPayload {
	alerts?: UserAlerts;
	company?: string;
	daily_digest?: boolean;
	email: string;
	level: number;
	name: string;
	password?: string;
	permissions?: UserPermissions;
	timezone?: string;
}

export interface UpdateUserPayload {
	alerts?: UserAlerts;
	company?: string;
	daily_digest?: boolean;
	email?: string;
	level?: number;
	name?: string;
	permissions?: UserPermissions;
	timezone?: string;
}

export interface ChangePasswordPayload {
	/** Required when changing your own password; ignored when an admin changes someone else's. */
	current_password?: string;
	new_password: string;
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
		api.listWithMeta<UserListItem>("/users", { query: params }),

	get: (id: number) => api.get<UserDetail>(`/users/${id}`),

	me: () => api.get<UserDetail>("/users/me"),

	create: (payload: CreateUserPayload) => api.post<UserDetail>("/users", payload),

	update: (id: number, payload: UpdateUserPayload) =>
		api.patch<UserDetail>(`/users/${id}`, payload),

	delete: (id: number) => api.delete<void>(`/users/${id}`),

	password: (id: number, payload: ChangePasswordPayload) =>
		api.post<void>(`/users/${id}/password`, payload),

	/** Developer-only: strip a user's TOTP secret when they've lost their device. */
	removeTwoFactor: (id: number) =>
		api.post<{ id: number; two_factor_enabled: boolean }>(`/users/${id}/2fa/remove`),
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
