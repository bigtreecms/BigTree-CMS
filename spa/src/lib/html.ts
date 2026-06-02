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
