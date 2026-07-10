import DOMPurify from "dompurify";

/**
 * Decode the HTML entities that PHP's htmlspecialchars()/htmlentities() write
 * into stored strings (field-type names, titles, …). The legacy admin renders
 * those values straight into HTML, so the encoding is invisible there; the SPA
 * puts them in text nodes, where the raw entities would otherwise show through
 * (e.g. "Date &amp; Time Picker").
 *
 * Covers the named entities htmlspecialchars emits (&amp; &lt; &gt; &quot; and
 * both &#039;/&#39; apostrophe forms) plus any decimal/hex numeric reference.
 * `&amp;` is resolved last so a double-encoded "&amp;lt;" still collapses
 * correctly.
 */

const NAMED_ENTITIES: Record<string, string> = {
	"&lt;": "<",
	"&gt;": ">",
	"&quot;": '"',
	"&#039;": "'",
	"&#39;": "'",
	"&apos;": "'",
	"&nbsp;": " ",
};

export const decodeHtmlEntities = (value: string): string => {
	if (!value || value.indexOf("&") === -1) {
		return value;
	}

	let out = value;

	for (const [entity, char] of Object.entries(NAMED_ENTITIES)) {
		out = out.split(entity).join(char);
	}

	out = out.replace(/&#(\d+);/g, (_, dec: string) => String.fromCodePoint(Number(dec)));
	out = out.replace(/&#[xX]([0-9a-fA-F]+);/g, (_, hex: string) =>
		String.fromCodePoint(parseInt(hex, 16))
	);

	return out.split("&amp;").join("&");
};

/**
 * DOM-based entity decoder: round-trips the value through a detached
 * `<textarea>`, which the browser decodes against the *full* HTML named-entity
 * table (broader than {@link decodeHtmlEntities}' hand-rolled set — it also
 * covers `&copy;`, `&mdash;`, accented entities, …). The module-level element is
 * created once and reused across calls.
 *
 * BigTree's view cache can double-encode (`&` written back as `&amp;amp;` after a
 * second htmlspecialchars pass), so `passes` repeats the round-trip until the
 * string stops changing, capped by `passes` to avoid pathological input. The cap
 * is well above the 2 passes the cache actually produces. Returns the input
 * unchanged when there is no DOM (SSR/tests) or no `&` to decode.
 */
let decodeEl: HTMLTextAreaElement | null = null;

export const decodeHtmlEntitiesDom = (value: string, passes = 1): string => {
	if (!value || value.indexOf("&") === -1 || typeof document === "undefined") {
		return value;
	}

	if (!decodeEl) {
		decodeEl = document.createElement("textarea");
	}

	let current = value;

	for (let i = 0; i < passes; i++) {
		decodeEl.innerHTML = current;
		const next = decodeEl.value;

		if (next === current) {
			break;
		}

		current = next;
	}

	return current;
};

/**
 * Reduce a WYSIWYG HTML value to a single line of plain text — for places that
 * can only show text (table cells, `title` tooltips, search summaries) and
 * would otherwise leak raw markup like "<p>…</p>". Tags become spaces, runs of
 * whitespace collapse, and any surviving entities are decoded.
 */
export const stripHtml = (value: string): string => {
	if (!value) {
		return "";
	}

	const text = value
		.replace(/<[^>]*>/g, " ")
		.replace(/\s+/g, " ")
		.trim();

	return decodeHtmlEntities(text);
};

/**
 * The single client-side sanitization point for `dangerouslySetInnerHTML`.
 *
 * Passes the value through DOMPurify with the default HTML profile, which
 * strips `<script>` elements, event-handler attributes (e.g. `onerror`),
 * and `javascript:`/`data:` script URLs while preserving ordinary formatting
 * markup (`<p>`, `<strong>`, `<a href>`, lists, etc.).
 *
 * Use this to wrap every `__html` value before passing it to React — it is
 * defense-in-depth against stored XSS in the admin's authenticated session
 * should any server-side filter ever be bypassed or misconfigured.
 */
export const sanitizeHtml = (value: string): string => {
	if (!value) {
		return "";
	}

	return DOMPurify.sanitize(value, { USE_PROFILES: { html: true } }) as string;
};
