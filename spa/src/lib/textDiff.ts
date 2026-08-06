import { diffLines, diffWords, type Change } from "diff";

import { stripHtml } from "./html";
import { stableStringify } from "./stableStringify";

/**
 * Text diffing for the "pending changes" comparison panel.
 *
 * The panel's original rendering put the published and draft values in two
 * columns and left the reader to spot the difference themselves — fine for an
 * image or a one-word list value, useless for a paragraph of body copy or a
 * twelve-row matrix, which is where a queued change most needs reviewing.
 *
 * This module decides whether a field is text-like enough to diff, normalizes
 * both sides into comparable strings, and returns the hunks. Rendering lives in
 * <TextDiff />; the field types with a picture-worth-a-thousand-words renderer
 * (image, upload, link, …) opt out here and keep the two-column view.
 *
 * jsdiff does the actual work. It is the same engine the heavier code-review
 * diff libraries wrap, minus their syntax highlighting and theming — this panel
 * renders through the app's own design tokens and shows prose, not code.
 */

/** Word-level for prose, line-level for structured values. */
export type DiffMode = "lines" | "words";

/**
 * How an `html` field is compared. "text" diffs what the page will read like;
 * "markup" diffs the tags themselves. See {@link normalizeHtmlMarkup} for why
 * the default is "text".
 */
export type HtmlDiffView = "markup" | "text";

export interface FieldDiff {
	/** Added words (words mode) or lines (lines mode). */
	added: number;
	changes: Change[];
	/** True when both sides normalized to the same string. */
	identical: boolean;
	mode: DiffMode;
	/** Removed words (words mode) or lines (lines mode). */
	removed: number;
}

/**
 * Field types FieldComparison renders with a dedicated widget, where a text
 * diff would be strictly worse than what it already shows — a thumbnail beats
 * any diff of an image path, and a relation's stored row ids diff into noise.
 *
 * Keep in step with ComparisonValue's branches in FieldComparison.tsx.
 */
const OPAQUE_TYPES = new Set([
	"image",
	"upload",
	"link",
	"image-reference",
	"file-reference",
	"video-reference",
	"video",
	"one-to-many",
	"many-to-many",
	"checkbox",
	"geocoding",
]);

/** Prose: compared word by word, rendered as one flowing block. */
const WORD_DIFF_TYPES = new Set(["text", "textarea", "html"]);

/** Composite values stored as JSON: compared line by line once pretty-printed. */
const LINE_DIFF_TYPES = new Set(["callouts", "matrix", "media-gallery"]);

/** Unchanged lines kept either side of a hunk before the rest is collapsed. */
export const CONTEXT_LINES = 2;

const isHtmlType = (fieldType?: string): boolean => fieldType === "html";

/** Arrays and objects — the values worth pretty-printing before comparing. */
const isStructural = (value: unknown): boolean =>
	Array.isArray(value) || (value !== null && typeof value === "object");

/**
 * Which diff (if any) suits this field. Returns null for the types that keep
 * the side-by-side view.
 *
 * A field with no declared type falls back to the shape of its values: the page
 * editor's own property/SEO fields (nav_title, meta_description, …) are plain
 * columns rendered without a field spec, and they are exactly the prose a diff
 * helps with.
 */
export const diffModeForFieldType = (
	fieldType: string | undefined,
	published: unknown,
	pending: unknown
): DiffMode | null => {
	const type = fieldType ?? "";

	if (OPAQUE_TYPES.has(type)) {
		return null;
	}

	if (LINE_DIFF_TYPES.has(type)) {
		return "lines";
	}

	if (WORD_DIFF_TYPES.has(type)) {
		return "words";
	}

	if (typeof published === "string" && typeof pending === "string") {
		return "words";
	}

	if (isStructural(published) && isStructural(pending)) {
		return "lines";
	}

	return null;
};

/**
 * Render one side of the comparison as the string that gets diffed.
 *
 * @param mode The mode {@link diffModeForFieldType} picked, so both sides
 *             normalize identically even when one of them is empty.
 */
export const normalizeForDiff = (
	value: unknown,
	fieldType: string | undefined,
	mode: DiffMode,
	htmlView: HtmlDiffView = "text"
): string => {
	if (value === null || value === undefined) {
		return "";
	}

	if (isHtmlType(fieldType)) {
		const raw = typeof value === "string" ? value : String(value);

		return htmlView === "markup"
			? normalizeHtmlMarkup(raw)
			: humanizeLinkTokens(stripHtml(raw));
	}

	if (mode === "lines") {
		return prettyJson(value);
	}

	if (typeof value === "string") {
		return humanizeLinkTokens(value);
	}

	if (typeof value === "number" || typeof value === "boolean") {
		return String(value);
	}

	return prettyJson(value);
};

