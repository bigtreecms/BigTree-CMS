import { describe, expect, it } from "vitest";

import {
	buildFieldDiff,
	diffModeForFieldType,
	humanizeLinkTokens,
	normalizeHtmlMarkup,
	toDiffRows,
} from "@/lib/textDiff";

/** The concatenated text of every non-removed hunk — what the reader ends up seeing. */
const rendered = (changes: Array<{ removed?: boolean; value: string }>): string =>
	changes
		.filter((change) => !change.removed)
		.map((change) => change.value)
		.join("");

describe("diffModeForFieldType", () => {
	it("word-diffs prose types", () => {
		expect(diffModeForFieldType("text", "a", "b")).toBe("words");
		expect(diffModeForFieldType("textarea", "a", "b")).toBe("words");
		expect(diffModeForFieldType("html", "a", "b")).toBe("words");
	});

	it("line-diffs the composite JSON types", () => {
		expect(diffModeForFieldType("matrix", [], [])).toBe("lines");
		expect(diffModeForFieldType("media-gallery", [], [])).toBe("lines");
		expect(diffModeForFieldType("callouts", [], [])).toBe("lines");
	});

	it("declines the types with a renderer of their own", () => {
		expect(diffModeForFieldType("image", "a.jpg", "b.jpg")).toBeNull();
		expect(diffModeForFieldType("link", "/a", "/b")).toBeNull();
		expect(diffModeForFieldType("upload", "/a.pdf", "/b.pdf")).toBeNull();
		expect(diffModeForFieldType("many-to-many", [1], [2])).toBeNull();
		expect(diffModeForFieldType("checkbox", true, false)).toBeNull();
	});

	it("falls back to the value shape for untyped columns", () => {
		// The page editor's own property/SEO fields arrive without a field spec.
		expect(diffModeForFieldType(undefined, "old title", "new title")).toBe("words");
		expect(diffModeForFieldType(undefined, { a: 1 }, { a: 2 })).toBe("lines");
		expect(diffModeForFieldType(undefined, 1, 2)).toBeNull();
	});
});

describe("buildFieldDiff", () => {
	it("marks the changed words and leaves the rest as context", () => {
		const diff = buildFieldDiff("The quick brown fox", "The quick red fox", "text");

		expect(diff).not.toBeNull();
		expect(diff?.mode).toBe("words");
		expect(diff?.changes.some((change) => change.added && change.value.includes("red"))).toBe(
			true
		);
		expect(
			diff?.changes.some((change) => change.removed && change.value.includes("brown"))
		).toBe(true);
	});

	it("reproduces the draft text across the non-removed hunks", () => {
		const diff = buildFieldDiff("one two three", "one four three", "textarea");

		expect(rendered(diff?.changes ?? [])).toBe("one four three");
	});

	it("counts added and removed words", () => {
		const diff = buildFieldDiff("a b c", "a b c d e", "text");

		expect(diff?.added).toBe(2);
		expect(diff?.removed).toBe(0);
	});

	it("reports identical when only the markup moved, in text view", () => {
		const diff = buildFieldDiff(
			"<p>Hello <b>world</b></p>",
			"<p>Hello <i>world</i></p>",
			"html"
		);

		expect(diff?.identical).toBe(true);
	});

	it("sees that same change in markup view", () => {
		const diff = buildFieldDiff(
			"<p>Hello <b>world</b></p>",
			"<p>Hello <i>world</i></p>",
			"html",
			"markup"
		);

		expect(diff?.identical).toBe(false);
	});

	it("ignores key order in structured values", () => {
		const diff = buildFieldDiff(
			[{ caption: "A", image: "/1.jpg" }],
			[{ image: "/1.jpg", caption: "A" }],
			"media-gallery"
		);

		expect(diff?.identical).toBe(true);
	});

	it("accepts composite values stored as JSON strings", () => {
		const diff = buildFieldDiff('[{"caption":"Old"}]', '[{"caption":"New"}]', "media-gallery");

		expect(diff?.mode).toBe("lines");
		expect(diff?.identical).toBe(false);
		expect(diff?.added).toBe(1);
		expect(diff?.removed).toBe(1);
	});

	it("returns null for a type that keeps its own renderer", () => {
		expect(buildFieldDiff("/a.jpg", "/b.jpg", "image")).toBeNull();
	});

	it("returns null when both sides are empty", () => {
		expect(buildFieldDiff("", null, "text")).toBeNull();
	});
});

describe("normalizeHtmlMarkup", () => {
	it("collapses the whitespace an editor reflows", () => {
		expect(normalizeHtmlMarkup("<p>Hello</p>\n\n  <p>World</p>")).toBe(
			normalizeHtmlMarkup("<p>Hello</p><p>World</p>")
		);
	});

	it("sorts attributes so a reorder isn't a change", () => {
		expect(normalizeHtmlMarkup('<p class="a" id="b">x</p>')).toBe(
			normalizeHtmlMarkup('<p id="b" class="a">x</p>')
		);
	});

	it("keeps a real attribute change visible", () => {
		expect(normalizeHtmlMarkup('<a href="/one">x</a>')).not.toBe(
			normalizeHtmlMarkup('<a href="/two">x</a>')
		);
	});

	it("keeps an inline run on one line", () => {
		expect(normalizeHtmlMarkup("<p>Hello <b>there</b> world</p>")).toContain(
			"Hello <b>there</b> world"
		);
	});
});

describe("humanizeLinkTokens", () => {
	it("labels internal page and file links by id", () => {
		expect(humanizeLinkTokens("ipl://0/42")).toBe("[page #42]");
		expect(humanizeLinkTokens("irl://0/7")).toBe("[file #7]");
	});

	it("labels the legacy base64 token form", () => {
		expect(humanizeLinkTokens("ipl://cGFnZXM6NDI=")).toBe("[internal page link]");
	});

	it("leaves ordinary URLs alone", () => {
		expect(humanizeLinkTokens("https://example.com/a")).toBe("https://example.com/a");
	});
});

describe("toDiffRows", () => {
	it("collapses unchanged stretches away from a change", () => {
		const before = Array.from({ length: 20 }, (_, i) => `line ${i}`).join("\n");
		const after = before.replace("line 10", "line ten");
		const diff = buildFieldDiff(before, after, "matrix");
		const rows = toDiffRows(diff?.changes ?? []);

		expect(rows.some((row) => row.kind === "collapsed")).toBe(true);
		expect(rows.filter((row) => row.kind === "line" && row.type === "added")).toHaveLength(1);
		expect(rows.filter((row) => row.kind === "line" && row.type === "removed")).toHaveLength(1);
	});

	it("keeps everything when the value is short", () => {
		const diff = buildFieldDiff("a\nb", "a\nc", "matrix");
		const rows = toDiffRows(diff?.changes ?? []);

		expect(rows.every((row) => row.kind === "line")).toBe(true);
	});
});
