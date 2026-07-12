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

	function test_sanitize_column_name() {
		T::equals(Sanitize::columnName("First Name"), "first_name", "title with space → underscores");
		T::equals(Sanitize::columnName("first-name"), "first_name", "dashes become underscores");
		T::equals(Sanitize::columnName("Price ($)"), "price", "non-alnum stripped");
		T::equals(Sanitize::columnName("`drop`"), "drop", "backticks stripped");
		T::equals(Sanitize::columnName(""), "", "empty input → empty string");
		T::equals(Sanitize::columnName("!!!"), "", "all-punctuation → empty string");
	}

	function test_sanitize_like_term() {
		T::equals(Sanitize::likeTerm("foo"), "%foo%", "plain term wrapped in wildcards");
		T::equals(Sanitize::likeTerm("50%"), "%50\\%%", "percent escaped as a literal");
		T::equals(Sanitize::likeTerm("a_b"), "%a\\_b%", "underscore escaped as a literal");
		T::equals(Sanitize::likeTerm("a\\b"), "%a\\\\b%", "backslash doubled before other escaping");
		T::equals(Sanitize::likeTerm("Foo"), "%Foo%", "case preserved by default");
		T::equals(Sanitize::likeTerm("Foo", true), "%foo%", "lowercase option applied");
	}

	function test_sanitize_is_valid_id_accepts() {
		T::equals(Sanitize::isValidId("contact-form"), true, "alnum with dash passes");
		T::equals(Sanitize::isValidId("my_field_2"), true, "alnum with underscore/digits passes");
		T::equals(Sanitize::isValidId("Abc123"), true, "mixed-case alnum passes");
		T::equals(Sanitize::isValidId(str_repeat("a", 127)), true, "exactly 127 chars passes");
		T::equals(Sanitize::isValidId("com.fastspot.feed", 127, "-_."), true, "reverse-DNS passes when dot allowed");
	}

	function test_sanitize_is_valid_id_rejects() {
		T::equals(Sanitize::isValidId(""), false, "empty id rejected");
		T::equals(Sanitize::isValidId("has space"), false, "space rejected");
		T::equals(Sanitize::isValidId("slash/id"), false, "slash rejected");
		T::equals(Sanitize::isValidId("--"), false, "all-extra-chars id rejected");
		T::equals(Sanitize::isValidId(str_repeat("a", 128)), false, "128 chars exceeds default cap");
		T::equals(Sanitize::isValidId("com.fastspot"), false, "dot rejected by default charset");
	}

	function test_sanitize_order_clause_allowed() {
		$columns = ["id" => true, "title" => true, "position" => true];

		T::equals(Sanitize::orderClause("title", $columns, "id"), "`title` ASC", "bare column defaults to ASC");
		T::equals(Sanitize::orderClause("title DESC", $columns, "id"), "`title` DESC", "explicit DESC honored");
		T::equals(Sanitize::orderClause("position asc", $columns, "id"), "`position` ASC", "lowercase asc normalized");
		T::equals(Sanitize::orderClause("`title`", $columns, "id"), "`title` ASC", "backticked column accepted");
	}

	function test_sanitize_order_clause_fallback() {
		$columns = ["id" => true, "title" => true];

		T::equals(Sanitize::orderClause("", $columns, "id"), "`id` ASC", "empty raw → fallback ASC");
		T::equals(Sanitize::orderClause("nope", $columns, "id"), "`id` ASC", "unknown column → fallback ASC");
	}

	function test_sanitize_order_clause_rejects_injection() {
		$columns = ["id" => true, "title" => true];

		T::equals(Sanitize::orderClause("id; DROP TABLE x", $columns, "title"), "`title` ASC", "statement injection rejected");
		T::equals(Sanitize::orderClause("id) OR 1=1", $columns, "title"), "`title` ASC", "boolean injection rejected");
		T::equals(Sanitize::orderClause("id`; DROP", $columns, "title"), "`title` ASC", "backtick break-out rejected");
		T::equals(Sanitize::orderClause("(SELECT 1)", $columns, "title"), "`title` ASC", "subquery rejected");
	}

	function test_sanitize_decode_entities() {
		T::equals(Sanitize::decodeEntities("Tom &amp; Jerry"), "Tom & Jerry", "named entity decoded");
		T::equals(Sanitize::decodeEntities("&quot;quoted&quot;"), '"quoted"', "double quotes decoded (ENT_QUOTES)");
		T::equals(Sanitize::decodeEntities("it&#039;s"), "it's", "single quote decoded (ENT_QUOTES)");
		T::equals(Sanitize::decodeEntities("&apos;"), "'", "HTML5 apostrophe entity decoded");
		T::equals(Sanitize::decodeEntities("plain text"), "plain text", "text without entities unchanged");
		T::equals(Sanitize::decodeEntities(null), "", "null cast to empty string");
	}

	function test_sanitize_decode_entities_inverts_safe_encode() {
		$raw = 'A "risky" <title> & more';
		T::equals(Sanitize::decodeEntities(\BigTree::safeEncode($raw)), $raw, "decodeEntities inverts safeEncode");
	}

	function test_sanitize_placeholders() {
		T::equals(Sanitize::placeholders(["a"]), "?", "single value → one placeholder");
		T::equals(Sanitize::placeholders([1, 2, 3]), "?,?,?", "three values → three placeholders");
		T::equals(Sanitize::placeholders([]), "", "empty array → empty string");
	}