/**
 * Diff a field's published value against its draft. Null when the field type
 * isn't text-like, which is the caller's signal to keep the two-column view.
 */
export const buildFieldDiff = (
	published: unknown,
	pending: unknown,
	fieldType?: string,
	htmlView: HtmlDiffView = "text"
): FieldDiff | null => {
	const baseMode = diffModeForFieldType(fieldType, published, pending);

	if (baseMode === null) {
		return null;
	}

	// Markup is structure, not prose. normalizeHtmlMarkup already lays it out one
	// block element per line, so comparing it line by line reads the way a code
	// diff does — a word diff over tags splits `href="/one"` mid-attribute.
	const mode = isHtmlType(fieldType) && htmlView === "markup" ? "lines" : baseMode;
	const before = normalizeForDiff(published, fieldType, mode, htmlView);
	const after = normalizeForDiff(pending, fieldType, mode, htmlView);

	if (before === "" && after === "") {
		return null;
	}

	const changes = mode === "lines" ? diffLines(before, after) : diffWords(before, after);
	let added = 0;
	let removed = 0;

	for (const change of changes) {
		if (!change.added && !change.removed) {
			continue;
		}

		const units = mode === "lines" ? countLines(change.value) : countWords(change.value);

		if (change.added) {
			added += units;
		} else {
			removed += units;
		}
	}

	return { mode, changes, added, removed, identical: before === after };
};

/** One row of a line-mode diff, or a marker standing in for collapsed context. */
export type DiffRow =
	| { kind: "collapsed"; count: number }
	| { kind: "line"; text: string; type: "added" | "context" | "removed" };

/**
 * Flatten line-mode hunks into rows, collapsing runs of unchanged lines that
 * are more than {@link CONTEXT_LINES} away from a change. A media gallery or a
 * matrix is mostly unchanged rows; without this the one edited line is buried.
 */
export const toDiffRows = (changes: Change[], context = CONTEXT_LINES): DiffRow[] => {
	const lines: Array<{ text: string; type: "added" | "context" | "removed" }> = [];

	for (const change of changes) {
		const type = change.added ? "added" : change.removed ? "removed" : "context";

		for (const text of splitLines(change.value)) {
			lines.push({ text, type });
		}
	}

	const keep = new Set<number>();

	lines.forEach((line, index) => {
		if (line.type === "context") {
			return;
		}

		for (
			let i = Math.max(0, index - context);
			i <= Math.min(lines.length - 1, index + context);
			i++
		) {
			keep.add(i);
		}
	});

	const rows: DiffRow[] = [];
	let collapsed = 0;

	lines.forEach((line, index) => {
		if (keep.has(index)) {
			if (collapsed > 0) {
				rows.push({ kind: "collapsed", count: collapsed });
				collapsed = 0;
			}

			rows.push({ kind: "line", text: line.text, type: line.type });

			return;
		}

		collapsed++;
	});

	if (collapsed > 0) {
		rows.push({ kind: "collapsed", count: collapsed });
	}

	return rows;
};

/**
 * Normalize WYSIWYG markup so a diff shows editorial changes rather than the
 * editor's own bookkeeping.
 *
 * TinyMCE re-serializes on every save: attribute order shifts, whitespace
 * between tags reflows, and a field nobody touched can come back byte-different.
 * Diffing that raw lights the whole value up red and green, which is worse than
 * showing nothing. So both sides are parsed and re-serialized here with sorted
 * attributes, collapsed text whitespace and one block element per line — two
 * values that differ only in serialization normalize to the same string.
 *
 * Falls back to a regex pass where there is no DOM to parse with.
 */
export const normalizeHtmlMarkup = (html: string): string => {
	if (!html) {
		return "";
	}

	if (typeof DOMParser === "undefined") {
		return html
			.replace(/>\s+</g, ">\n<")
			.replace(/[ \t]+/g, " ")
			.trim();
	}

	try {
		const parsed = new DOMParser().parseFromString(html, "text/html");

		return serializeNode(parsed.body, 0).join("\n").trim();
	} catch {
		return html.trim();
	}
};

/** Block-level tags that get their own line in the normalized markup. */
const BLOCK_TAGS = new Set([
	"address",
	"article",
	"aside",
	"blockquote",
	"div",
	"dd",
	"dl",
	"dt",
	"fieldset",
	"figcaption",
	"figure",
	"footer",
	"form",
	"h1",
	"h2",
	"h3",
	"h4",
	"h5",
	"h6",
	"header",
	"hr",
	"li",
	"main",
	"nav",
	"ol",
	"p",
	"pre",
	"section",
	"table",
	"tbody",
	"td",
	"tfoot",
	"th",
	"thead",
	"tr",
	"ul",
	"video",
]);

