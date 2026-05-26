import type { AuthUser } from "@/auth/store";
import type { PageAccess } from "@/api/endpoints/pages";

/**
 * Numeric level conventions used throughout the BigTree admin:
 *
 *   0 — Normal:        per-resource access only
 *   1 — Editor:        full content access except settings & developer
 *   2 — Administrator: everything, including the Developer section
 */
export const LEVEL = {
	NORMAL: 0,
	EDITOR: 1,
	ADMINISTRATOR: 2,
} as const;

export type Level = (typeof LEVEL)[keyof typeof LEVEL];

export const isAdmin = (user: AuthUser | null | undefined): boolean => {
	return !!user && user.level >= LEVEL.ADMINISTRATOR;
};

export const isEditor = (user: AuthUser | null | undefined): boolean => {
	return !!user && user.level >= LEVEL.EDITOR;
};

export const isDeveloper = (user: AuthUser | null | undefined): boolean => {
	// Developer = Administrator in BigTree's model. Kept as a named alias so
	// future "developer-only" sub-grants can be plumbed without callers changing.
	return isAdmin(user);
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
