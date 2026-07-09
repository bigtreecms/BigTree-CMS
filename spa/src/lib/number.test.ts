import { describe, expect, it } from "vitest";

import { formatNumber, pluralize } from "@/lib/number";

describe("formatNumber", () => {
	it("returns an em dash for null/undefined/NaN", () => {
		expect(formatNumber(null)).toBe("—");
		expect(formatNumber(undefined)).toBe("—");
		expect(formatNumber(Number.NaN)).toBe("—");
	});

	it("formats zero as a real count, not an em dash", () => {
		expect(formatNumber(0)).toBe("0");
	});

	it("adds thousands separators", () => {
		expect(formatNumber(1234567)).toBe("1,234,567");
	});

	it("passes through Intl options", () => {
		expect(formatNumber(0.5, { style: "percent" })).toBe("50%");
	});
});

describe("pluralize", () => {
	it("uses the singular noun for a count of one", () => {
		expect(pluralize(1, "feed")).toBe("1 feed");
	});

	it("appends an 's' for any other count", () => {
		expect(pluralize(0, "feed")).toBe("0 feeds");
		expect(pluralize(3, "feed")).toBe("3 feeds");
	});

	it("honors an explicit plural for irregular nouns", () => {
		expect(pluralize(1, "entry", "entries")).toBe("1 entry");
		expect(pluralize(5, "entry", "entries")).toBe("5 entries");
	});
});
