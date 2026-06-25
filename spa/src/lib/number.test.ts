import { describe, expect, it } from "vitest";

import { formatNumber } from "@/lib/number";

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
