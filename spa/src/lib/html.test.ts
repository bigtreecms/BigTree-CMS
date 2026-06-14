import { describe, expect, it } from "vitest";

import { decodeHtmlEntities, stripHtml } from "@/lib/html";

describe("decodeHtmlEntities", () => {
	it("returns the input unchanged when there is no ampersand", () => {
		expect(decodeHtmlEntities("plain text")).toBe("plain text");
		expect(decodeHtmlEntities("")).toBe("");
	});

	it("decodes the named entities htmlspecialchars emits", () => {
		expect(decodeHtmlEntities("Date &amp; Time")).toBe("Date & Time");
		expect(decodeHtmlEntities("&lt;tag&gt;")).toBe("<tag>");
		expect(decodeHtmlEntities("&quot;q&quot;")).toBe('"q"');
		expect(decodeHtmlEntities("&#039;a&#39;")).toBe("'a'");
		expect(decodeHtmlEntities("&apos;b&apos;")).toBe("'b'");
		expect(decodeHtmlEntities("a&nbsp;b")).toBe("a b"); // &nbsp; → U+00A0
	});

	it("decodes decimal and hex numeric references", () => {
		expect(decodeHtmlEntities("&#38;")).toBe("&");
		expect(decodeHtmlEntities("&#x26;")).toBe("&");
	});

	it("decodes a double-encoded value one level (entities resolve before &amp;)", () => {
		// "&amp;lt;" is the encoding of the literal text "&lt;". Because the named
		// pass runs first and "&amp;" is resolved LAST, this collapses to "&lt;"
		// (the literal text the user typed) — NOT to "<". A single-level decode is
		// the intended behavior; preserving "&lt;" is correct.
		expect(decodeHtmlEntities("&amp;lt;")).toBe("&lt;");
	});
});

describe("stripHtml", () => {
	it("returns an empty string for empty input", () => {
		expect(stripHtml("")).toBe("");
	});

	it("strips tags to spaces and collapses whitespace", () => {
		expect(stripHtml("<p>Hello <b>world</b></p>")).toBe("Hello world");
	});

	it("decodes entities that survive tag stripping", () => {
		expect(stripHtml("<p>A &amp; B</p>")).toBe("A & B");
	});
});
