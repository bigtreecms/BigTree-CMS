<?php
	/**
	 * Resources::clean() and Response::notModified() — the two zero-behavior-change
	 * extractions from API consolidation round 2 (findings #1 and #3).
	 *
	 * Resources::clean() is the shared callout/template resource normalizer that
	 * was byte-identical copy-paste in both services; Response::notModified()
	 * encapsulates the fiddly 304-with-ETag shape (is_envelope = false, body = null)
	 * the ETag'd list endpoints hand-assembled.
	 */

	use BigTree\Api\Resources;
	use BigTree\Api\Response;

	function test_resources_clean_shapes_and_encodes() {
		$out = Resources::clean([
			[
				"id" => "headline",
				"type" => "text",
				"title" => "Head<line>",
				"settings" => ["max_length" => 100, "blank" => ""],
			],
		]);

		T::equals(count($out), 1, "clean keeps a well-formed resource");
		T::equals($out[0]["id"], "headline", "clean carries the id through");
		T::equals($out[0]["title"], BigTree::safeEncode("Head<line>"), "clean safe-encodes scalar fields");
		T::ok(!isset($out[0]["settings"]["blank"]), "clean recursively filters empty settings values");
	}

	function test_resources_clean_drops_idless_and_defaults_type() {
		$out = Resources::clean([
			["title" => "no id here"],
			["id" => "body"],
		]);

		T::equals(count($out), 1, "clean drops entries with no id");
		T::equals($out[0]["id"], "body", "clean keeps the entry that has an id");
		T::equals($out[0]["type"], "text", "clean defaults a missing type to 'text'");
	}

	function test_resources_clean_falls_back_to_options_and_decodes_json() {
		// Legacy callers stored settings under "options"; a string payload is JSON.
		$out = Resources::clean([
			["id" => "a", "options" => ["legacy" => "kept"]],
			["id" => "b", "settings" => '{"decoded":"yes"}'],
		]);

		T::equals($out[0]["settings"]["legacy"], "kept", "clean reads settings from the legacy 'options' key");
		T::equals($out[1]["settings"]["decoded"], "yes", "clean json-decodes a string settings payload");
	}

	function test_resources_clean_tolerates_non_array_input() {
		T::equals(Resources::clean("garbage"), [], "clean returns [] for non-array input");
		T::equals(Resources::clean(null), [], "clean returns [] for null input");
	}

	function test_response_not_modified_shape() {
		$r = Response::notModified('"abc123"');

		T::equals($r->status, 304, "notModified sets a 304 status");
		T::ok($r->is_envelope === false, "notModified disables the JSON envelope");
		T::ok($r->body === null, "notModified leaves an empty body");
		T::equals($r->headers["ETag"], '"abc123"', "notModified echoes the ETag header");
	}
