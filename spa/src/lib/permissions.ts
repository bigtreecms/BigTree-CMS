import type { AuthUser } from "@/auth/store";
import type { PageAccess } from "@/api/endpoints/pages";
import type { PermissionCode, UserAlerts } from "@/api/endpoints/users";

/**
 * Numeric level conventions used throughout the BigTree admin:
 *
 *   0 — Normal User:    per-resource access only (must be granted via the
 *                       per-page / per-module / per-folder permissions tree)
 *   1 — Administrator:  full content access (pages, modules, files, settings)
 *   2 — Developer:      Administrator + the Developer section (templates,
 *                       module designer, configure, debug, extensions)
 */
export const LEVEL = {
	NORMAL: 0,
	ADMINISTRATOR: 1,
	DEVELOPER: 2,
} as const;

export type Level = (typeof LEVEL)[keyof typeof LEVEL];

export const isAdmin = (user: AuthUser | null | undefined): boolean => {
	return !!user && user.level >= LEVEL.ADMINISTRATOR;
};

export const isDeveloper = (user: AuthUser | null | undefined): boolean => {
	return !!user && user.level >= LEVEL.DEVELOPER;
};

export const hasLevel = (user: AuthUser | null | undefined, required: number): boolean => {
	return !!user && user.level >= required;
};

/**
 * Page-access codes follow BigTree's longstanding scheme:
 *
 *   "n" — no access
 *   "v" — view only (read)
 *   "e" — edit
 *   "p" — publish (implies edit)
 */
export const canViewPage = (access: PageAccess | undefined): boolean => {
	return access === "v" || access === "e" || access === "p";
};

export const canEditPage = (access: PageAccess | undefined): boolean => {
	return access === "e" || access === "p";
};

export const canPublishPage = (access: PageAccess | undefined): boolean => {
	return access === "p";
};

/**
 * Apply a per-resource permission choice to a permission map, returning a new
 * map. Selecting "no access" ("") or "inherit" ("i") removes the entry (so the
 * row falls back to inherited access); any other code assigns it. Shared by the
 * page / module / resource-folder permission trees.
 */
export const applyPermission = (
	map: Record<string, PermissionCode> | undefined,
	id: string,
	perm: PermissionCode
): Record<string, PermissionCode> => {
	const next = { ...(map ?? {}) };

	if (perm === "" || perm === "i") {
		delete next[id];
	} else {
		next[id] = perm;
	}

	return next;
};

/**
 * Toggle a per-page change-alert flag on/off, returning a new alerts map. "On"
 * stores "on"; "off" removes the entry entirely (the PHP admin's convention).
 */
export const toggleFlag = (map: UserAlerts, id: string, on: boolean): UserAlerts => {
	const next = { ...map };

	if (on) {
		next[id] = "on";
	} else {
		delete next[id];
	}

	return next;
};
