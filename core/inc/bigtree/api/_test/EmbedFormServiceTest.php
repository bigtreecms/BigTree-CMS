<?php
	/**
	 * Tests for BigTree\Services\EmbedFormService's public-submission guards: the
	 * bot-check challenge, the upload / crop tokens that bind a stored file to one
	 * form and column, and the post-data cleaning that drops values a visitor
	 * shouldn't be able to set. Pure: uses Jwt::currentSecret(), no DB.
	 */

	use BigTree\Api\Jwt;
	use BigTree\Api\Pagination;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Services\EmbedFormService;

	function embed_form_catch_code(callable $fn): string {
		try {
			$fn();
		} catch (BadRequestException $e) {
			return $e->code_string;
		}

		return "";
	}

	function embed_form_backdated_challenge(string $hash, int $seconds_ago): string {

		return Pagination::encodeCursor([
			"k" => "embed-challenge",
			"h" => $hash,
			"iat" => time() - $seconds_ago,
			"exp" => time() + 600,
		], Jwt::currentSecret());
	}

	function test_embed_form_challenge_accepted_after_fill_time() {
		$token = embed_form_backdated_challenge("abc", EmbedFormService::MIN_FILL_SECONDS + 1);
		$claims = EmbedFormService::verifyChallenge($token, "abc", true);

		T::equals($claims["h"], "abc", "a challenge older than the fill time verifies");
	}

	function test_embed_form_challenge_rejects_instant_submit() {
		$token = EmbedFormService::mintChallenge("abc");

		T::equals(embed_form_catch_code(function () use ($token) {
			EmbedFormService::verifyChallenge($token, "abc", true);
		}), "spam_check_failed", "a submit straight after load is rejected");

		T::equals(embed_form_catch_code(function () use ($token) {
			EmbedFormService::verifyChallenge($token, "abc", false);
		}), "", "uploads don't wait for the fill time");
	}

	function test_embed_form_challenge_bound_to_form() {
		$token = embed_form_backdated_challenge("abc", 60);

		T::equals(embed_form_catch_code(function () use ($token) {
			EmbedFormService::verifyChallenge($token, "other", true);
		}), "spam_check_failed", "another form's challenge is rejected");

		T::equals(embed_form_catch_code(function () {
			EmbedFormService::verifyChallenge("", "abc", true);
		}), "spam_check_failed", "a missing challenge is rejected");
	}

	function test_embed_form_upload_token_binding() {
		$token = EmbedFormService::mintUploadToken("abc", "photo", "{wwwroot}files/a.jpg");

		T::ok(EmbedFormService::uploadTokenMatches($token, "abc", "photo", "{wwwroot}files/a.jpg"), "matching form, column and file");
		T::ok(!EmbedFormService::uploadTokenMatches($token, "abc", "photo", "{wwwroot}files/b.jpg"), "another file is rejected");
		T::ok(!EmbedFormService::uploadTokenMatches($token, "abc", "resume", "{wwwroot}files/a.jpg"), "another column is rejected");
		T::ok(!EmbedFormService::uploadTokenMatches($token, "xyz", "photo", "{wwwroot}files/a.jpg"), "another form is rejected");

		$challenge = EmbedFormService::mintChallenge("abc");
		T::ok(!EmbedFormService::uploadTokenMatches($challenge, "abc", "photo", "{wwwroot}files/a.jpg"), "a challenge isn't an upload token");
	}

	function test_embed_form_clean_post_data() {
		$fields = [
			["column" => "name", "type" => "text"],
			["column" => "photo", "type" => "image"],
			["column" => "resume", "type" => "upload"],
			["column" => "logo", "type" => "image-reference"],
			["column" => "clip", "type" => "video"],
			["column" => "blocks", "type" => "matrix"],
		];
		$values = [
			"name" => "Jo",
			"photo" => "{wwwroot}files/a.jpg",
			"resume" => "{wwwroot}files/secret.pdf",
			"logo" => 12,
			"clip" => ["new" => "https://youtu.be/x", "managed" => 4],
			"blocks" => [["image" => "resource://files/x.jpg", "title" => "T"]],
			"__photo_recrop__" => "on",
		];
		$tokens = ["photo" => EmbedFormService::mintUploadToken("abc", "photo", "{wwwroot}files/a.jpg")];
		$clean = EmbedFormService::cleanPostData($values, $fields, $tokens, "abc");

		T::equals($clean["name"], "Jo", "plain values pass through");
		T::equals($clean["photo"], "{wwwroot}files/a.jpg", "an issued image path is kept");
		T::equals($clean["resume"], "", "a file path without a token is cleared");
		T::ok(!array_key_exists("logo", $clean), "media-library references are dropped");
		T::equals($clean["clip"], ["new" => "https://youtu.be/x"], "a library pick on a video field is dropped");
		T::equals($clean["blocks"][0]["image"], "", "nested resource:// values are cleared");
		T::ok(!array_key_exists("__photo_recrop__", $clean), "control keys are dropped");
	}

	function test_embed_form_extension_allowed() {
		T::ok(EmbedFormService::extensionAllowed("cv.PDF", "pdf, .docx"), "case- and dot-insensitive match");
		T::ok(!EmbedFormService::extensionAllowed("cv.exe", "pdf,docx"), "an unlisted extension is rejected");
		T::ok(EmbedFormService::extensionAllowed("anything.zip", ""), "no list allows any extension");
	}

	function embed_form_matrix_fixture(): array {
		return [
			"fields" => [
				["column" => "photo", "type" => "image", "settings" => ["directory" => "files/top/"]],
				[
					"column" => "gallery",
					"type" => "matrix",
					"settings" => [
						"columns" => [
							["id" => "caption", "type" => "text"],
							["id" => "shot", "type" => "image", "settings" => json_encode(["min_width" => 10])],
							["id" => "doc", "type" => "upload", "settings" => ["directory" => "files/docs/"]],
							["id" => "pick", "type" => "image-reference"],
							[
								"id" => "inner",
								"type" => "matrix",
								"settings" => ["columns" => [["id" => "deep", "type" => "image"]]],
							],
						],
					],
				],
			],
		];
	}

	function test_embed_form_require_field_resolves_matrix_paths() {
		$form = embed_form_matrix_fixture();

		$top = EmbedFormService::requireField($form, "photo", ["image"]);
		T::equals($top["settings"]["directory"], "files/top/", "a top-level field keeps its settings");

		$shot = EmbedFormService::requireField($form, "gallery.shot", ["image"]);
		T::equals($shot["column"], "gallery.shot", "the path is the field's column");
		T::equals($shot["settings"]["min_width"], 10, "JSON-stored sub-field settings are decoded");
		T::equals($shot["settings"]["directory"], "files/pages/", "a sub-field without a directory gets the matrix default");

		$doc = EmbedFormService::requireField($form, "gallery.doc", ["upload"]);
		T::equals($doc["settings"]["directory"], "files/docs/", "a sub-field's own directory wins");

		$deep = EmbedFormService::requireField($form, "gallery.inner.deep", ["image"]);
		T::equals($deep["type"], "image", "a matrix inside a matrix resolves");

		foreach (["gallery", "gallery.caption", "gallery.doc.x", "gallery.missing", "caption", "gallery.pick", "photo.shot", ""] as $path) {
			T::throws(function () use ($form, $path) {
				EmbedFormService::requireField($form, $path, ["image", "upload"]);
			}, BadRequestException::class, "'$path' isn't an upload field");
		}
	}

	function test_embed_form_clean_post_data_matrix_rows() {
		$form = embed_form_matrix_fixture();
		$values = [
			"gallery" => [
				[
					"caption" => "One",
					"shot" => "{wwwroot}files/pages/one.jpg",
					"doc" => "{wwwroot}files/docs/cv.pdf",
					"pick" => 7,
					"inner" => [["deep" => "{wwwroot}files/pages/deep.jpg"], "not a row"],
					"__internal-title" => "One",
				],
				[
					"caption" => "Two",
					"shot" => "{wwwroot}files/pages/one.jpg",
					"doc" => "{wwwroot}custom/environment.php",
					"__unchanged" => json_encode(["shot" => "resource://files/x.jpg"]),
				],
				"junk",
			],
		];
		$tokens = [
			EmbedFormService::mintUploadToken("abc", "gallery.shot", "{wwwroot}files/pages/one.jpg"),
			EmbedFormService::mintUploadToken("abc", "gallery.doc", "{wwwroot}files/docs/cv.pdf"),
			EmbedFormService::mintUploadToken("abc", "gallery.inner.deep", "{wwwroot}files/pages/deep.jpg"),
			EmbedFormService::mintUploadToken("abc", "photo", "{wwwroot}files/pages/one.jpg"),
			"garbage",
			["not" => "a string"],
		];
		$clean = EmbedFormService::cleanPostData($values, $form["fields"], $tokens, "abc");
		$rows = $clean["gallery"];

		T::equals(count($rows), 2, "non-row entries are dropped");
		T::equals($rows[0]["caption"], "One", "plain sub-values pass through");
		T::equals($rows[0]["shot"], "{wwwroot}files/pages/one.jpg", "a nested image with its token is kept");
		T::equals($rows[0]["doc"], "{wwwroot}files/docs/cv.pdf", "a nested upload with its token is kept");
		T::ok(!array_key_exists("pick", $rows[0]), "nested media-library references are dropped");
		T::ok(!array_key_exists("__internal-title", $rows[0]), "row control keys are dropped");
		T::equals($rows[0]["inner"], [["deep" => "{wwwroot}files/pages/deep.jpg"]], "a matrix inside a matrix is cleaned too");
		T::equals($rows[1]["shot"], "{wwwroot}files/pages/one.jpg", "one upload can fill the same column in another row");
		T::equals($rows[1]["doc"], "", "a nested path without a token is cleared");
		T::ok(!array_key_exists("__unchanged", $rows[1]), "an __unchanged blob can't smuggle values past the checks");

		$crossed = EmbedFormService::cleanPostData(
			["photo" => "{wwwroot}files/pages/deep.jpg"],
			$form["fields"],
			$tokens,
			"abc"
		);
		T::equals($crossed["photo"], "", "a token for a nested path doesn't vouch for a top-level column");
	}
