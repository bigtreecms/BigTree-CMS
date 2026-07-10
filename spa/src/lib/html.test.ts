import { describe, expect, it } from "vitest";

import { decodeHtmlEntities, decodeHtmlEntitiesDom, sanitizeHtml, stripHtml } from "@/lib/html";

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

describe("decodeHtmlEntitiesDom", () => {
	it("returns the input unchanged when there is no ampersand", () => {
		expect(decodeHtmlEntitiesDom("plain text")).toBe("plain text");
		expect(decodeHtmlEntitiesDom("")).toBe("");
	});

	it("decodes a single pass by default", () => {
		expect(decodeHtmlEntitiesDom("Tom &amp; Jerry")).toBe("Tom & Jerry");
	});

	it("decodes the broader named-entity table the textarea covers", () => {
		expect(decodeHtmlEntitiesDom("&copy; 2026")).toBe("© 2026");
	});

	it("unwinds a double-encoded value across multiple passes", () => {
		// The view cache writes "&" back as "&amp;amp;" (two htmlspecialchars
		// passes). One pass yields "&amp;"; a second collapses it to "&".
		expect(decodeHtmlEntitiesDom("Tom &amp;amp; Jerry", 1)).toBe("Tom &amp; Jerry");
		expect(decodeHtmlEntitiesDom("Tom &amp;amp; Jerry", 5)).toBe("Tom & Jerry");
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

describe("sanitizeHtml", () => {
	it("returns an empty string for falsy input", () => {
		expect(sanitizeHtml("")).toBe("");
	});

	it("preserves ordinary formatting markup", () => {
		const result = sanitizeHtml("<p>Hello <strong>world</strong></p>");

		expect(result).toContain("Hello");
		expect(result).toContain("<strong>world</strong>");
	});

	it("strips script elements", () => {
		const result = sanitizeHtml("<p>ok</p><script>alert(1)</script>");

		expect(result).not.toContain("<script");
		expect(result).not.toContain("alert(1)");
	});

	it("strips inline event handler attributes", () => {
		const result = sanitizeHtml('<img src=x onerror="alert(1)">');

		expect(result).not.toContain("onerror");
	});

	it("neutralizes javascript: links", () => {
		const result = sanitizeHtml('<a href="javascript:alert(1)">x</a>');

		expect(result).not.toContain("javascript:");
	});
});
