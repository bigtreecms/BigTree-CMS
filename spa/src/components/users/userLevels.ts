import type { AuthUser } from "@/auth/store";
import type { UserLevelLabel } from "@/api/endpoints/users";
import { isDeveloper, type Level } from "@/lib/permissions";

export interface UserLevel {
	value: Level;
	label: UserLevelLabel;
	/** Explains the level under the "User level" select on the add / edit forms. */
	hint: string;
}

/**
 * The canonical user-level source of truth for the admin UI — numeric values as
 * the API stores them, paired with the label and hint copy the Users add form
 * and the User edit form both render. Keep the hints context-neutral: they are
 * shown before a user exists (add) and after (edit).
 */
export const USER_LEVELS: UserLevel[] = [
	{
		value: 0,
		label: "Normal User",
		hint: "Access only to the pages, modules, and folders granted to them.",
	},
	{
		value: 1,
		label: "Administrator",
		hint: "Manage pages, modules, files, settings, and other users.",
	},
	{
		value: 2,
		label: "Developer",
		hint: "Full access including the Developer section (templates, module designer, configure, debug).",
	},
];

/**
 * Levels the given user is allowed to assign. Only a Developer may mint another
 * Developer; administrators see the first two.
 */
export const assignableUserLevels = (currentUser: AuthUser | null | undefined): UserLevel[] => {
	if (isDeveloper(currentUser)) {
		return USER_LEVELS;
	}

	return USER_LEVELS.filter((level) => level.value !== 2);
};

/** `assignableUserLevels` shaped for `SelectField`, whose values are strings. */
export const userLevelOptions = (
	currentUser: AuthUser | null | undefined
): { value: string; label: string }[] => {
	return assignableUserLevels(currentUser).map(({ value, label }) => ({
		value: String(value),
		label,
	}));
};

/** The hint copy for a numeric level, clamped the way `levelToLabel` clamps. */
export const userLevelHint = (level: number): string => {
	const clamped = Math.min(Math.max(level, 0), 2);

	return USER_LEVELS.find((entry) => entry.value === clamped)!.hint;
};
