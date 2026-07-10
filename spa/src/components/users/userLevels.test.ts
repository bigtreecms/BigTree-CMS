import { describe, expect, it } from "vitest";

import type { AuthUser } from "@/auth/store";
import {
	USER_LEVELS,
	assignableUserLevels,
	userLevelHint,
	userLevelOptions,
} from "@/components/users/userLevels";

// The level helpers only ever read `level` off the current user, so a partial
// cast is enough — same shortcut lib/permissions.test.ts takes.
const userAt = (level: number): AuthUser => ({ level }) as AuthUser;

describe("assignableUserLevels", () => {
	it("lets a developer assign all three levels", () => {
		expect(assignableUserLevels(userAt(2))).toEqual(USER_LEVELS);
	});

	it("hides Developer from administrators and nullish users", () => {
		for (const user of [userAt(1), userAt(0), null, undefined]) {
			expect(assignableUserLevels(user).map((l) => l.label)).toEqual([
				"Normal User",
				"Administrator",
			]);
		}
	});
});

describe("userLevelOptions", () => {
	it("shapes the levels for SelectField's string values", () => {
		expect(userLevelOptions(userAt(2))).toEqual([
			{ value: "0", label: "Normal User" },
			{ value: "1", label: "Administrator" },
			{ value: "2", label: "Developer" },
		]);
	});
});

describe("userLevelHint", () => {
	it("returns the hint for each level", () => {
		expect(userLevelHint(0)).toBe(USER_LEVELS[0]!.hint);
		expect(userLevelHint(1)).toBe(USER_LEVELS[1]!.hint);
		expect(userLevelHint(2)).toBe(USER_LEVELS[2]!.hint);
	});

	it("clamps out-of-range levels the way levelToLabel does", () => {
		expect(userLevelHint(9)).toBe(USER_LEVELS[2]!.hint);
		expect(userLevelHint(-1)).toBe(USER_LEVELS[0]!.hint);
	});
});
