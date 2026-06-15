<?php
	use BigTree\Api\Sanitize;

	function test_sanitize_path_segment_accepts_valid() {
		T::equals(Sanitize::pathSegment("com.fastspot.date-range"), true, "reverse-DNS extension id passes");
		T::equals(Sanitize::pathSegment("abc"), true, "plain alnum passes");
		T::equals(Sanitize::pathSegment("a_b-c.d"), true, "underscore/dash/dot mix passes");
	}

	function test_sanitize_path_segment_rejects_invalid() {
		T::equals(Sanitize::pathSegment(""), false, "empty string rejected");
		T::equals(Sanitize::pathSegment("../etc"), false, "traversal prefix rejected");
		T::equals(Sanitize::pathSegment("a/b"), false, "slash rejected");
		T::equals(Sanitize::pathSegment("a b"), false, "space rejected");
		T::equals(Sanitize::pathSegment(".hidden"), false, "leading dot rejected");
		T::equals(Sanitize::pathSegment("a..b"), false, "embedded traversal rejected");
	}