/** Tags with no closing partner. */
const VOID_TAGS = new Set(["br", "hr", "img", "input", "source", "track", "wbr"]);

/**
 * Serialize a parsed node to indented lines. Block elements open and close on
 * their own line; inline runs stay on one line so a sentence isn't split into a
 * word per row.
 */
const serializeNode = (node: Node, depth: number): string[] => {
	const lines: string[] = [];
	const pad = "  ".repeat(depth);
	let inline = "";

	const flushInline = () => {
		const trimmed = inline.replace(/\s+/g, " ").trim();

		if (trimmed !== "") {
			lines.push(pad + trimmed);
		}

		inline = "";
	};

	node.childNodes.forEach((child) => {
		if (child.nodeType === 3) {
			inline += child.nodeValue ?? "";

			return;
		}

		if (child.nodeType !== 1) {
			return;
		}

		const element = child as Element;
		const tag = element.tagName.toLowerCase();

		if (!BLOCK_TAGS.has(tag)) {
			inline += serializeInline(element);

			return;
		}

		flushInline();
		const open = openTag(element);

		if (VOID_TAGS.has(tag)) {
			lines.push(pad + open);

			return;
		}

		lines.push(pad + open);
		lines.push(...serializeNode(element, depth + 1));
		lines.push(`${pad}</${tag}>`);
	});

	flushInline();

	return lines;
};

/** An inline element and its subtree, kept on one line. */
const serializeInline = (element: Element): string => {
	const tag = element.tagName.toLowerCase();
	const open = openTag(element);

	if (VOID_TAGS.has(tag)) {
		return open;
	}

	let inner = "";

	element.childNodes.forEach((child) => {
		if (child.nodeType === 3) {
			inner += child.nodeValue ?? "";

			return;
		}

		if (child.nodeType === 1) {
			inner += serializeInline(child as Element);
		}
	});

	return `${open}${inner}</${tag}>`;
};

/** `<tag attr="value">` with attributes sorted, so a reorder isn't a change. */
const openTag = (element: Element): string => {
	const tag = element.tagName.toLowerCase();
	const attributes = Array.from(element.attributes)
		.map((attribute) => `${attribute.name}="${humanizeLinkTokens(attribute.value)}"`)
		.sort();

	return attributes.length > 0 ? `<${tag} ${attributes.join(" ")}>` : `<${tag}>`;
};

/**
 * Label BigTree's internal link tokens for display.
 *
 * A link or HTML value reaches the SPA in its stored form, so a changed page
 * link diffs as `ipl://0/42` → `ipl://0/57` — technically a change, and
 * meaningless to the person deciding whether to publish it. The id is what the
 * reader can actually act on, so it is what gets shown. Display only: nothing
 * here is ever written back.
 */
export const humanizeLinkTokens = (value: string): string => {
	if (!value || value.indexOf("://") === -1) {
		return value;
	}

	return value
		.replace(/ipl:\/\/\d+\/(\d+)/g, "[page #$1]")
		.replace(/irl:\/\/\d+\/(\d+)/g, "[file #$1]")
		.replace(/ipl:\/\/[A-Za-z0-9+/=]+/g, "[internal page link]")
		.replace(/irl:\/\/[A-Za-z0-9+/=]+/g, "[internal file link]");
};

/**
 * Pretty-print a value for a line diff, with object keys sorted so a value the
 * server rebuilt in a different key order doesn't read as an edit. Accepts the
 * JSON strings BigTree stores some composite field types as.
 */
const prettyJson = (value: unknown): string => {
	let target = value;

	if (typeof value === "string") {
		try {
			target = JSON.parse(value);
		} catch {
			return value;
		}
	}

	try {
		// stableStringify sorts the keys; re-stringifying the parse of that keeps
		// the sorted order and adds the indentation.
		return JSON.stringify(JSON.parse(stableStringify(target)), null, 2);
	} catch {
		return String(value);
	}
};

/** Lines in a chunk, ignoring the trailing newline that ends the last one. */
const countLines = (value: string): number => splitLines(value).length;

const splitLines = (value: string): string[] => {
	const lines = value.split("\n");

	if (lines.length > 1 && lines[lines.length - 1] === "") {
		lines.pop();
	}

	return lines;
};

const countWords = (value: string): number =>
	value.split(/\s+/).filter((word) => word !== "").length;
