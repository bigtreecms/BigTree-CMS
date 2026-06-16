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
