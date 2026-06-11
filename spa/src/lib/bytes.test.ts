import { describe, expect, it } from "vitest";

import { formatBytes } from "@/lib/bytes";

describe("formatBytes", () => {
	it("returns an em dash for falsy sizes", () => {
		expect(formatBytes(0)).toBe("—");
		expect(formatBytes(null)).toBe("—");
		expect(formatBytes(undefined)).toBe("—");
	});

	it("formats bytes under 1 KB without a decimal", () => {
		expect(formatBytes(512)).toBe("512 B");
	});

	it("formats KB / MB / GB with one decimal", () => {
		expect(formatBytes(1536)).toBe("1.5 KB");
		expect(formatBytes(5 * 1024 * 1024)).toBe("5.0 MB");
		expect(formatBytes(3 * 1024 * 1024 * 1024)).toBe("3.0 GB");
	});
});
