import { describe, expect, it } from "vitest";

import type { AuthUser } from "@/auth/store";
import {
	applyPermission,
	canEditPage,
	canPublishPage,
	canViewPage,
	hasLevel,
	isAdmin,
	isDeveloper,
	toggleFlag,
} from "@/lib/permissions";

// permissions.ts only depends on AuthUser/PageAccess as types, so a partial cast
// is enough — these helpers only ever read `level`.
const userAt = (level: number): AuthUser => ({ level }) as AuthUser;

describe("isAdmin", () => {
	it("is false for nullish users", () => {
		expect(isAdmin(null)).toBe(false);
		expect(isAdmin(undefined)).toBe(false);
	});

	it("requires level >= 1", () => {
		expect(isAdmin(userAt(0))).toBe(false);
		expect(isAdmin(userAt(1))).toBe(true);
		expect(isAdmin(userAt(2))).toBe(true);
	});
});

describe("isDeveloper", () => {
	it("requires level >= 2", () => {
		expect(isDeveloper(null)).toBe(false);
		expect(isDeveloper(userAt(1))).toBe(false);
		expect(isDeveloper(userAt(2))).toBe(true);
	});
});

describe("hasLevel", () => {
	it("compares level against the requirement, inclusive", () => {
		expect(hasLevel(userAt(1), 1)).toBe(true); // equal passes
		expect(hasLevel(userAt(0), 1)).toBe(false); // below fails
		expect(hasLevel(userAt(2), 1)).toBe(true); // above passes
	});

	it("is false for a nullish user regardless of requirement", () => {
		expect(hasLevel(null, 0)).toBe(false);
		expect(hasLevel(undefined, 1)).toBe(false);
	});
});

describe("page access helpers", () => {
	it("canViewPage is true for v/e/p, false for n/undefined", () => {
		expect(canViewPage("v")).toBe(true);
		expect(canViewPage("e")).toBe(true);
		expect(canViewPage("p")).toBe(true);
		expect(canViewPage("n")).toBe(false);
		expect(canViewPage(undefined)).toBe(false);
	});

	it("canEditPage is true for e/p, false for v/n/undefined", () => {
		expect(canEditPage("e")).toBe(true);
		expect(canEditPage("p")).toBe(true);
		expect(canEditPage("v")).toBe(false);
		expect(canEditPage("n")).toBe(false);
		expect(canEditPage(undefined)).toBe(false);
	});

	it("canPublishPage is true only for p", () => {
		expect(canPublishPage("p")).toBe(true);
		expect(canPublishPage("e")).toBe(false);
		expect(canPublishPage("v")).toBe(false);
		expect(canPublishPage("n")).toBe(false);
		expect(canPublishPage(undefined)).toBe(false);
	});
});

describe("applyPermission", () => {
	it("assigns a concrete code and returns a new map", () => {
		const map = { "1": "e" as const };
		const next = applyPermission(map, "2", "p");

		expect(next).toEqual({ "1": "e", "2": "p" });
		expect(next).not.toBe(map); // new reference
	});

	it("removes the entry for no-access and inherit", () => {
		const map = { "1": "e" as const, "2": "p" as const };

		expect(applyPermission(map, "1", "")).toEqual({ "2": "p" });
		expect(applyPermission(map, "2", "i")).toEqual({ "1": "e" });
	});

	it("tolerates an undefined map", () => {
		expect(applyPermission(undefined, "1", "n")).toEqual({ "1": "n" });
	});
});

describe("toggleFlag", () => {
	it("stores 'on' when enabled", () => {
		expect(toggleFlag({}, "5", true)).toEqual({ "5": "on" });
	});

	it("removes the entry when disabled", () => {
		expect(toggleFlag({ "5": "on" }, "5", false)).toEqual({});
	});
});
