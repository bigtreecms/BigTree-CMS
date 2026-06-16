<?php
	/**
	 * SVG store-time sanitizer in ResourceService::sanitizeSvg.
	 *
	 * SVGs are served as static files from the site origin, so a stored
	 * <script>/on*-handler/<foreignObject>/javascript: URI is a stored-XSS vector
	 * when another admin opens the file URL. The sanitizer strips that active
	 * content while leaving benign markup structurally intact.
	 *
	 * Pure string transform — no DB needed.
	 */

	use BigTree\Services\ResourceService;

	function _svgsan(string $svg): string {
		$m = new ReflectionMethod(ResourceService::class, "sanitizeSvg");
		$m->setAccessible(true);

		return $m->invoke(null, $svg);
	}

	function test_svg_sanitize_strips_script() {
		$out = _svgsan('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect/></svg>');
		T::ok(stripos($out, "<script") === false, "drops <script> element");
		T::ok(stripos($out, "alert(1)") === false, "drops script body");
		T::ok(strpos($out, "<rect") !== false, "keeps benign <rect>");

		// Self-closing / void script tag too.
		$out2 = _svgsan('<svg><script src="https://evil/x.js"/></svg>');
		T::ok(stripos($out2, "<script") === false, "drops self-closing <script>");
	}

	function test_svg_sanitize_strips_event_handlers() {
		$out = _svgsan('<svg><rect onload="x()" width="10"/></svg>');
		T::ok(stripos($out, "onload") === false, "drops onload= handler");
		T::ok(strpos($out, 'width="10"') !== false, "keeps benign attribute");

		$out2 = _svgsan("<svg><circle onclick='y()' r='5'/></svg>");
		T::ok(stripos($out2, "onclick") === false, "drops single-quoted handler");

		$out3 = _svgsan('<svg><rect onmouseover=bad()/></svg>');
		T::ok(stripos($out3, "onmouseover") === false, "drops unquoted handler");
	}

	function test_svg_sanitize_strips_foreign_object() {
		$out = _svgsan('<svg><foreignObject><body onload="z()"></body></foreignObject><path/></svg>');
		T::ok(stripos($out, "foreignObject") === false, "drops <foreignObject>");
		T::ok(strpos($out, "<path") !== false, "keeps benign <path> after foreignObject");
	}

	function test_svg_sanitize_strips_javascript_uri() {
		$out = _svgsan('<svg><a xlink:href="javascript:alert(1)"><text>x</text></a></svg>');
		T::ok(stripos($out, "javascript:") === false, "drops javascript: in xlink:href");

		$out2 = _svgsan('<svg><a href="data:text/html,<script>1</script>">x</a></svg>');
		T::ok(stripos($out2, "data:text/html") === false, "drops data:text/html href");
	}

	function test_svg_sanitize_passes_benign() {
		$benign = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0 L10 10"/></svg>';
		$out = _svgsan($benign);
		T::ok(strpos($out, "<path") !== false, "benign SVG keeps its <path>");
		T::ok(strpos($out, 'd="M0 0 L10 10"') !== false, "benign path data preserved");
		T::ok(strpos($out, "<svg") !== false, "root <svg> preserved");
	}

	// — DOM/allowlist sanitizer bypass vectors (plan 012) —

	function test_svg_sanitize_event_handler_whitespace_tricks() {
		// Newline/whitespace between the handler name and "=" — the old regex relied
		// on \son[a-z]+\s*= so a clean case still mattered. Confirm any on* is gone.
		$out = _svgsan("<svg xmlns=\"http://www.w3.org/2000/svg\"><rect onload\n=\"alert(1)\" width=\"10\"/></svg>");
		T::ok(stripos($out, "onload") === false, "drops onload handler with embedded newline");
		T::ok(stripos($out, "alert(1)") === false, "drops handler body");

		// Mixed-case handler name.
		$out2 = _svgsan('<svg xmlns="http://www.w3.org/2000/svg"><rect OnMouseOver="bad()"/></svg>');
		T::ok(stripos($out2, "onmouseover") === false, "drops mixed-case OnMouseOver handler");
	}

	function test_svg_sanitize_case_insensitive_javascript_uri() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href="JaVaScRiPt:alert(1)">x</a></svg>';
		$out = _svgsan($svg);
		T::ok(stripos($out, "javascript") === false, "neutralizes mixed-case JaVaScRiPt: in xlink:href");
		T::ok(strpos($out, 'xlink:href="#"') !== false, "rewrites unsafe xlink:href to inert #");

		// Plain href variant.
		$out2 = _svgsan('<svg xmlns="http://www.w3.org/2000/svg"><a href="JAVASCRIPT:alert(1)">x</a></svg>');
		T::ok(stripos($out2, "javascript") === false, "neutralizes JAVASCRIPT: in href");
	}

	function test_svg_sanitize_whitespace_in_scheme() {
		// Control chars / newline inside the scheme must not slip past the check.
		$svg = "<svg xmlns=\"http://www.w3.org/2000/svg\"><a href=\"java\nscript:alert(1)\">x</a></svg>";
		$out = _svgsan($svg);
		T::ok(stripos($out, "alert(1)") === false, "neutralizes whitespace-obfuscated javascript scheme");
		T::ok(strpos($out, 'href="#"') !== false, "rewrites whitespace-scheme href to inert #");
	}

	function test_svg_sanitize_use_data_uri() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="data:image/svg+xml,foo"/></svg>';
		$out = _svgsan($svg);
		T::ok(stripos($out, "data:") === false, "neutralizes data: in <use> xlink:href");
		T::ok(strpos($out, 'xlink:href="#"') !== false, "rewrites <use> data: ref to inert #");
	}

	function test_svg_sanitize_image_data_html() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><image href="data:text/html,bar"/></svg>';
		$out = _svgsan($svg);
		T::ok(stripos($out, "data:text/html") === false, "neutralizes data:text/html in <image href>");
		T::ok(strpos($out, 'href="#"') !== false, "rewrites <image> data: ref to inert #");
	}

	function test_svg_sanitize_drops_animate_and_set() {
		// <animate attributeName="href" to="javascript:...">: element is not on the
		// allowlist, so it must be dropped entirely.
		$out = _svgsan('<svg xmlns="http://www.w3.org/2000/svg"><animate attributeName="href" to="javascript:alert(1)"/></svg>');
		T::ok(stripos($out, "<animate") === false, "drops <animate> element");
		T::ok(stripos($out, "javascript") === false, "drops animate javascript target");

		// <set> equivalent (also not allowlisted).
		$out2 = _svgsan('<svg xmlns="http://www.w3.org/2000/svg"><a><set attributeName="href" to="javascript:alert(1)"/></a></svg>');
		T::ok(stripos($out2, "<set") === false, "drops <set> element");
		T::ok(stripos($out2, "javascript") === false, "drops set javascript target");
		T::ok(strpos($out2, "<a") !== false, "keeps allowlisted <a> wrapper");
	}

	function test_svg_sanitize_drops_style() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><style>* { background: url("javascript:alert(1)") }</style><rect width="10"/></svg>';
		$out = _svgsan($svg);
		T::ok(stripos($out, "<style") === false, "drops <style> element");
		T::ok(stripos($out, "javascript") === false, "drops CSS javascript: url");
		T::ok(strpos($out, "<rect") !== false, "keeps benign <rect> sibling");
	}

	function test_svg_sanitize_entity_encoded_handler_not_reconstituted() {
		// A bare "&" makes this not well-formed XML; the sanitizer must fail closed
		// (neutral empty SVG) rather than ever reconstitute an active onload handler.
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect on&#108;oad="x()"/></svg>';
		$out = _svgsan($svg);
		T::ok(stripos($out, "onload") === false, "entity-encoded handler is not reconstituted");
		T::ok(stripos($out, "x()") === false, "handler body absent");
		T::ok(stripos($out, "<svg") !== false, "fails closed to a neutral <svg>");
	}

	function test_svg_sanitize_malformed_fails_closed() {
		// Not well-formed XML → neutral SVG, never the original.
		$out = _svgsan('<svg><rect></svg');
		T::ok(stripos($out, "<svg") !== false, "malformed input yields a neutral <svg>");
		T::ok(stripos($out, "<rect") === false, "malformed input does not pass markup through");

		// Non-SVG root is rejected.
		$out2 = _svgsan('<html><body><script>alert(1)</script></body></html>');
		T::ok(stripos($out2, "<script") === false, "non-svg root with script fails closed");
		T::ok(stripos($out2, "<body") === false, "non-svg root markup dropped");
	}

	function test_svg_sanitize_keeps_fragment_and_relative_refs() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="g"><stop offset="0" stop-color="red"/></linearGradient></defs><rect fill="url(#g)"/><a href="page.html"><circle r="5"/></a></svg>';
		$out = _svgsan($svg);
		T::ok(strpos($out, 'id="g"') !== false, "keeps gradient definition");
		T::ok(strpos($out, 'fill="url(#g)"') !== false, "keeps fragment fill reference");
		T::ok(strpos($out, 'href="page.html"') !== false, "keeps safe relative href");
		T::ok(strpos($out, "<circle") !== false, "keeps benign <circle>");
		T::ok(strpos($out, "<stop") !== false, "keeps <stop> child");
	}
