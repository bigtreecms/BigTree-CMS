import { describe, expect, it } from "vitest";

import { draftOwnerLabel, fieldValuesEqual, isEmptyValue } from "@/lib/fieldComparison";

describe("isEmptyValue", () => {
	it("treats null, undefined, empty string and empty array as empty", () => {
		expect(isEmptyValue(null)).toBe(true);
		expect(isEmptyValue(undefined)).toBe(true);
		expect(isEmptyValue("")).toBe(true);
		expect(isEmptyValue([])).toBe(true);
	});

	it("treats real content as non-empty", () => {
		expect(isEmptyValue("x")).toBe(false);
		expect(isEmptyValue([1])).toBe(false);
		expect(isEmptyValue(0)).toBe(false);
	});
});

describe("fieldValuesEqual", () => {
	it("equates the indistinguishable 'absent' values", () => {
		expect(fieldValuesEqual(null, "")).toBe(true);
		expect(fieldValuesEqual(undefined, [])).toBe(true);
	});

	it("compares structurally for arrays and objects", () => {
		expect(fieldValuesEqual([1, 2], [1, 2])).toBe(true);
		expect(fieldValuesEqual({ a: 1 }, { a: 1 })).toBe(true);
		expect(fieldValuesEqual([1, 2], [2, 1])).toBe(false);
	});
});

describe("draftOwnerLabel", () => {
	it("says 'Your draft' when the owner is the current user", () => {
		expect(draftOwnerLabel(7, "Sam", 7)).toBe("Your draft");
	});

	it("attributes to the owner name when it's someone else", () => {
		expect(draftOwnerLabel(3, "Sam", 7)).toBe("Draft by Sam");
	});

	it("falls back to a neutral label when ownership is unknown", () => {
		expect(draftOwnerLabel(null, null, 7)).toBe("Pending draft");
	});
});
