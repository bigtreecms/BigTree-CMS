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
