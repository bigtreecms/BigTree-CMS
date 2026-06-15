import { describe, expect, it } from "vitest";

import { csvCell } from "@/lib/csv";

describe("csvCell", () => {
	it("quotes a normal value without a prefix", () => {
		expect(csvCell("hello")).toBe('"hello"');
	});

	it("prefixes leading-formula values with an apostrophe", () => {
		expect(csvCell("=1+1")).toBe('"\'=1+1"');
		expect(csvCell("+1")).toBe('"\'+1"');
		expect(csvCell("-1")).toBe('"\'-1"');
		expect(csvCell("@x")).toBe('"\'@x"');
		expect(csvCell("\t x")).toBe('"\'\t x"');
		expect(csvCell("\r x")).toBe('"\'\r x"');
	});

	it("doubles embedded quotes per RFC 4180", () => {
		expect(csvCell('a"b')).toBe('"a""b"');
	});

	it("both prefixes and doubles quotes for a leading-formula value with a quote", () => {
		expect(csvCell('=a"b')).toBe('"\'=a""b"');
	});

	it("does not prefix values where the trigger char is not first", () => {
		expect(csvCell("a=1")).toBe('"a=1"');
	});
});
