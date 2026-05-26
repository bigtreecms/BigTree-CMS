import { api } from "@/api/client";

/**
 * User shapes returned by the BigTree API (UserService::presentList).
 * Note: level is numeric (0=Normal, 1=Editor, 2=Administrator).
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

export interface CreateUserPayload {
	email: string;
	name: string;
	company?: string;
	level: number; // 0, 1 or 2
	// For the initial create flow we usually let the system send an invite
	// instead of setting a password directly.
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

	create: (payload: CreateUserPayload) => api.post<UserListItem>("/users", payload),

	delete: (id: number) => api.delete<void>(`/users/${id}`),
};

/**
 * Map numeric level from the API to the display labels used in the UI
 * (matching the design prototype).
 */
export function levelToLabel(level: number): "Administrator" | "Editor" | "Normal" {
	if (level >= 2) return "Administrator";
	if (level === 1) return "Editor";
	return "Normal";
}

/**
 * Map UI label back to the numeric level the API expects on create/update.
 */
export function labelToLevel(label: "Administrator" | "Editor" | "Normal"): number {
	if (label === "Administrator") return 2;
	if (label === "Editor") return 1;
	return 0;
}
